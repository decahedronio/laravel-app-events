<?php

namespace Decahedron\AppEvents;

use Exception;
use Google\Cloud\PubSub\Subscription;

class SubscriptionTopicMismatchException extends Exception
{
    public function __construct(string $expectedTopic, Subscription $subscription)
    {
        $actualTopic = $subscription->info()['topic'];

        parent::__construct("subscription {$subscription->name()} bound to topic \"$actualTopic\", expected \"$expectedTopic\"");
    }
}
