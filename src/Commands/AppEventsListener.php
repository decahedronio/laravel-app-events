<?php

namespace Decahedron\AppEvents\Commands;

use Exception;
use Illuminate\Console\Command;
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
    protected $signature = 'app-events:listen {--stop-on-failure}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for notifications across all services of your application';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $pubSub = new PubSubClient([
            'projectId' => config('app-events.project_id'),
        ]);

        $topic = $pubSub->topic(config('app-events.topic'));
        if (!$topic->exists()) {
            $topic->create();
        }

        $subscription = $topic->subscription(config('app-events.subscription'));
        if (!$subscription->exists()) {
            $subscription->create();
        }

        $this->info('Starting to listen for events');
        while (true) {
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
                    $this->info('No implementation registered for message type: '.$e->protoMessageType);
                    continue;
                }
                $this->info('Handling message: '.$job->event);

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

            $subscription->acknowledgeBatch($handledMessages);
        }
    }
}
