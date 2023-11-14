<?php

namespace Decahedron\AppEvents;

use Exception;

class UnsupportedEventException extends Exception
{
    /**
     * @var string|string
     */
    public $protoMessageType;

    /**
     * UnserializableProtoException constructor.
     * @param string $protoMessageType
     */
    public function __construct(string $protoMessageType)
    {
        $this->protoMessageType = $protoMessageType;
    }
}
