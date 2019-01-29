<?php

namespace Decahedron\AppEvents;

use Google\Cloud\PubSub\Message;
use Google\Protobuf\Internal\Message as ProtobufMessage;

class AppEventFactory
{

    public static function fromMessage(Message $message): AppEvent
    {
        return new AppEvent($message->attribute('event_type'), static::resolveProtobufInstance($message));
    }

    protected static function resolveProtobufInstance(Message $message): ProtobufMessage
    {
        $rawData = json_decode(base64_decode($message->data()), JSON_OBJECT_AS_ARRAY);

        if (! ($protobufClass = config('app-events.mappings.'.$rawData['proto']))) {
            throw new UnserializableProtoException($rawData['proto']);
        }

        /** @var ProtobufMessage $proto */
        $proto = new $protobufClass;
        $proto->mergeFromString($rawData['payload']);

        return $proto;
    }
}
