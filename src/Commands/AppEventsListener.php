<?php

namespace Decahedron\AppEvents\Commands;

use Decahedron\AppEvents\AppEvent;
use Decahedron\AppEvents\AppEventFactory;
use Decahedron\AppEvents\UnserializableProtoException;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Console\Command;

class AppEventsListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app-events:listen';

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

            foreach ($messages as $message) {
                try {
                    $job = AppEventFactory::fromMessage($message);
                } catch (UnserializableProtoException $e) {
                    $this->info('No handler registered for message type: '.$e->protoMessageType);
                }
                $this->info('Handling message: '.$job->event);

                $job->handle();
            }

            $subscription->acknowledgeBatch($messages);
        }
    }
}
