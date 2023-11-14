<?php

namespace Decahedron\AppEvents;

use Google\Cloud\PubSub\Message;
use Google\Protobuf\Internal\Message as ProtobufMessage;
use Illuminate\Support\Facades\Config;

class AppEventFactory
{
    /**
     * @throws UnserializableProtoException
     * @throws UnsupportedEventException
     */
    public static function fromMessage(Message $message): AppEvent
    {
        return new AppEvent($message->attribute('event_type'), static::resolveProtobufInstance($message));
    }

    /**
     * @throws UnserializableProtoException
     * @throws UnsupportedEventException
     * @throws \Exception
     */
    protected static function resolveProtobufInstance(Message $message): ProtobufMessage
    {
        $rawData = json_decode($message->data(), JSON_OBJECT_AS_ARRAY);

        if (! isset($rawData['proto'])) {
            throw new UnserializableProtoException('ProtoUnknown');
        }

        if (! ($protobufClass = Config::get('app-events.mappings.'.$rawData['proto']))) {
            throw new UnsupportedEventException($rawData['proto']);
        }

        /** @var ProtobufMessage $proto */
        $proto = new $protobufClass;
        $proto->mergeFromString(base64_decode($rawData['payload']));

        return $proto;
    }
}
