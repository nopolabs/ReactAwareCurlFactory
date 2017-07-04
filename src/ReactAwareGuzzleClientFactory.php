<?php

namespace Nopolabs;


use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class ReactAwareGuzzleClientFactory
{
    private $createHandlerStack;

    public function __construct(callable $createHandlerStack = null)
    {
        $this->createHandlerStack = $createHandlerStack;
    }

    public function createGuzzleClient(
        LoopInterface $eventLoop,
        array $config = [],
        CurlFactory $curlFactory = null,
        LoggerInterface $logger = null
    ) : Client
    {
        $reactAwareCurlFactory = $this->createReactAwareCurlFactory($eventLoop, $curlFactory, $logger);

        $handler = $reactAwareCurlFactory->getHandler();

        $handlerStack = $this->createHandlerStack($handler);

        $config['handler'] = $handlerStack;

        return new Client($config);
    }

    public function createReactAwareCurlFactory(
        LoopInterface $eventLoop,
        CurlFactory $curlFactory = null,
        LoggerInterface $logger = null
    ) : ReactAwareCurlFactory
    {
        $curlFactory = $curlFactory ?? new CurlFactory(50);
        $reactAwareCurlFactory = new ReactAwareCurlFactory($eventLoop, $curlFactory, $logger);
        $handler = new CurlMultiHandler(['handle_factory' => $reactAwareCurlFactory]);
        $reactAwareCurlFactory->setHandler($handler);

        return $reactAwareCurlFactory;
    }

    private function createHandlerStack(CurlMultiHandler $handler)
    {
        if ($this->createHandlerStack) {
            $create = $this->createHandlerStack;
            return $create($handler);
        }

        return HandlerStack::create($handler);
    }
}