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
use Kovey\Tcp\Handler\HandlerAbstract;
use Kovey\Connection\ManualCollectInterface;
use Google\Protobuf\Internal\Message;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Tcp\App\Router\RouterInterface;
use Kovey\Tcp\App\Router\RoutersInterface;
use Kovey\Tcp\Event;
use Kovey\Logger\Logger;

class Handler extends Work
{
    private RoutersInterface $routers;

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
            throw new CloseConnectionException('protocol number is error', 1000);
        }
        $baseClass = $router->getProtobufBase();
        $class = $router->getProtobuf();
        $protobuf = new $class();

        $base = null;
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

        $class = $router->getHandler();
        $keywords = $this->container->getKeywords($class, $router->getMethod());
        try {
            $instance = $this->container->get($class, $event->getTraceId(), $event->getSpanId(), $keywords['ext']);
            if (!$instance instanceof HandlerAbstract) {
                throw new CloseConnectionException("$class is not implements HandlerAbstract");
            }

            $instance->setClientIp($event->getIp());

            if ($keywords['openTransaction']) {
                $instance->database->beginTransaction();
                try {
                    $result = $this->triggerHandler($instance, $router->getMethod(), $protobuf, $event->getFd(), $base);
                    $instance->database->commit();
                } catch (\Throwable $e) {
                    $instance->database->rollBack();
                    Logger::writeErrorLog(__LINE__, __FILE__, array(
                        'class' => $router->getHandler(),
                        'method' => $router->getMethod(),
                        'params' => $protobuf->serializeToJsonString(),
                        'error' => $e->getMessage(),
                        'base' => empty($base) ? '' : $base->serializeToJsonString()
                    ));
                    throw $e;
                }
            } else {
                $result = $this->triggerHandler($instance, $router->getMethod(), $protobuf, $event->getFd(), $base);
            }

            if (empty($result)) {
                $result = array();
            }

            $result['class'] = $router->getHandler();
            $result['method'] = $router->getMethod();
            $result['params'] = $protobuf->serializeToJsonString();
            $result['base'] = empty($base) ? '' : $base->serializeToJsonString();

            return $result;
        } catch (\Throwable $e) {
            Logger::writeErrorLog(__LINE__, __FILE__, array(
                'class' => $router->getHandler(),
                'method' => $router->getMethod(),
                'params' => $protobuf->serializeToJsonString(),
                'error' => $e->getMessage(),
                'base' => empty($base) ? '' : $base->serializeToJsonString()
            ));
            throw $e;
        } finally {
            foreach ($keywords as $value) {
                if (!$value instanceof ManualCollectInterface) {
                    continue;
                }

                $value->collect();
            }
        }
    }

    private function triggerHandler(HandlerAbstract $instance, string $method, Message $message, int $fd, ?Message $base) : Array
    {
        if ($this->event->listened('run_handler')) {
            return $this->event->dispatchWithReturn(new Event\RunHandler($instance, $method, $message, $fd, $base));
        }

        return call_user_func(array($instance, $method), $message, $fd, $base);
    }
}
