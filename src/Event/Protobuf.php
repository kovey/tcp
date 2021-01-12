<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-01-12 14:33:57
 *
 */
namespace Kovey\Tcp\Event;

use Kovey\Event\EventInterface;
use Kovey\Tcp\Protocol\ProtocolInterface;

class Protobuf implements EventInterface
{
    private ProtocolInterface $packet;

    public function __construct(ProtocolInterface $packet)
    {
        $this->packet = $packet;
    }

    public function getMessage() :  string
    {
        return $this->packet->getMessage();
    }

    public function getAction() : int
    {
        return $this->packet->getAction();
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
