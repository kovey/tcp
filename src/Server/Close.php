<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-04-12 18:01:05
 *
 */
namespace Kovey\Tcp\Server;

use Kovey\Tcp\Event;
use Kovey\Logger\Logger;
use Kovey\Event\EventManager;

class Close
{
    public function close(Server $server, EventManager $event, int $fd) : Close
    {
        try {
            $event->dispatch(new Event\Close($server, $fd));
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }

        return $this;
    }
}
