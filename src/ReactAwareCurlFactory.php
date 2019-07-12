<?php

namespace Nopolabs;


use Exception;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlFactoryInterface;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Handler\EasyHandle;
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

    /** @var TimerInterface|null */
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
     * @param CurlFactory $curlFactory
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        LoopInterface $eventLoop,
        CurlFactory $curlFactory,
        LoggerInterface $logger = null
    )
    {
        $this->eventLoop = $eventLoop;
        $this->factory = $curlFactory;
        $this->logger = $logger ?? new NullLogger();

        $this->count = 0;
    }

    public function setHandler(CurlMultiHandler $handler)
    {
        $this->handler = $handler;
    }

    public function getHandler() : CurlMultiHandler
    {
        return $this->handler;
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

    public function tick()
    {
        try {
            $this->getHandler()->tick();
        } catch (Exception $exception) {
            $this->logger->warning('ReactAwareCurlFactory::tick() '.$exception->getMessage());
        }

        if ($this->noMoreWork()) {
            $this->stopTimer();
        }
    }

    public function isTimerActive() : bool
    {
        if ($this->timer) {
            return $this->timer->isActive();
        }

        return false;
    }

    public function noMoreWork() : bool
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
