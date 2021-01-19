<?php
/**
 * @description protocol interface
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-04-29 14:21:46
 *
 */
namespace Kovey\Tcp\Protocol;

interface ProtocolInterface
{
    /**
     * @description pack type
     *
     * @var string
     */
    const PACK_TYPE = 'N';

    /**
     * @description header length
     *
     * @var int
     */
    const HEADER_LENGTH = 8;

    /**
     * @description max length
     *
     * @var int
     */
    const MAX_LENGTH = 2097152;

    /**
     * @description length offset
     *
     * @var int
     */
    const LENGTH_OFFSET = 4;

    /**
     * @description body offset
     *
     * @var int
     */
    const BODY_OFFSET = 8;

    /**
     * @description construct
     *
     * @param string $body
     *
     * @param int $action
     *
     * @return ProtocolInterface
     */
    public function __construct(string $body, int $action);

    /**
     * @description get message
     *
     * @return string
     */
    public function getMessage() : string;

    /**
     * @description get action
     *
     * @return int
     */
    public function getAction() : int;
}
