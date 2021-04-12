<?php
/**
 * @description App global object
 *
 * @package     Tcp\App
 *
 * @time        2020-03-08 15:51:43
 *
 * @author      kovey
 */
namespace Kovey\Tcp\App;

use Kovey\Tcp\Server\Server;
use Kovey\Tcp\Event;
use Kovey\App\App as AA;
use Kovey\App\Components\ServerInterface;
use Kovey\Rpc\Work\Handler;
use Kovey\Tcp\App\Router\RouterInterface;
use Kovey\Tcp\App\Router\RoutersInterface;
use Kovey\Library\Exception\KoveyException;

class App extends AA
{
    /**
     * @description App instance
     *
     * @var App
     */
    private static ?App $instance = null;

    /**
     * @description other app object
     *
     * @var Array
     */
    private Array $otherApps;

    /**
     * @description get app instance
     *
     * @return App
     */
    public static function getInstance(Array $config = array()) : App
    {
        if (empty(self::$instance) || !self::$instance instanceof App) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    protected function init() : App
    {
        $this->bootstrap->add(new BaseInit());
        $this->event->addSupportEvents(array(
            'run_handler' => Event\RunHandler::class,
            'error' => Event\Error::class,
            'encrypt' => Event\Encrypt::class,
        ));

        return $this;
    }

    protected function initWork() : AppBase
    {
        $this->work = new Handler($this->config['rpc']['handler']);
        $this->work->setEventManager($this->event);
        return $this;
    }

    /**
     * @description register server
     *
     * @param Server $server
     *
     * @return App
     */
    public function registerServer(Server $server) : App
    {
        $this->server = $server;
        $this->server
            ->on('handler', array($this->work, 'run'))
            ->on('console', array($this, 'console'))
            ->on('initPool', array($this->pools, 'initPool'))
            ->on('monitor', array($this, 'monitor'));

        return $this;
    }

    /**
     * @description check config
     *
     * @return App
     *
     * @throws KoveyException
     */
    public function checkConfig() : App
    {
        $fields = array(
            'server' => array(
                'host', 'port', 'logger_dir', 'pid_file'
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
     * @description send data to client
     *
     * @param mixed $packet
     *
     * @param int $fd
     *
     * @return bool
     */
    public function send(mixed $packet, int $action, int $fd) : bool
    {
        return $this->server->send($packet, $action, $fd);
    }

    /**
     * @description event listen on server
     *
     * @param string $name
     *
     * @param callable $callable
     *
     * @return App
     */
    public function serverOn(string $event, callable | Array $callable) : App
    {
        $this->server->on($event, $callable);
        return $this;
    }

    /**
     * @description register other app
     *
     * @param string $name
     *
     * @param mixed $app
     *
     * @return App
     */
    public function registerOtherApp(string $name, mixed $app) : App
    {
        $this->otherApps[$name] = $app;
        return $this;
    }

    /**
     * @description get other app
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getOtherApp(string $name) : mixed
    {
        return $this->otherApps[$name] ?? null;
    }
    
    /**
     * @description register router
     *
     * @param string | int $code
     *
     * @param RouterInterface $router
     *
     * @return App
     */
    public function registerRouter(string | int $code, RouterInterface $router) : App
    {
        $this->work->addRouter($code, $router);
        return $this;
    }

    /**
     * @description register routers
     *
     * @param RoutersInterface $routers
     *
     * @return Application
     */
    public function registerRouters(RoutersInterface $routers) : Application
    {
        $this->work->setRouters($routers);
        return $this;
    }
}
