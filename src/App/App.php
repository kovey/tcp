<?php
/**
 *
 * @description 全局大对象
 *
 * @package     Tcp\App
 *
 * @time        2020-03-08 15:51:43
 *
 * @author      kovey
 */
namespace Kovey\Tcp\App;

use Kovey\Tcp\Handler\HandlerAbstract;
use Kovey\Library\Process\ProcessAbstract;
use Kovey\Connection\Pool\PoolInterface;
use Kovey\Library\Container\ContainerInterface;
use Kovey\Library\Config\Manager;
use Kovey\Tcp\App\Bootstrap\Autoload;
use Kovey\Tcp\Server\Server;
use Kovey\Library\Process\UserProcess;
use Kovey\Logger\Logger;
use Kovey\Logger\Monitor;
use Google\Protobuf\Internal\Message;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Library\Exception\KoveyException;
use Kovey\Connection\AppInterface;
use Kovey\Library\Util\Json;

class App implements AppInterface
{
    /**
     * @description App实例
     *
     * @var App
     */
    private static App $instance;

    /**
     * @description 服务器
     *
     * @var Kovey\Tcp\Server\Server
     */
    private Server $server;

    /**
     * @description 容器对象
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * @description 启动处理
     *
     * @var Kovey\Tcp\Bootstrap\Bootstrap
     */
    private $bootstrap;

    /**
     * @description 自定义启动
     *
     * @var mixed
     */
    private $customBootstrap;

    /**
     * @description 应用配置
     *
     * @var Array
     */
    private Array $config;

    /**
     * @description 用户自定义进程
     *
     * @var UserProcess
     */
    private UserProcess $userProcess;

    /**
     * @description 连接池
     *
     * @var Array
     */
    private Array $pools;

    /**
     * @description 自动加载
     *
     * @var Autoload
     */
    private Autoload $autoload;

    /**
     * @description 事件
     *
     * @var Array
     */
    private Array $events;

    /**
     * @description 全局变量
     *
     * @var Array
     */
    private Array $globals;

    /**
     * @description 其他服务的对象
     *
     * @var mixed
     */
    private $otherApps;

    /**
     * @description 构造函数
     *
     * @return App
     */
    private function __construct()
    {
        $this->pools = array();
        $this->events = array();
        $this->globals = array();
        $this->otherApps = array();
    }

    private function __clone()
    {}

