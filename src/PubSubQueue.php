<?php

namespace Decahedron\AppEvents;

use Google\Cloud\PubSub\Message;
use Kainxspirits\PubSubQueue\Jobs\PubSubJob;
use Kainxspirits\PubSubQueue\PubSubQueue as BaseQueue;

class PubSubQueue extends BaseQueue
{
    protected function createPayload($job, $queue, $data = '')
    {
        $protoMappings = config('app-events.mappings');
        $payloadClass = get_class($job->payload);

        $payload = [
            'proto' => array_flip($protoMappings)[$payloadClass] ?? $payloadClass,
            'payload' => $job->payload->serializeToString(),
            'id' => $this->getRandomId(),
        ];

        return base64_encode(json_encode($payload));
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $this->getQueue($queue), $data), $queue, [
            'event_type' => $job->event,
        ]);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @param  array   $options
     *
     * @return array
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $topic = $this->getTopic($queue, true);

        $this->subscribeToTopic($topic);

        $publish = ['data' => $payload];

        if (! empty($options)) {
            $publish['attributes'] = $options;
        }

        $topic->publish($publish);

        $decoded_payload = json_decode(base64_decode($payload), true);

        return $decoded_payload['id'];
    }


    public function pop($queue = null)
    {
        $topic = $this->getTopic($this->getQueue($queue));

        if (! $topic->exists()) {
            return;
        }

        $subscription = $topic->subscription($this->getSubscriberName());
        $messages = $subscription->pull([
            'returnImmediately' => true,
            'maxMessages' => 1,
        ]);

        if (! empty($messages) && count($messages) > 0) {
            return new PubSubJob(
                $this->container,
                $this,
                $this->transformMessage($messages[0]),
                $this->connectionName,
                $this->getQueue($queue)
            );
        }
    }

    private function transformMessage(Message $message)
    {
        $job = AppEventFactory::fromMessage($message);

        $payload = [
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => get_class($job),
                'command' => serialize(clone $job)
            ]
        ];

        return new Message(
            [
                'data' => base64_encode(json_encode($payload)),
                'messageId' => $message->id(),
                'publishTime' => $message->publishTime(),
                'attributes' => $message->attributes(),
            ],
            [
                'ackId' => $message->ackId(),
                'subscription' => $message->subscription(),
            ]
        );
    }

    public function getSubscriberName()
    {
        return $this->container['config']->get('app-events.subscription');
    }
}
