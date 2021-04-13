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
use Kovey\Tcp\Protocol\ProtocolInterface;

class Handler implements EventInterface
{
    private ProtocolInterface $packet;

    private int $fd;

    private string $ip;

    private string $traceId;

    private string $spanId;

    public function __construct(ProtocolInterface $packet, int $fd, string $ip, string $traceId, string $spanId)
    {
        $this->packet = $packet;
        $this->fd = $fd;
        $this->ip = $ip;
        $this->traceId = $traceId;
        $this->spanId = $spanId;
    }

    public function getPacket() : ProtocolInterface
    {
        return $this->packet;
    }

    public function getFd() : string
    {
        return $this->fd;
    }

    public function getIp() : string
    {
        return $this->ip;
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