    /**
     * @description 获取App 的实例
     *
     * @return App
     */
    public static function getInstance() : App
    {
        if (empty(self::$instance) || !self::$instance instanceof App) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @description 注册全局变量
     *
     * @param string $name
     *
     * @param mixed $val
     *
     * @return App
     */
    public function registerGlobal(string $name, $val) : App
    {
        $this->globals[$name] = $val;
        return $this;
    }

    /**
     * @description 获取全局变量
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getGlobal($name)
    {
        return $this->globals[$name] ?? null;
    }

    /**
     * @description 事件监听
     *
     * @param string $event
     *
     * @param callable $callable
     *
     * @return App
     */
    public function on(string $event, $callable) : App
    {
        if (!is_callable($callable)) {
            return $this;
        }

        $this->events[$event] = $callable;
        return $this;
    }

    /**
     * @description 设置配置
     *
     * @param Array $config
     *
     * @return App
     */
    public function setConfig(Array $config) : App
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @description 获取配置
     *
     * @return Array
     */
    public function getConfig() : Array
    {
        return $this->config;
    }

    /**
     * @description 启动处理
     *
     * @return App
     */
    public function bootstrap() : App
    {
        if (is_object($this->bootstrap)) {
            $btfuns = get_class_methods($this->bootstrap);
            foreach ($btfuns as $fun) {
                if (substr($fun, 0, 6) !== '__init') {
                    continue;
                }

                $this->bootstrap->$fun($this);
            }
        }

        if (is_object($this->customBootstrap)) {
            $funs = get_class_methods($this->customBootstrap);
            foreach ($funs as $fun) {
                if (substr($fun, 0, 6) !== '__init') {
                    continue;
                }

                $this->customBootstrap->$fun($this);
            }
        }

        return $this;
    }

    /**
     * @description handler业务
     *
     * @param string $packet
     *
     * @param int $fd
     *
     * @param string $ip
     *
     * @param string $traceId
     *
     * @return Array
     */
    public function handler(string $packet, int $action, $fd, string $ip, string $traceId) : Array
    {
        $begin = microtime(true);
        $reqTime = time();
        $result = array();
        $message = array();
        $monitorType = '';
        try {
            if (!isset($this->events['protobuf'])) {
                $monitorType = 'exception';
                if (isset($this->events['error'])) {
                    $result = call_user_func($this->events['error'], 'protobuf event is not register');
                }

                return $result;
            }

            $message = call_user_func($this->events['protobuf'], $packet, $action);
            if (empty($message['handler']) || empty($message['method'])) {
                $monitorType = 'exception';
                if (isset($this->events['error'])) {
                    $result = call_user_func($this->events['error'], 'unknown message');
                }

                return $result;
            }

            $class = $this->config['tcp']['handler'] . '\\' . ucfirst($message['handler']);
            $keywords = $this->container->getKeywords($class, $message['method']);
            $instance = $this->container->get($class, $traceId, $keywords['ext']);
            if (!$instance instanceof HandlerAbstract) {
                $monitorType = 'exception';
                if (isset($this->events['error'])) {
                    $result = call_user_func($this->events['error'], sprintf('%s is not extends HandlerAbstract', ucfirst($message['handler'])));
                }

                return $result;
            }

            $monitorType = 'success';
            if (!isset($this->events['run_handler'])) {
                $method = $message['method'];
                if ($keywords['openTransaction']) {
                    $keywords['database']->getConnection()->beginTransaction();
                    try {
                        $result = $instance->$method($message['message'], $fd, $ip);
                        $keywords['database']->getConnection()->commit();
                    } catch (\Throwable $e) {
                        $keywords['database']->getConnection()->rollBack();
                        throw $e;
                    }
                }  else {
                    $result = $instance->$method($message['message'], $fd, $ip);
                }
                return $result;
            }

            if ($keywords['openTransaction']) {
                $keywords['database']->getConnection()->beginTransaction();
                try {
                    $result = call_user_func($this->events['run_handler'], $instance, $message['method'], $message['message'], $fd, $ip);
                    $keywords['database']->getConnection()->commit();
                } catch (\Throwable $e) {
                    $keywords['database']->getConnection()->rollBack();
                    throw $e;
                }
            } else {
                $result = call_user_func($this->events['run_handler'], $instance, $message['method'], $message['message'], $fd, $ip);
            }
            return $result;
        } catch (CloseConnectionException $e) {
            throw $e;
        } catch (KoveyException $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $traceId);
            $monitorType = 'exception';
            if (isset($this->events['error'])) {
                $result = call_user_func($this->events['error'], 'exception');
            }
            return $result;
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $traceId);
            $monitorType = 'exception';
            if (isset($this->events['error'])) {
                $result = call_user_func($this->events['error'], 'throwable exception');
            }
            return $result;
        } finally {
            $this->sendToMonitor($reqTime, $begin, $ip, $monitorType, $traceId, $message, $result);
        }
    }

    /**
     * @description 监控
     *
     * @param int $reqTime
     *
     * @param float $begin
     *
     * @param string $ip
     *
     * @param string $type
     *
     * @param string $traceId
     *
     * @param Array $message
     *
     * @param Array $result
     *
     * @return null
     */
    private function sendToMonitor(int $reqTime, float $begin, string $ip, string $type, string $traceId, Array $message, Array $result)
    {
        $end = microtime(true);
        $params = '[]';
        if (!empty($message['message'])) {
            if (is_array($message['message'])) {
                $params = Json::encode($message['message']);
            } else if ($message['message'] instanceof Message) {
                $params = $message['message']->serializeToJsonString();
            } else {
                $params = $message['message'];
            }
        }
        if (!empty($result['message'])) {
            if ($result['message'] instanceof Message) {
                $result['message'] = $result['message']->serializeToJsonString();
            }
        }

        $data = array(
            'delay' => round(($end - $begin) * 1000, 2),
            'request_time' => $begin * 10000,
            'class' => $message['handler'] ?? '',
            'method' => $message['method'] ?? '',
            'type' => $type,
            'params' => $params,
            'response' => Json::encode($result),
            'ip' => $ip,
            'time' => $reqTime,
            'timestamp' => date('Y-m-d H:i:s', $reqTime),
            'minute' => date('YmdHi', $reqTime),
            'traceId' => $traceId
        );

        $this->monitor($data);
    }

    /**
     * @description 注册自动加载
     *
     * @param Autoload $autoload
     *
     * @return App
     */
    public function registerAutoload(Autoload $autoload) : App
    {
        $this->autoload = $autoload;
        return $this;
    }

    /**
     * @description 注册服务端
     *
     * @param Server $server
     *
     * @return App
     */
    public function registerServer(Server $server) : App
    {
        $this->server = $server;
        $this->server
            ->on('handler', array($this, 'handler'))
            ->on('pipeMessage', array($this, 'pipeMessage'))
            ->on('initPool', array($this, 'initPool'));

        return $this;
    }

    /**
     * @description 进程间通信
     *
     * @param string $path
     *
     * @param string $method
     *
     * @param Array $args
     *
     * @param string $traceId
     *
     * @return null
     */
    public function pipeMessage(string $path, string $method, Array $args, string $traceId)
    {
        if (!isset($this->events['pipeMessage'])) {
            return;
        }

        try {
            call_user_func($this->events['pipeMessage'], $path, $method, $args, $traceId);
        } catch (\Exception $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $traceId);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $traceId);
        }
    }

    /**
     * @description 初始化连接池
     *
     * @param Swoole\Server
     *
     * @return null
     */
    public function initPool(Server $serv)
    {
        try {
            foreach ($this->pools as $pool) {
                if (is_array($pool)) {
                    foreach ($pool as $p) {
                        $p->init();
                    }

                    if (count($p->getErrors()) > 0) {
                        Logger::writeErrorLog(__LINE__, __FILE__, implode(';', $p->getErrors()));
                    }
                    continue;
                }

                $pool->init();
                if (count($pool->getErrors()) > 0) {
                    Logger::writeErrorLog(__LINE__, __FILE__, implode(';', $pool->getErrors()));
                }
            }
        } catch (\Exception $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }
    }

    /**
     * @description 监控
     *
     * @param Array $data
     *
     * @return null
     */
    private function monitor(Array $data)
    {
        $this->userProcess->push('monitor', $data);
        Monitor::write($data);
    }

    /**
     * @description 注册容器
     *
     * @param ContainerInterface $container
     *
     * @return App
     */
    public function registerContainer(ContainerInterface $container) : App
    {
        $this->container = $container;
        return $this;
    }

    /**
     * @description 检测配置
     *
     * @return App
     *
     * @throws KoveyException
     */
    public function checkConfig() : App
    {
        $fields = array(
            'server' => array(
                'host', 'port', 'log_file', 'pid_file'
            ), 
            'logger' => array(
                'info', 'exception', 'error', 'warning'
            ), 
            'tcp' => array(
                'name', 'handler'
            )
        );

        foreach ($fields as $key => $field) {
            if (!isset($this->config[$key])) {
                throw new KoveyException("$key is not exists", 500);
            }

            foreach ($field as $fe) {
                if (!isset($this->config[$key][$fe])) {
                    throw new KoveyException("$fe of $key is not exists", 500);
                }
            }
        }

        return $this;
    }

    /**
     * @description 注册启动处理类
     *
     * @param mixed Bootstrap
     *
     * @return App
     */
    public function registerBootstrap($bootstrap) : App
    {
        $this->bootstrap = $bootstrap;
        return $this;
    }

    /**
     * @description 注册自定义的启动处理类
     *
     * @param mixed Bootstrap
     *
     * @return App
     */
    public function registerCustomBootstrap($bootstrap) : App
    {
        $this->customBootstrap = $bootstrap;
        return $this;
    }

    /**
     * @description 用户自定义进程管理
     *
     * @param UserProcess $userProcess
     *
     * @return App
     */
    public function registerUserProcess(UserProcess $userProcess) : App
    {
        $this->userProcess = $userProcess;
        return $this;
    }

    /**
     * @description 获取用户自定义进程管理
     *
     * @return UserProcess
     */
    public function getUserProcess() : UserProcess
    {
        return $this->userProcess;
    }

    /**
     * @description 注册自定义进程
     *
     * @param string $name
     *
     * @param ProcessAbstract $process
     *
     * @return App
     */
    public function registerProcess(string $name, ProcessAbstract $process) : App
    {
        if (!is_object($this->server)) {
            return $this;
        }

        $process->setServer($this->server->getServ());
        $this->userProcess->addProcess($name, $process);
        return $this;
    }

    /**
     * @description 注册本地加载路径
     *
     * @param string $path
     *
     * @return App
     */
    public function registerLocalLibPath(string $path) : App
    {
        if (!is_object($this->autoload)) {
            return $this;
        }

        $this->autoload->addLocalPath($path);
        return $this;
    }

    /**
     * @description 注册连接池
     *
     * @param string $name
     *
     * @param PoolInterface $pool
     *
     * @param int $partition = 0
     *
     * @return App
     */
    public function registerPool(string $name, PoolInterface $pool, int $partition = 0) : AppInterface
    {
        $this->pools[$name] ??= array();
        $this->pools[$name][$partition] = $pool;
        return $this;
    }

    /**
     * @description 获取连接池
     *
     * @param string $name
     *
     * @param int $partition
     *
     * @return ?PoolInterface
     */
    public function getPool(string $name, int $partition = 0) : ?PoolInterface
    {
        return $this->pools[$name][$partition] ?? null;
    }

    /**
     * @description 获取容器
     *
     * @return ContainerInterface
     */
    public function getContainer() : ContainerInterface
    {
        return $this->container;
    }

    /**
     * @description 运用启动
     *
     * @return null
     *
     * @throws KoveyException
     */
    public function run()
    {
        if (!is_object($this->server)) {
            throw new KoveyException('server not register');
        }

        $this->server->start();
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
        $this->server->send($packet, $action, $fd);
    }

    /**
     * @description 服务器事件注册
     *
     * @param string $name
     *
     * @param callable $callable
     *
     * @return App
     */
    public function serverOn(string $event, $callable) : App
    {
        $this->server->on($event, $callable);
        return $this;
    }

    /**
     * @description 注册其他服务的大对象
     *
     * @param string $name
     *
     * @param mixed $app
     *
     * @return App
     */
    public function registerOtherApp(string $name, $app) : App
    {
        $this->otherApps[$name] = $app;
        return $this;
    }

    /**
     * @description 获取其他服务的对象
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getOtherApp(string $name)
    {
        return $this->otherApps[$name] ?? null;
    }

    /**
     * @description 获取服务对象
     *
     * @return Server
     */
    public function getServer() : Server
    {
        return $this->server;
    }
}
