<?php
/**
 * @description
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
     * @description 获取消息
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
