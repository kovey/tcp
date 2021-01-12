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

class Pack implements EventInterface
{
    private mixed $packet;

    private int $action;

    public function __construct(mixed $packet, int $action)
    {
        $this->packet = $packet;
        $this->action = $action;
    }

    public function getPacket() : mixed
    {
        return $this->packet;
    }

    public function getAction() : int
    {
        return $this->action;
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
