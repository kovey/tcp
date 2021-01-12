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
use Kovey\Tcp\Server\Server;

class Connect implements EventInterface
{
    private Server $server;

    private int $fd;

    public function __construct(Server $server, int $fd)
    {
        $this->server = $server;
        $this->fd = $fd;
    }

    public function getServer() : Server
    {
        return $this->server;
    }

    public function getFd() : int
    {
        return $this->fd;
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
