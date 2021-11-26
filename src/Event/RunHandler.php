<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-01-08 10:02:48
 *
 */
namespace Kovey\Tcp\Event;

use Kovey\Event\EventInterface;
use Kovey\Tcp\Handler\HandlerAbstract;
use Google\Protobuf\Internal\Message;

class RunHandler implements EventInterface
{
    private HandlerAbstract $hander;

    private string $method;

    private Message $message;

    private int $fd;

    private ?Message $base;

    private string $traceId;

    private string $spanId;

    public function __construct(HandlerAbstract $hander, string $method, Message $message, int $fd, ?Message $base, string $traceId, string $spanId)
    {
        $this->hander = $hander;
        $this->fd = $fd;
        $this->method = $method;
        $this->message = $message;
        $this->base = $base;
        $this->traceId = $traceId;
        $this->spanId = $spanId;
    }

    public function getHandler() : HandlerAbstract
    {
        return $this->hander;
    }

    public function getMessage() : Message
    {
        return $this->message;
    }

    public function getFd() : int
    {
        return $this->fd;
    }

    public function getMethod() : string
    {
        return $this->method;
    }

    public function getBase() : ?Message
    {
        return $this->base;
    }

    public function getTraceId() : string
    {
        return $this->traceId;
    }

    public function getSpanId() : string
    {
        return $this->spanId;
    }

    /**
     * @description propagation stopped
     *
     * @return bool
     */
    public function isPropagationStopped() : bool
    {
        return true;
    }

    /**
     * @description stop propagation
     *
     * @return EventInterface
     */
    public function stopPropagation() : EventInterface
    {
        return $this;
    }
}
