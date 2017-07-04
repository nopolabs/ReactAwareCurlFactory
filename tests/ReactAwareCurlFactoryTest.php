<?php

namespace Nopolabs;


use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Handler\EasyHandle;
use Nopolabs\Test\MockWithExpectationsTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

class ReactAwareCurlFactoryTest extends TestCase
{
    use MockWithExpectationsTrait;

    private $eventLoop;
    private $curlFactory;
    private $logger;

    protected function setUp()
    {
        $this->eventLoop = $this->createMock(LoopInterface::class);
        $this->curlFactory = $this->createMock(CurlFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testStartsAndStopsTimer()
    {
        $handler = $this->newPartialMockWithExpectations(CurlMultiHandler::class, [
            'tick' => ['invoked' => 3],
        ]);

        $factory = $this->newReactAwareCurlFactory([
            'getHandler' => ['invoked' => 3, 'result' => $handler],
        ]);

        $timer = $this->newPartialMockWithExpectations(TimerInterface::class, [
            'isActive' => ['invoked' => 'any', 'result' => true],
            'cancel' => ['invoked' => 1],
        ]);

        $this->setAtExpectations($this->eventLoop, [
            ['addPeriodicTimer', ['params' => [0, [$factory, 'tick']], 'result' => $timer]],
        ]);

        $request = $this->createMock(RequestInterface::class);
        $options = [];

        $easy = new EasyHandle();

        $this->setAtExpectations($this->curlFactory, [
            ['create', ['params' => [$request, $options], 'result' => $easy]],
        ]);

        $this->assertFalse($factory->isTimerActive());

        $actual = $factory->create($request, $options);

        $this->assertEquals($easy, $actual);
        $this->assertTrue($factory->isTimerActive());

        $factory->tick();
        $factory->tick();

        $factory->release($easy);

        $this->assertTrue($factory->isTimerActive());

        $factory->tick();

        $this->assertFalse($factory->isTimerActive());
    }

    public function testTickStopsTimerWhenNoMoreWork()
    {
        $handler = $this->newPartialMockWithExpectations(CurlMultiHandler::class, [
            'tick' => ['invoked' => 2],
        ]);

        $factory = $this->newReactAwareCurlFactory([
            ['getHandler', ['result' => $handler]],
            ['noMoreWork', ['result' => false]],
            ['getHandler', ['result' => $handler]],
            ['noMoreWork', ['result' => true]],
            ['stopTimer', []],
        ]);

        $factory->tick();
        $factory->tick();
    }

    public function noMoreWorkDataProvider()
    {
        return [
            [false, false, false],
            [false, true, false],
            [true, false, false],
            [true, true, true],
        ];
    }

    /**
     * @dataProvider noMoreWorkDataProvider
     */
    public function testNoMoreWork(bool $noActiveHandles, bool $queueIsEmpty, bool $noMoreWork)
    {
        $factory = $this->newReactAwareCurlFactory([
            'noActiveHandles' => ['invoked' => 'any', 'result' => $noActiveHandles],
            'queueIsEmpty' => ['invoked' => 'any', 'result' => $queueIsEmpty],
        ]);

        $this->assertEquals($noMoreWork, $factory->noMoreWork());
    }

    private function newReactAwareCurlFactory(array $expectations = []) : ReactAwareCurlFactory
    {
        if (empty($expectations)) {
            return new ReactAwareCurlFactory(
                $this->eventLoop,
                $this->curlFactory,
                $this->logger
            );
        }

        return $this->newPartialMockWithExpectations(
            ReactAwareCurlFactory::class,
            $expectations,
            [
                $this->eventLoop,
                $this->curlFactory,
                $this->logger
            ]
        );
    }
}