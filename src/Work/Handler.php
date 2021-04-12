<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-04-12 10:14:34
 *
 */
namespace Kovey\Tcp\Work;

use Kovey\App\Components\Work;
use Kovey\Event\EventInterface;
use Kovey\Rpc\Handler\HandlerAbstract;
use Kovey\Connection\ManualCollectInterface;
use Google\Protobuf\Internal\Message;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Tcp\App\Router\RouterInterface;
use Kovey\Tcp\App\Router\RoutersInterface;

class Handler extends Work
{
    private string $handler;

    private RoutersInterface $routers;

    public function __construct(string $handler)
    {
        $this->handler = $handler;
    }

    public function setRouters(RoutersInterface $routers) : Handler
    {
        $this->routers = $routers;
        return $this;
    }

    public function addRouter(int | string $code, RouterInterface $router) : Handler
    {
        $this->routers->addRouter($code, $router);
        return $this;
    }

    public function run(EventInterface $event) : Array
    {
        $router = $this->routers->getRouter($event->getPacket()->getAction());
        if (empty($router)) {
            throw CloseConnectionException('protocol number is error', 1000);
        }
        $baseClass = $router->getProtobufBase();
        $class = $router->getProtobuf();
        $protobuf = new $class();

        if (!empty($baseClass)) {
            $base = new $baseClass();
            $base->mergeFromString($event->getPacket()->getMessage());
            if ($this->event->listened('encrypt')) {
                $message = $this->event->dispatchWithReturn(new Event\Encrypt($base));
                $protobuf->mergeFromString($message);
            }
        } else {
            $protobuf->mergeFromString($event->getPacket()->getMessage());
        }

        $class = $this->handler . '\\' . $event->getHandler();
        $keywords = $this->container->getKeywords($class, $event->getMethod());
        try {
            $instance = $this->container->get($class, $event->getTraceId(), $keywords['ext']);
            if (!$instance instanceof HandlerAbstract) {
                throw new CloseConnectionException("$class is not implements HandlerAbstract");
            }

            $instance->setClientIp($event->getIp());

            if ($keywords['openTransaction']) {
                $instance->database->beginTransaction();
                try {
                    $result = $this->triggerHandler($instance, $event->getMethod(), $protobuf, $event->getFd());
                    $instance->database->commit();
                } catch (\Throwable $e) {
                    $instance->database->rollBack();
                    throw $e;
                }
            } else {
                $result = $this->triggerHandler($instance, $event->getMethod(), $protobuf, $event->getFd());
            }

            if (empty($result)) {
                $result = array();
            }

            $result['class'] = $event->getHandler();
            $result['method'] = $event->getMethod();
            $result['params'] = $protobuf->serializeToJsonString();

            return $result;
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            foreach ($keywords as $value) {
                if (!$value instanceof ManualCollectInterface) {
                    $value->collect();
                }
            }
        }
    }

    private function triggerHandler(HandlerAbstract $instance, string $method, Message $message, int $fd) : Array
    {
        if ($this->event->listened('run_handler')) {
            return $this->dispatch->dispatchWithReturn(new Event\RunHandler($instance, $method, $message, $fd));
        }

        return call_user_func(array($instance, $method), $message, $fd);
    }
}
