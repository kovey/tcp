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
use Google\Protobuf\Internal\Message;

class Encrypt implements EventInterface
{
    private Message $packet;

    public function __construct(Message $packet)
    {
        $this->packet = $packet;
    }

    public function getMessage() :  Message
    {
        return $this->packet;
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
