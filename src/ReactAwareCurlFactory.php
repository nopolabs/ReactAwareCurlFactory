<?php

namespace Nopolabs;


use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlFactoryInterface;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Handler\EasyHandle;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

class ReactAwareCurlFactory implements CurlFactoryInterface
{
    private $eventLoop;
    private $logger;
    private $factory;
    private $count;

    /** @var CurlMultiHandler */
    private $handler;

    /** @var TimerInterface */
    private $timer;

    /**
     * ReactAwareCurlFactory wraps an instance of CurlFactory and keeps
     * track of active curl handles. When there are active curl handles it starts
     * a periodic task on the React event loop that calls CurlMultiHandler::tick()
     * allowing tasks in the Guzzle task queue to be processed.
     * The periodic task is stopped when there are no more active curl handles
     * and the Guzzle task queue is empty.
     *
     * @param LoopInterface $eventLoop
     * @param LoggerInterface|null $logger
     */
    protected function __construct(LoopInterface $eventLoop, LoggerInterface $logger = null)
    {
        $this->eventLoop = $eventLoop;
        $this->logger = $logger ?? new NullLogger();

        $this->factory = new CurlFactory(50);
        $this->count = 0;
    }

    /**
     * There is a circular dependency between ReactAwareCurlFactory and CurlMultiHandler.
     *
     * @param LoopInterface $eventLoop
     * @param LoggerInterface|null $logger
     * @return ReactAwareCurlFactory
     */
    public static function createFactory(
        LoopInterface $eventLoop,
        LoggerInterface $logger = null) : ReactAwareCurlFactory
    {
        $reactAwareCurlFactory = new ReactAwareCurlFactory($eventLoop, $logger);
        $handler = new CurlMultiHandler(['handle_factory' => $reactAwareCurlFactory]);
        $reactAwareCurlFactory->setHandler($handler);

        return $reactAwareCurlFactory;
    }

    /**
     * Example usage: $client = ReactAwareCurlFactory::createFactory($eventLoop)->createClient();
     *
     * @param HandlerStack|null $handlerStack
     * @return Client
     */
    public function createClient(HandlerStack $handlerStack = null) : Client
    {
        $config['handler'] = $handlerStack ?? HandlerStack::create($this->getHandler());

        return new Client($config);
    }

    /**
     * Access to the CurlMultiHandler to allow construction of a Guzzle Client with a custom HandlerStack.
     *
     * @return CurlMultiHandler
     */
    public function getHandler() : CurlMultiHandler
    {
        return $this->handler;
    }

    public function tick()
    {
        $this->getHandler()->tick();

        if ($this->noMoreWork()) {
            $this->stopTimer();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(RequestInterface $request, array $options)
    {
        $this->incrementCount();

        return $this->factory->create($request, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function release(EasyHandle $easy)
    {
        $this->factory->release($easy);

        $this->decrementCount();
    }

    protected function setHandler(CurlMultiHandler $handler)
    {
        $this->handler = $handler;
    }

    protected function noMoreWork() : bool
    {
        return $this->noActiveHandles() && $this->queueIsEmpty();
    }

    protected function noActiveHandles() : bool
    {
        return $this->count === 0;
    }

    protected function queueIsEmpty() : bool
    {
        return \GuzzleHttp\Promise\queue()->isEmpty();
    }

    protected function incrementCount()
    {
        if ($this->noActiveHandles()) {
            $this->startTimer();
        }

        $this->count++;
    }

    protected function decrementCount()
    {
        $this->count--;
    }

    protected function startTimer()
    {
        if ($this->timer === null) {
            $this->timer = $this->eventLoop->addPeriodicTimer(0, [$this, 'tick']);

            $this->logger->debug('ReactAwareCurlFactory started periodic queue processing');
        }
    }

    protected function stopTimer()
    {
        if ($this->timer !== null) {
            $this->timer->cancel();
            $this->timer = null;

            $this->logger->debug('ReactAwareCurlFactory stopped periodic queue processing');
        }
    }
}
