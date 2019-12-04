<?php

namespace Decahedron\AppEvents\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Google\Cloud\PubSub\PubSubClient;
use Decahedron\AppEvents\AppEventFactory;
use Decahedron\AppEvents\UnserializableProtoException;

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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $topic = $this->pubSub->topic($this->config->get('app-events.topic'));
        if (!$topic->exists()) {
            $topic->create();
        }

        $subscription = $topic->subscription($this->config->get('app-events.subscription'));
        if (!$subscription->exists()) {
            $subscription->create();
        }

        if (! $this->option('silent')) {
            $this->info('Starting to listen for events');
        }
        do {
            $messages = $subscription->pull([
                'maxMessages' => 500,
            ]);

            if (count($messages) === 0) {
                continue;
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
                }
                if (! $this->option('silent')) {
                    $this->info('Handling message: '.$job->event);
                }

                try {
                    $job->handle();
                    $handledMessages[] = $message;
                } catch (Exception $e) {
                    if (! $this->option('stop-on-failure')) {
                        Log::error('Failed to handle app event', ['exception' => $e]);
                    } else {
                        $subscription->acknowledgeBatch($handledMessages);
                        throw $e;
                    }
                }
            }

            if (count ($handledMessages)) {
                $subscription->acknowledgeBatch($handledMessages);
            }
        } while (! $this->option('single'));
    }
}
