<?php
/**
 * @description
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
	 * @description 打包类型
	 *
	 * @var string
	 */
	const PACK_TYPE = 'N';

	/**
	 * @description 包头长度
	 *
	 * @var int
	 */
	const HEADER_LENGTH = 8;

	/**
	 * @description 包最大长度
	 *
	 * @var int
	 */
	const MAX_LENGTH = 2097152;

	/**
	 * @description 包长度所在位置
	 *
	 * @var int
	 */
	const LENGTH_OFFSET = 4;

	/**
	 * @description 包体开始位置
	 *
	 * @var int
	 */
	const BODY_OFFSET = 8;

	/**
	 * @description 构造函数
	 *
	 * @param string $body
	 *
	 * @param int $action
	 *
	 * @return ProtocolInterface
	 */
	public function __construct(string $body, int $action);

	/**
	 * @description 获取路径
	 *
	 * @return string
	 */
	public function getMessage() : string;

    /**
     * @description 获取协议号
     *
     * @return int
     */
    public function getAction() : int;
}
