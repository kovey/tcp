<?php
/**
 * @description 短连接服务端
 *
 * @package Server
 *
 * @author kovey
 *
 * @time 2019-11-13 14:43:19
 *
 */
namespace Kovey\Tcp\Server;

use Kovey\Tcp\Protocol\ProtocolInterface;
use Kovey\Library\Exception\BusiException;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Library\Exception\KoveyException;
use Kovey\Logger\Logger;
use Kovey\Library\Server\PortInterface;

class Server implements PortInterface
{
    /**
     * @description 服务器
     *
     * @var Swoole\Server
     */
    private \Swoole\Server $serv;

    /**
     * @description 配置
     *
     * @var Array
     */
    private Array $conf;

    /**
     * @description 事件
     *
     * @var Array
     */
    private Array $events;

    /**
     * @description 允许的事件
     *
     * @var Array
     */
    private Array $allowEvents;

    /**
     * @description 是否运行在docker中
     *
     * @var bool
     */
    private bool $isRunDocker;

    /**
     * @description 构造函数
     *
     * @param Array $conf
     *
     * @return Server
     */
    public function __construct(Array $conf)
    {
        $this->conf = $conf;
        $this->isRunDocker = ($this->conf['run_docker'] ?? 'Off') === 'On';
        $this->events = array();
        $this->initAllowEvents()
            ->initServer()
            ->initCallback()
            ->initLog();

    }

    /**
     * @description 设置配置选项
     *
     * @param string $key
     *
     * @param mixed $val
     *
     * @return Server
     */
    public function setOption(string $key, $val) : Server
    {
        $this->serv->set(array($key => $val));
        return $this;
    }

    /**
     * @description 初始化服务
     *
     * @return Server
     */
    private function initServer() : Server
    {
        $this->serv = new \Swoole\Server($this->conf['host'], $this->conf['port']);
        $this->serv->set(array(
            'open_length_check' => true,
            'package_max_length' => ProtocolInterface::MAX_LENGTH,
            'package_length_type' => ProtocolInterface::PACK_TYPE,
            'package_length_offset' => ProtocolInterface::LENGTH_OFFSET,
            'package_body_offset' => ProtocolInterface::BODY_OFFSET,
            'enable_coroutine' => true,
            'worker_num' => $this->conf['worker_num'],
            'max_coroutine' => $this->conf['max_co'],
            'daemonize' => !$this->isRunDocker,
            'pid_file' => $this->conf['pid_file'],
            'log_file' => $this->conf['log_file'],
        ));

        $this->serv->on('connect', array($this, 'connect'));
        $this->serv->on('receive', array($this, 'receive'));
        $this->serv->on('close', array($this, 'close'));
        return $this;
    }

