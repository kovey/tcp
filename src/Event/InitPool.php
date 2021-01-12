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

class InitPool implements EventInterface
{
    private Server $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function getServer() : Server
    {
        return $this->server;
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
