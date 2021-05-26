<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-05-18 10:50:53
 *
 */
namespace Kovey\Tcp\Protocol;

interface PacketInterface
{
    public function mergeFromString(string $data) : void;

    public function serializeToString() : string;

    public function serializeToJsonString() : string;
}
