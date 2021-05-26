<?php
/**
 * @description tcp server
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
use Kovey\Tcp\Event;
use Kovey\App\Components\ServerAbstract;

class Server extends ServerAbstract
{
    /**
     * @description set config options
     *
     * @param string $key
     *
     * @param mixed $val
     *
     * @return Server
     */
    public function setOption(string $key, mixed $val) : Server
    {
        $this->serv->set(array($key => $val));
        return $this;
    }

    /**
     * @description 初始化服务
     *
     * @return Server
     */
    protected function initServer()
    {
        $this->serv = new \Swoole\Server($this->config['host'], $this->config['port']);
        $this->serv->set(array(
            'open_length_check' => true,
            'package_max_length' => $this->config['package_max_length'] ?? ProtocolInterface::MAX_LENGTH,
            'package_length_type' => $this->config['package_length_type'] ?? ProtocolInterface::PACK_TYPE,
            'package_length_offset' => $this->config['package_length_offset'] ?? ProtocolInterface::LENGTH_OFFSET,
            'package_body_offset' => $this->config['package_body_offset'] ?? ProtocolInterface::BODY_OFFSET,
            'enable_coroutine' => true,
            'worker_num' => $this->config['worker_num'],
            'max_coroutine' => $this->config['max_co'],
            'daemonize' => !$this->isRunDocker,
            'pid_file' => $this->config['pid_file'],
            'log_file' => $this->config['logger_dir'] . '/server/server.log',
            'event_object' => true,
            'log_rotation' => SWOOLE_LOG_ROTATION_DAILY,
            'log_date_format' => '%Y-%m-%d %H:%M:%S'
        ));

        $this->serv->on('connect', array($this, 'connect'));
        $this->serv->on('receive', array($this, 'receive'));
        $this->serv->on('close', array($this, 'close'));

        $this->initAllowEvents();
    }

    /**
     * @description init events support
     *
     * @return Server
     */
    protected function initAllowEvents()
    {
        $this->event->addSupportEvents(array(
            'handler' => Event\Handler::class,
            'unpack' => Event\Unpack::class,
            'pack' => Event\Pack::class,
            'connect' => Event\Connect::class,
            'close' => Event\Close::class, 
            'error' => Event\Error::class
        ));
    }

    /**
     * @description connect event
     *
     * @param Swoole\Server $serv
     *
     * @param Swoole\Server\Event $event
     *
     * @return void
     */
    public function connect(\Swoole\Server $serv, \Swoole\Server\Event $event) : void
    {
        $connect = new Connect();
        $connect->connect($this, $this->event, $serv, $event->fd);
    }

    /**
     * @description receive event
     *
     * @param Swoole\Server $serv
     *
     * @param mixed $data
     *
     * @return void
     */
    public function receive(\Swoole\Server $serv, \Swoole\Server\Event $event) : void
    {
        $receive = new Receive($event->data, $this->getClientIP($event->fd), $event->fd, $this->config['name']);
        $receive->begin()
                 ->run($this->event, $serv)
                 ->end($this)
                 ->monitor($this);
    }

    /**
     * @description send data to client
     *
     * @param mixed $packet
     *
     * @param int $fd
     *
     * @return bool
     */
    public function send($packet, int $action, int $fd) : bool
    {
        if (!$this->serv->exist($fd)) {
            throw new CloseConnectionException('connect is not exist');
        }

        $data = $this->event->dispatchWithReturn(new Event\Pack($packet, $action));
        if (!$data) {
            return false;
        }

        $len = strlen($data);
        if ($len <= ProtocolInterface::MAX_LENGTH) {
            return $this->serv->send($fd, $data);
        }

        $sendLen = 0;
        while ($sendLen < $len) {
            $this->serv->send($fd, substr($data, $sendLen, ProtocolInterface::MAX_LENGTH));
            $sendLen += ProtocolInterface::MAX_LENGTH;
        }

        return true;
    }

    /**
     * @description close connection
     *
     * @param Swoole\Server $serv
     *
     * @param Swoole\Server\Event $event
     *
     * @return void
     */
    public function close(\Swoole\Server $serv, \Swoole\Server\Event $event) : void
    {
        $close = new Close();
        $close->close($this, $this->event, $event->fd);
    }

    /**
     * @description get client info
     *
     * @param int $fd
     *
     * @return Array
     */
    public function getClientInfo(int $fd) : Array
    {
        return $this->serv->getClientInfo($fd);
    }
}
