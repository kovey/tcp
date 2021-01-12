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

class PipeMessage implements EventInterface
{
    private Array $data;

    public function __construct(Array $data)
    {
        $this->data = $data;
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

    public function getPath() : string
    {
        return $this->data['p'] ?? '';
    }

    public function getMethod() : string
    {
        return $this->data['m'] ?? '';
    }

    public function getArgs() : Array
    {
        return $this->data['a'] ?? array();
    }

    public function getTraceId() : string
    {
        return $this->data['t'] ?? '';
    }
}
