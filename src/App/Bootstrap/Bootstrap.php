<?php
/**
 *
 * @description 整个运用启动前的初始化
 *
 * @package     App\Bootstrap
 *
 * @time        Tue Sep 24 09:00:10 2019
 *
 * @author      kovey
 */
namespace Kovey\Tcp\App\Bootstrap;

use Kovey\Process\Process;
use Kovey\Library\Config\Manager;
use Kovey\Logger\Logger;
use Kovey\Logger\Monitor;
use Kovey\Logger\Db;
use Kovey\Container\Container;
use Kovey\Tcp\App\App;
use Kovey\Tcp\Server\Server;
use Kovey\Process\UserProcess;

class Bootstrap
{
    /**
     * @description 初始化日志
     *
     * @param App $app
     *
     * @return null
     */
    public function __initLogger(App $app)
    {
        ko_change_process_name(Manager::get('server.tcp.name') . ' tcp root');
        Logger::setLogPath(Manager::get('server.server.logger_dir'));
        Db::setLogDir(Manager::get('server.server.logger_dir'));
        Monitor::setLogDir(Manager::get('server.server.logger_dir'));
    }

    /**
     * @description 初始化APP
     *
     * @param App $app
     *
     * @return null
     */
    public function __initApp(App $app)
    {
        $app->registerServer(new Server($app->getConfig()['server']))
            ->registerContainer(new Container())
            ->registerUserProcess(new UserProcess($app->getConfig()['server']['worker_num']));
    }

    /**
     * @description 初始化自定义进程
     *
     * @param App $app
     *
     * @return null
     */
    public function __initProcess(App $app)
    {
        $app->registerProcess('kovey_config', (new Process\Config())->setProcessName(Manager::get('server.tcp.name') . ' config'));
    }

    /**
     * @description 初始化自定义的Bootsrap
     *
     * @param App $app
     *
     * @return null
     */
    public function __initCustomBoot(App $app)
    {
        $bootstrap = $app->getConfig()['tcp']['boot'] ?? 'application/Bootstrap.php';
        $file = APPLICATION_PATH . '/' . $bootstrap;
        if (!is_file($file)) {
            return;
        }

        require_once $file;

        $app->registerCustomBootstrap(new \Bootstrap());
    }

    public function __initParseInject(App $app)
    {
        $app->registerLocalLibPath(APPLICATION_PATH . '/application');

        $handler = $app->getConfig()['tcp']['handler'];
        $app->getContainer()->parse(APPLICATION_PATH . '/application/' . $handler, $handler);
    }
}
