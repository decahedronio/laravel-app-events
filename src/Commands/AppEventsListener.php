<?php

namespace Decahedron\AppEvents\Commands;

use Decahedron\AppEvents\UnsupportedEventException;
use Exception;
use Google\Cloud\Core\Exception\BadRequestException;
use Google\Cloud\PubSub\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Google\Cloud\PubSub\PubSubClient;
use Decahedron\AppEvents\AppEventFactory;
use Illuminate\Contracts\Config\Repository;
use Decahedron\AppEvents\UnserializableProtoException;
use Decahedron\AppEvents\SubscriptionTopicMismatchException;

class AppEventsListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app-events:listen {--stop-on-failure} {--single} {--silent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for notifications across all services of your application';

    /**
     * @var PubSubClient
     */
    protected $pubSub;

    /**
     * @var Repository
     */
    protected $config;

    /**
     * AppEventsListener constructor.
     * @param PubSubClient $pubSubClient
     * @param Repository   $config
     */
    public function __construct(PubSubClient $pubSubClient, Repository $config)
    {
        parent::__construct();

        $this->pubSub = $pubSubClient;
        $this->config = $config;
    }

    public function handle()
    {
        $topic = $this->pubSub->topic($this->config->get('app-events.topic'));
        $subscriptionName = $this->config->get('app-events.subscription_prefix') . $this->config->get('app-events.subscription');
        if (!$topic->exists()) {
            $topic->create();
        }

        $subscription = $topic->subscription($subscriptionName);
        if (!$subscription->exists()) {
            $subscription->create();
        }

        // The full topic is projects/{project}/topics/{topic} and we only want {topic}
        $parts = explode('/', $subscription->info()['topic']);
        $topicName = array_pop($parts);

        if ($topicName !== $this->config->get('app-events.topic')) {
            throw new SubscriptionTopicMismatchException($this->config->get('app-events.topic'), $subscription);
        }

        if (! $this->option('silent')) {
            $this->info('Starting to listen for events');
        }
        do {
            $this->runIteration($subscription);
        } while (! $this->option('single'));
    }

    /**
     * @throws BadRequestException
     */
    private function runIteration(Subscription $subscription)
    {
        $messages = $subscription->pull([
            'maxMessages' => 500,
        ]);

        if (count($messages) === 0) {
            return;
        }

        $handledMessages = [];

        foreach ($messages as $message) {
            try {
                $job = AppEventFactory::fromMessage($message);
            } catch (UnserializableProtoException $e) {
                if (! $this->option('silent')) {
                    $this->info('No implementation registered for message type: ' . $e->protoMessageType);
                }
                $handledMessages[] = $message;
                continue;
            } catch (UnsupportedEventException $e) {
                Log::debug('Unsupported message', [
                    'exception' => $e,
                    'message' => $message->info(),
                    'published_at' => $message->publishTime(),
                ]);
                $handledMessages[] = $message;
                continue;
            }
            if (! $this->option('silent')) {
                $this->info('Handling message: '.$job->event);
            }

            try {
                $job->handle();
                $handledMessages[] = $message;
            } catch (Exception $e) {
                if ($this->option('stop-on-failure')) {
                    $subscription->acknowledgeBatch($handledMessages);
                    throw $e;
                }

                Log::error('Failed to handle app event', [
                    'exception' => $e,
                    'message' => $message->info(),
                    'published_at' => $message->publishTime(),
                ]);
            }
        }

        if (count ($handledMessages)) {
            $subscription->acknowledgeBatch($handledMessages);
        }
    }
}
