<?php

namespace Decahedron\AppEvents;

use Exception;

class UnserializableProtoException extends Exception
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
