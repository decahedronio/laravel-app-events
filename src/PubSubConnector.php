<?php

namespace Decahedron\AppEvents;

use Google\Cloud\PubSub\PubSubClient;
use Kainxspirits\PubSubQueue\Connectors\PubSubConnector as BaseConnector;

class PubSubConnector extends BaseConnector
{
    public function connect(array $config)
    {
        $gcp_config = $this->transformConfig($config);

        return new PubSubQueue(
            new PubSubClient($gcp_config),
            $config['queue'] ?? $this->default_queue
        );
    }
}
