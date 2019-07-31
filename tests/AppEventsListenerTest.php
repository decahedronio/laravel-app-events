<?php

namespace Decahedron\AppEvents\Tests;

use Decahedron\AppEvents\Commands\AppEventsListener;
use Decahedron\AppEvents\Tests\Proto\Test;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
use Google\Cloud\PubSub\Topic;
use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\TesterTrait;

class AppEventsListenerTest extends TestCase
{
    use TesterTrait, Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    public function test_it_suppresses_errors()
    {
        $client = Mockery::mock(PubSubClient::class);
        $client->shouldReceive('topic')
            ->andReturn($topic = Mockery::mock(Topic::class));

        $topic->shouldReceive('exists')
            ->andReturnTrue()
            ->getMock()
            ->shouldReceive('subscription')
            ->with('sub1')
            ->andReturn($sub = Mockery::mock(Subscription::class));

        $message = new Test(['message' => 'hi']);

        Config::shouldReceive('get')
            ->with('app-events.mappings.Test')
            ->andReturn(Test::class);
        Config::shouldReceive('get')
            ->with('app-events.handlers')
            ->andReturn(['some_event' => TestExceptionHandler::class]);

        $sub->shouldReceive('exists')
            ->andReturnTrue()
            ->getMock()
            ->shouldReceive('pull')
            ->with(['maxMessages' => 500])
            ->andReturn([
                $pubsubMessage = new Message([
                    'data' => json_encode([
                        'proto' => 'Test',
                        'payload' => base64_encode($message->serializeToString()),
                        'id' => 'sj38zd'
                    ]),
                    'attributes' => ['event_type' => 'some_event'],
                ], []),
            ]);

        $config = Mockery::mock(Repository::class)
            ->shouldReceive('get')
            ->with('app-events.topic')
            ->andReturn('mytopic')
            ->getMock()
            ->shouldReceive('get')
            ->with('app-events.subscription')
            ->andReturn('sub1')
            ->getMock();

        $input = new ArrayInput(['--single' => true]);

        $this->initOutput([]);
        $listener = new AppEventsListener($client, $config);

        $container = Mockery::mock(Container::class)
            ->shouldReceive('make')
            ->with(OutputStyle::class, ['input' => $input, 'output' => $this->output])
            ->andReturn(new OutputStyle($input, $this->output))
            ->getMock()
            ->shouldReceive('call')
            ->with([$listener, 'handle'])
            ->once()
            ->andReturnUsing(function () use ($listener) {
                return $listener->handle();
            })
            ->getMock();

        Container::setInstance($container);

        $listener->setLaravel($container);

        $log = Log::spy();
        $listener->run($input, $this->output);
        $log->shouldHaveReceived('error');
        $sub->shouldNotHaveReceived('acknowledgeBatch');
        $container->shouldHaveReceived('make')
        ->with(TestExceptionHandler::class)
        ->once();
    }
}

class TestExceptionHandler
{
    public function handle()
    {
        throw new \Exception('failure');
    }
}