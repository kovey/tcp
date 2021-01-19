<?php
/**
 * @description tcp api
 *
 * @package
 *
 * @author kovey
 *
 * @time 2019-11-14 22:58:02
 *
 */
namespace Kovey\Tcp\Handler;

abstract class HandlerAbstract
{
    protected string $clientIp;

    public function setClientIp(string $clientIp)
    {
        $this->clientIp = $clientIp;
    }
}
