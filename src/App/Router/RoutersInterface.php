<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-04-12 15:39:36
 *
 */
namespace Kovey\Tcp\App\Router;

interface RoutersInterface
{
    public function addRouter(int | string $code, RouterInterface $router) : RoutersInterface;

    public function getRouter(int | string $code) : RouterInterface;
}
