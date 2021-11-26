<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-04-12 16:32:34
 *
 */
namespace Kovey\Tcp\Server;

use Kovey\Library\Exception\BusiException;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Library\Exception\KoveyException;
use Kovey\Tcp\Event;
use Kovey\Logger\Logger;
use Kovey\Tcp\Protocol\ProtocolInterface;
use Kovey\Event\EventManager;
use Google\Protobuf\Internal\Message;

class Receive
{
    private float $begin;

    private int $reqTime;

    private Array $result;

    private string $traceId;

    private string $trace;

    private string $err;

    private string $ip;

    private int $fd;

    private string $packet;

    private string $service;

    private int $action;

    private string $type;

    private string $spanId;

    public function __construct(string $packet, string $ip, int $fd, string $service)
    {
        $this->packet = $packet;
        $this->ip = $ip;
        $this->fd = $fd;
        $this->service = $service;
    }

    public function begin() : Receive
    {
        $this->begin = microtime(true);
        $this->reqTime = time();
        $this->result = array();
        $this->traceId = hash('sha256', uniqid($this->fd, true) . random_int(1000000, 9999999));
        $this->trace = '';
        $this->err = '';
        $this->type = 'success';
        $this->action = 0;
        $this->spanId = md5($this->fd . microtime(true));

        return $this;
    }

    public function run(EventManager $event, \Swoole\Server $serv) : Receive
    {
        try {
            $proto = $event->dispatchWithReturn(new Event\Unpack($this->packet));
            if (!$proto instanceof ProtocolInterface) {
                Logger::writeErrorLog(__LINE__, __FILE__, 'data is error');
                $serv->close($event->fd);
                return $this;
            }

            $this->action = $proto->getAction();
            $this->result = $event->dispatchWithReturn(new Event\Handler($proto, $this->fd, $this->ip, $this->traceId, $this->spanId));
        } catch (CloseConnectionException $e) {
            $serv->close($this->fd);
            $this->type = 'connection_close_exception';
            $this->trace = $e->getTraceAsString();
            $this->err = $e->getMessage();
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->traceId);
        } catch (BusiException | KoveyException $e) {
            $this->trace = $e->getTraceAsString();
            $this->err = $e->getMessage();
            $this->type = 'busi_exception';
            Logger::writeBusiException(__LINE__, __FILE__, $e, $this->traceId);
            if (!$event->listened('error')) {
                return $this;
            }

            $this->result = $event->dispatchWithReturn(new Event\Error($e));
        } catch (\Throwable $e) {
            $this->trace = $e->getTraceAsString();
            $this->err = $e->getMessage();
            $this->type = 'error_exception';
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->traceId);
        }

        return $this;
    }

    public function end(Server $server) : Receive
    {
        if (empty($this->result) || !isset($this->result['message']) || !isset($this->result['action'])) {
            return $this;
        }

        try {
            $server->send($this->result['message'], $this->result['action'], $this->fd);
        } catch (CloseConnectionException $e) {
            $server->getServ()->close($this->fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->traceId);
        }

        return $this;
    }

    public function monitor(Server $server) : Receive
    {
        if (!empty($this->result['message'])) {
            if ($this->result['message'] instanceof Message) {
                $this->result['message'] = $this->result['message']->serializeToJsonString();
            }
        }

        $end = microtime(true);
        $server->monitor(array(
            'delay' => round(($end - $this->begin) * 1000, 2),
            'request_time' => $this->begin * 10000,
            'action' => $this->action,
            'res_action' => $this->result['action'] ?? 0,
            'class' => $this->result['class'] ?? '',
            'method' => $this->result['method'] ?? '',
            'service' => $this->service,
            'service_type' => 'tcp',
            'packet' => base64_encode($this->packet),
            'type' => $this->type,
            'params' => $this->result['params'] ?? '',
            'response' => $this->result['message'] ?? '',
            'base' => $this->result['base'] ?? '',
            'ip' => $this->ip,
            'time' => $this->reqTime,
            'timestamp' => date('Y-m-d H:i:s', $this->reqTime),
            'minute' => date('YmdHi', $this->reqTime),
            'traceId' => $this->traceId,
            'from' => $this->service,
            'end' => $end * 10000,
            'trace' => $this->trace,
            'err' => $this->err,
            'parentId' => 'root',
            'spanId' => $this->spanId,
            'fd' => $this->fd
        ), $this->traceId);

        return $this;
    }
}
