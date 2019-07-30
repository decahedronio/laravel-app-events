<?php

namespace Decahedron\AppEvents;

use Illuminate\Bus\Queueable;
use Google\Protobuf\Internal\Message;
use Illuminate\Contracts\Queue\ShouldQueue;

class AppEvent implements ShouldQueue
{
    use Queueable;

    /**
     * @var string
     */
    public $event;

    /**
     * @var Message
     */
    public $payload;

    /**
     * Event constructor.
     * @param string $event
     * @param        $payload
     */
    public function __construct(string $event, Message $payload)
    {
        $this->onConnection('app-events');
        $this->payload = $payload;
        $this->event = $event;
    }

    public function handle()
    {
        foreach (config('app-events.handlers') as $event => $handler) {
            if ($this->event !== $event) {
                continue;
            }

            app()->make($handler)->handle($this->payload, $event);
        }
    }
}
