<?php
/**
 *
 * @description bootstrap
 *
 * @package     App\Bootstrap
 *
 * @time        Tue Sep 24 09:00:10 2019
 *
 * @author      kovey
 */
namespace Kovey\Tcp\App\Bootstrap;

use Kovey\Tcp\App\App;
use Kovey\Tcp\Server\Server;

class BaseInit
{
    /**
     * @description init app
     *
     * @param App $app
     *
     * @return void
     */
    public function __initApp(App $app) : void
    {
        $app->registerServer(new Server($app->getConfig()['server']));
    }
}
