<?php

namespace Decahedron\AppEvents\Tests;

use Decahedron\AppEvents\AppEvent;
use Decahedron\AppEvents\PubSubQueue;
use Decahedron\AppEvents\Tests\Proto\Test;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
use Google\Cloud\PubSub\Topic;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Mockery;
use PHPUnit\Framework\TestCase;

class PubSubQueueTest extends TestCase
{
    /**
     * @var Topic|Mockery\MockInterface
     */
    protected $topic;

    /**
     * @var PubSubQueue|Mockery\MockInterface
     */
    protected $queue;

    /**
     * @var PubSubClient|Mockery\MockInterface
     */
    protected $client;

    /**
     * @var Subscription|Mockery\MockInterface
     */
    protected $subscription;

    /**
     * @var Container|Mockery\MockInterface
     */
    protected $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topic        = Mockery::mock(Topic::class);
        $this->client       = Mockery::mock(PubSubClient::class);
        $this->subscription = Mockery::mock(Subscription::class);
        $this->container    = Mockery::mock(Container::class);
    }

    public function test_it_correctly_serializes_jobs_when_pushing_them_onto_the_queue()
    {
        $queue     = new PubSubQueue($this->client, 'test');
        $this->container
            ->shouldReceive('make')
            ->with('config')
            ->andReturn(
                Mockery::mock(Repository::class)
                    ->shouldReceive('get')
                    ->with('app-events.mappings')
                    ->andReturn(['Test' => Test::class])
                    ->getMock()
                    ->shouldReceive('get')
                    ->with('app-events.subscription')
                    ->andReturn('app1')
                    ->getMock()
            )
            ->getMock();

        $queue->setContainer($this->container);
        $this->client
            ->shouldReceive('topic')
            ->andReturn($this->topic);

        $message = new Test(['message' => 'hi']);
        $this->topic
            ->shouldReceive('subscription')
            ->with('app1')
            ->andReturn(
                Mockery::mock(Subscription::class)
                    ->shouldReceive('exists')
                    ->andReturn(true)
                    ->getMock()
            )
            ->getMock()
            ->shouldReceive('exists')
            ->once()
            ->andReturn(true)
            ->getMock()
            ->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(function ($argument) use ($message) {
                $data = json_decode($argument['data'], true);
                $this->assertEquals('Test', $data['proto']);
                $this->assertEquals(base64_encode($message->serializeToString()), $data['payload']);
                $this->assertArrayHasKey('id', $data);
                $this->assertEquals(['event_type' => 'the-event'], $argument['attributes']);

                return true;
            }));

        $this->assertEquals(32, mb_strlen($queue->push(new AppEvent('the-event', $message))));
    }

    public function test_it_can_push_raw_jobs()
    {
        $queue     = new PubSubQueue($this->client, 'test');
        $this->container
            ->shouldReceive('make')
            ->with('config')
            ->andReturn(
                Mockery::mock(Repository::class)
                    ->shouldReceive('get')
                    ->with('app-events.subscription')
                    ->andReturn('app1')
                    ->getMock()
            )
            ->getMock();

        $queue->setContainer($this->container);
        $this->client
            ->shouldReceive('topic')
            ->andReturn($this->topic);

        $this->topic
            ->shouldReceive('subscription')
            ->with('app1')
            ->andReturn(
                Mockery::mock(Subscription::class)
                    ->shouldReceive('exists')
                    ->andReturn(true)
                    ->getMock()
            )
            ->getMock()
            ->shouldReceive('exists')
            ->once()
            ->andReturn(true)
            ->getMock()
            ->shouldReceive('publish')
            ->once()
            ->with(['data' => '{"id":"test"}']);

        $this->assertEquals('test', $queue->pushRaw('{"id":"test"}'));
    }
}
