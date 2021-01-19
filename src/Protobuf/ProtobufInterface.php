<?php
/**
 * @description protobuf interface
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-04-29 17:36:23
 *
 */
namespace Kovey\Tcp\Protobuf;

use Google\Protobuf\Internal\Message;

interface ProtobufInterface
{
    /**
     * @description get message
     *
     * @return Message
     */
    public function getMessage() : ?Message;

    /**
     * @description get handler
     *
     * @return string
     */
    public function getHandler() : string;

    /**
     * @description get method
     *
     * @return string
     */
    public function getMethod() : string;
}
