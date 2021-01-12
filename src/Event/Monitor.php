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

class Monitor implements EventInterface
{
    private Array $data;

    public function __construct(Array $data)
    {
        $this->data = $data;
    }

    public function getData() : Array
    {
        return $this->data;
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
