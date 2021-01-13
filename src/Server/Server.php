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
use Kovey\Tcp\Event;
use Kovey\Event\Dispatch;
use Kovey\Event\Listener\Listener;
use Kovey\Event\Listener\ListenerProvider;

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
    private Array $onEvents;

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

    private Dispatch $dispatch;

    private ListenerProvider $provider;

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
        $this->onEvents = array();
        $this->provider = new ListenerProvider();
        $this->dispatch = new Dispatch($this->provider);
        $this->initAllowEvents()
            ->initLog()
            ->initServer()
            ->initCallback();

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
            'log_file' => $this->conf['logger_dir'] . '/server/server.log',
            'event_object' => true,
            'log_rotation' => SWOOLE_LOG_ROTATION_DAILY,
            'log_date_format' => '%Y-%m-%d %H:%M:%S'
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
        $logDir = $this->conf['logger_dir'] . '/server';
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
            'handler' => Event\Handler::class,
            'pipeMessage' => Event\PipeMessage::class,
            'initPool' => Event\InitPool::class,
            'monitor' => Event\Monitor::class,
            'unpack' => Event\Unpack::class,
            'pack' => Event\Pack::class,
            'connect' => Event\Connect::class,
            'close' => Event\Close::class, 
            'error' => Event\Error::class
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

        try {
            $this->dispatch->dispatch(new Event\InitPool($this));
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
    public function on(string $event, callable | Array $call) : PortInterface
    {
        if (!isset($this->allowEvents[$event])) {
            throw new KoveyException('event: "' . $event . '" is not allow');
        }

        if (!is_callable($call)) {
            throw new KoveyException('callback is not callable');
        }

        $this->onEvents[$event] = $event;
        $listener = new Listener();
        $listener->addEvent($this->allowEvents[$event], $call);
        $this->provider->addListener($listener);

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
    public function pipeMessage(\Swoole\Server $serv, \Swoole\Server\PipeMessage $message)
    {
        try {
            $this->dispatch->dispatch(new Event\PipeMessage($message->data));
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $message->data['t'] ?? '');
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
    public function connect(\Swoole\Server $serv, \Swoole\Server\Event $event) : Server
    {
        try {
            $this->dispatch->dispatch(new Event\Connect($this, $event->fd));
        } catch (CloseConnectionException $e) {
           $this->serv->close($event->fd);
        } catch (BusiException $e) {
            Logger::writeBusiException(__LINE__, __FILE__, $e);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            $this->serv->close($event->fd);
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
    public function receive(\Swoole\Server $serv, \Swoole\Server\Event $event)
    {
        try {
            $proto = $this->dispatch->dispatchWithReturn(new Event\Unpack($event->data));
            if (!$proto instanceof ProtocolInterface) {
                Logger::writeErrorLog(__LINE__, __FILE__, 'data is error');
                $serv->close($event->fd);
                return;
            }

            $this->handler($proto, $event->fd);
        } catch (CloseConnectionException $e) {
            $serv->close($event->fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        } catch (\Throwable $e) {
            $serv->close($event->fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }
    }

    /**
     * @description Handler 处理
     *
     * @param ProtocolInterface $packet
     *
     * @param int $fd
     *
     * @return null
     */
    private function handler(ProtocolInterface $packet, int $fd)
    {
        $begin = microtime(true);
        $reqTime = time();
        $result = null;
        $traceId = hash('sha256', uniqid($fd, true) . random_int(1000000, 9999999));
        $trace = '';
        $err = '';

        try {
            $result = $this->dispatch->dispatchWithReturn(new Event\Handler($packet, $fd, $this->serv->getClientIP($fd), $traceId));
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
            if (!isset($this->onEvents['error'])) {
                return;
            }

            $result = $this->dispatch->dispatchWithReturn(new Event\Error($e));
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
        try {
            $this->dispatch->dispatch(new Event\Monitor(array(
                'delay' => round(($end - $begin) * 1000, 2),
                'request_time' => $begin * 10000,
                'action' => $packet->getAction(),
                'packet' => base64_encode($packet->getMessage()),
                'ip' => $this->getClientIP($fd),
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
            )));
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

        $data = $this->dispatch->dispatchWithReturn(new Event\Pack($packet, $action));
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
    public function close(\Swoole\Server $serv, \Swoole\Server\Event $event)
    {
        try {
            $this->dispatch->dispatch(new Event\Close($this, $event->fd));
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
    public function getClientIP($fd) : string
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