    /**
     * @description 初始化LOG
     *
     * @return Server
     */
    private function initLog() : Server
    {
        $logDir = dirname($this->conf['log_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $pidDir = dirname($this->conf['pid_file']);
        if (!is_dir($pidDir)) {
            mkdir($pidDir, 0777, true);
        }

        return $this;
    }

    /**
     * @description 初始化允许的事件
     *
     * @return Server
     */
    private function initAllowEvents() : Server
    {
        $this->allowEvents = array(
            'handler' => 1,
            'pipeMessage' => 1,
            'initPool' => 1,
            'monitor' => 1,
            'run_action' => 1,
            'unpack' => 1,
            'pack' => 1,
            'connect' => 1,
            'close' => 1, 
            'error' => 1
        );

        return $this;
    }

    /**
     * @description 初始化回调
     *
     * @return Server
     */
    private function initCallback() : Server
    {
        $this->serv->on('pipeMessage', array($this, 'pipeMessage'));
        $this->serv->on('workerStart', array($this, 'workerStart'));
        $this->serv->on('managerStart', array($this, 'managerStart'));
        return $this;
    }

    /**
     * @description manager 启动回调
     *
     * @param Swoole\Server $serv
     *
     * @return null
     */
    public function managerStart(\Swoole\Server $serv)
    {
        ko_change_process_name($this->conf['name'] . ' master');
    }

    /**
     * @description worker 启动回调
     *
     * @param Swoole\Server $serv
     *
     * @param int $workerId
     *
     * @return null
     */
    public function workerStart(\Swoole\Server $serv, $workerId)
    {
        ko_change_process_name($this->conf['name'] . ' worker');

        if (!isset($this->events['initPool'])) {
            return;
        }

        try {
            call_user_func($this->events['initPool'], $this);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }
    }

    /**
     * @description 添加事件
     *
     * @param string $events
     *
     * @param callable $cal
     *
     * @return PortInterface
     *
     * @throws Exception
     */
    public function on(string $event, $call) : PortInterface
    {
        if (!isset($this->allowEvents[$event])) {
            throw new \Exception('event: "' . $event . '" is not allow');
        }

        if (!is_callable($call)) {
            throw new \Exception('callback is not callable');
        }

        $this->events[$event] = $call;
        return $this;
    }

    /**
     * @description 管道事件回调
     *
     * @param Swoole\Server $serv
     *
     * @param int $workerId
     *
     * @param mixed $data
     *
     * @return null
     */
    public function pipeMessage(\Swoole\Server $serv, int $workerId, $data)
    {
        try {
            if (!isset($this->events['pipeMessage'])) {
                return;
            }

            call_user_func($this->events['pipeMessage'], $data['p'] ?? '', $data['m'] ?? '', $data['a'] ?? array(), $data['t'] ?? '');
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $data['t'] ?? '');
        }
    }

    /**
     * @description 链接回调
     *
     * @param Swoole\Server $serv
     *
     * @param int $fd
     *
     * @return Server
     */
    public function connect($serv, $fd) : Server
    {
        if (!isset($this->events['connect'])) {
            return $this;
        }

        try {
            call_user_func($this->events['connect'], $this, $fd);
        } catch (CloseConnectionException $e) {
           $this->serv->close($fd);
        } catch (BusiException $e) {
            Logger::writeBusiException(__LINE__, __FILE__, $e);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            $this->serv->close($fd);
        }
        return $this;
    }

    /**
     * @description 接收回调
     *
     * @param Swoole\Server $serv
     *
     * @param int $fd
     *
     * @param int $reactor_id
     *
     * @param mixed $data
     *
     * @return null
     */
    public function receive(\Swoole\Server $serv, $fd, $reactor_id, $data)
    {
        if (!isset($this->events['unpack'])) {
            Logger::writeErrorLog(__LINE__, __FILE__, 'unpack events is null');
            return;
        }

        try {
            $proto = call_user_func($this->events['unpack'], $data);
            if (!$proto instanceof ProtocolInterface) {
                Logger::writeErrorLog(__LINE__, __FILE__, 'data is error');
                $serv->close($fd);
                return;
            }

            $this->handler($proto, $fd);
        } catch (CloseConnectionException $e) {
            $serv->close($fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        } catch (\Throwable $e) {
            $serv->close($fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }
    }

    /**
     * @description Hander 处理
     *
     * @param ProtocolInterface $packet
     *
     * @param int $fd
     *
     * @return null
     */
    private function handler(ProtocolInterface $packet, $fd)
    {
        $begin = microtime(true);
        $reqTime = time();
        $result = null;
        $traceId = hash('sha256', uniqid($fd, true) . random_int(1000000, 9999999));
        $trace = '';
        $err = '';

        try {
            if (!isset($this->events['handler'])) {
                return;
            }

            $result = call_user_func($this->events['handler'], $packet->getMessage(), $packet->getAction(), $fd, $this->serv->getClientInfo($fd)['remote_ip'], $traceId);
            if (empty($result) || !isset($result['message']) || !isset($result['action'])) {
                return;
            }

            $this->send($result['message'], $result['action'], $fd);
        } catch (CloseConnectionException $e) {
            $this->serv->close($fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $traceId);
        } catch (BusiException | KoveyException $e) {
            $trace = $e->getTraceAsString();
            $err = $e->getMessage();
            Logger::writeBusiException(__LINE__, __FILE__, $e, $traceId);
            if (!isset($this->events['error'])) {
                return;
            }
            $result = call_user_func($this->events['error'], $e);
            if (empty($result) || !isset($result['message']) || !isset($result['action'])) {
                return;
            }

            $this->send($result['message'], $result['action'], $fd);
        } catch (\Throwable $e) {
            $trace = $e->getTraceAsString();
            $err = $e->getMessage();
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $traceId);
        } finally {
            if (isset($this->conf['monitor_open']) && $this->conf['monitor_open'] === 'Off') {
                return;
            }

            $end = microtime(true);
            $this->monitor($begin, $end, $packet, $reqTime, $fd, $traceId, $trace, $err);
        }
    }

    /**
     * @description 监控
     *
     * @param float $begin
     *
     * @param float $end
     *
     * @param ProtocolInterface $packet
     *
     * @param int $reqTime
     *
     * @param Array $result
     *
     * @param int $fd
     *
     * @param string $traceId
     *
     * @return null
     */
    private function monitor(float $begin, float $end, ProtocolInterface $packet, int $reqTime, $fd, string $traceId, string $trace, string $err)
    {
        if (!isset($this->events['monitor'])) {
            return;
        }

        try {
            call_user_func($this->events['monitor'], array(
                'delay' => round(($end - $begin) * 1000, 2),
                'request_time' => $begin * 10000,
                'action' => $packet->getAction(),
                'packet' => base64_encode($packet->getMessage()),
                'ip' => $this->getRemoteIp($fd),
                'time' => $reqTime,
                'timestamp' => date('Y-m-d H:i:s', $reqTime),
                'minute' => date('YmdHi', $reqTime),
                'class' => '',
                'method' => '',
                'service' => $this->conf['name'],
                'service_type' => 'tcp',
                'from' => $this->conf['name'],
                'traceId' => $traceId,
                'end' => $end * 10000,
                'trace' => $trace,
                'err' => $err
            ));
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $traceId);
        }
    }

    /**
     * @description 发送数据
     *
     * @param mixed $packet
     *
     * @param int $fd
     *
     * @return null
     */
    public function send($packet, int $action, $fd)
    {
        if (!$this->serv->exist($fd)) {
            throw new CloseConnectionException('connect is not exist');
        }

        if (!isset($this->events['pack'])) {
            return false;
        }

        $data = call_user_func($this->events['pack'], $packet, $action);
        if (!$data) {
            return false;
        }

        $len = strlen($data);
        if ($len <= self::PACKET_MAX_LENGTH) {
            return $this->serv->send($fd, $data);
        }

        $sendLen = 0;
        while ($sendLen < $len) {
            $this->serv->send($fd, substr($data, $sendLen, self::PACKET_MAX_LENGTH));
            $sendLen += self::PACKET_MAX_LENGTH;
        }

        return true;
    }

    /**
     * @description 关闭链接
     *
     * @param Swoole\Server $serv
     *
     * @param int $fd
     *
     * @return null
     */
    public function close(\Swoole\Server $serv, $fd)
    {
        if (!isset($this->events['close'])) {
            return;
        }

        try {
            call_user_func($this->events['close'], $this, $fd);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }
    }

    /**
     * @description 启动服务
     *
     * @return null
     */
    public function start()
    {
        $this->serv->start();
    }

    /**
     * @description 获取底层服务
     *
     * @return Swoole\Server
     */
    public function getServ() : \Swoole\Server
    {
        return $this->serv;
    }

    /**
     * @description 获取远程ID
     *
     * @param int $fd
     *
     * @return string
     */
    public function getRemoteIp($fd) : string
    {
        $info = $this->serv->getClientInfo($fd);
        if (empty($info)) {
            return '';
        }

        return $info['remote_ip'] ?? '';
    }

    /**
     * @description 获取客户端信息
     *
     * @param int $fd
     *
     * @return Array
     */
    public function getClientInfo($fd) : Array
    {
        return $this->serv->getClientInfo($fd);
    }
}
