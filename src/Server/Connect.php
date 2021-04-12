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

use Kovey\Library\Exception\BusiException;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Library\Exception\KoveyException;
use Kovey\Tcp\Event;
use Kovey\Logger\Logger;
use Kovey\Event\EventManager;

class Connect
{
    public function connect(Server $server, EventManager $event, \Swoole\Server $serv, int $fd) : Connect
    {
        try {
            $event->dispatch(new Event\Connect($server, $fd));
        } catch (CloseConnectionException $e) {
           $serv->close($fd);
        } catch (BusiException $e) {
            $serv->close($fd);
            Logger::writeBusiException(__LINE__, __FILE__, $e);
        } catch (\Throwable $e) {
            $serv->close($fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }
    }
}
