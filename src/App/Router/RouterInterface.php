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

interface RouterInterface
{
    public function getProtobuf() : string;

    public function getProtobufBase() : string;

    public function getHandler() : string;

    public function getMethod() : string;
}
