<?php

namespace Nopolabs;


use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;

class ReactAwareGuzzleClientTest extends TestCase
{
    private $response;

    public function testClient()
    {
        $eventLoop = Factory::create();

        $factory = new ReactAwareGuzzleClientFactory();

        $client = $factory->createGuzzleClient($eventLoop);

        $promise = $client->getAsync('https://google.com')
            ->then(function(ResponseInterface $response) {
                $this->response = $response;
                return $response;
            });

        // 'run' the event loop
        while ($promise->getState() === PromiseInterface::PENDING) {
            $eventLoop->tick();
        }

        $this->assertNotNull($this->response);
        $this->assertInstanceOf(ResponseInterface::class, $this->response);
        $this->assertSame($this->response, $promise->wait());
    }
}