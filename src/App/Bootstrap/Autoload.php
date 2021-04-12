<?php
/**
 *
 * @description autoload manager
 *
 * @package     App\Bootstrap
 *
 * @time        Tue Sep 24 08:59:43 2019
 *
 * @author      kovey
 */
namespace Kovey\Tcp\App\Bootstrap;

use Kovey\App\Components\AutoloadInterface;

class Autoload implements AutoloadInterface
{
    /**
     * @description custom auto path
     *
     * @var Array
     */
    private Array $customs;

    /**
     * @description library path
     *
     * @var string
     */
    private string $library;

    /**
     * @description construct
     *
     * @return Autoload
     */
    public function __construct()
    {
        $this->library = APPLICATION_PATH . '/application/library/';

        $this->customs = array();
    }

    /**
     * @description register
     *
     * @return Autoload
     */
    public function register() : Autoload
    {
        spl_autoload_register(array($this, 'autoloadUserLib'));
        spl_autoload_register(array($this, 'autoloadLocal'));
        return $this;
    }

    /**
     * @description user library
     *
     * @param string $className
     *
     * @return void
     */
    public function autoloadUserLib(string $className) : void
    {
        try {
            $className = $this->library . str_replace('\\', '/', $className) . '.php';
            $className = str_replace('//', '/', $className);
            if (!is_file($className)) {
                return;
            }

            require_once $className;
        } catch (\Throwable $e) {    
            echo $e->getMessage();
        }
    }

    /**
     * @description custom library
     *
     * @param string $className
     *
     * @return void
     */
    public function autoloadLocal(string $className) : void
    {
        foreach ($this->customs as $path) {
            try {
                $className = $path . '/' . str_replace('\\', '/', $className) . '.php';
                if (!is_file($className)) {
                    continue;
                }

                require_once $className;
                break;
            } catch (\Throwable $e) {
                echo $e->getMessage();
            }
        }
    }

    /**
     * @description add local path
     *
     * @param string $path
     *
     * @return Autoload
     */
    public function addLocalPath(string $path) : Autoload
    {
        if (!is_dir($path)) {
            return $this;
        }
        $this->customs[] = $path;
        return $this;
    }
}
