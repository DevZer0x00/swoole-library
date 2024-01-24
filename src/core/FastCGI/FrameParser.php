<?php
/**
 * This file is part of Swoole.
 *
 * @link     https://www.swoole.com
 * @contact  team@swoole.com
 * @license  https://github.com/swoole/library/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Swoole\FastCGI;

use Swoole\FastCGI;
use Swoole\FastCGI\Record\AbortRequest;
use Swoole\FastCGI\Record\BeginRequest;
use Swoole\FastCGI\Record\Data;
use Swoole\FastCGI\Record\EndRequest;
use Swoole\FastCGI\Record\GetValues;
use Swoole\FastCGI\Record\GetValuesResult;
use Swoole\FastCGI\Record\Params;
use Swoole\FastCGI\Record\Stderr;
use Swoole\FastCGI\Record\Stdin;
use Swoole\FastCGI\Record\Stdout;
use Swoole\FastCGI\Record\UnknownType;

/**
 * Utility class to simplify parsing of FastCGI protocol data.
 */
class FrameParser
{
    /**
     * Mapping of constants to the classes
     *
     * @var array
     */
    protected static $classMapping = [
        FastCGI::BEGIN_REQUEST     => BeginRequest::class,
        FastCGI::ABORT_REQUEST     => AbortRequest::class,
        FastCGI::END_REQUEST       => EndRequest::class,
        FastCGI::PARAMS            => Params::class,
        FastCGI::STDIN             => Stdin::class,
        FastCGI::STDOUT            => Stdout::class,
        FastCGI::STDERR            => Stderr::class,
        FastCGI::DATA              => Data::class,
        FastCGI::GET_VALUES        => GetValues::class,
        FastCGI::GET_VALUES_RESULT => GetValuesResult::class,
        FastCGI::UNKNOWN_TYPE      => UnknownType::class,
    ];

    /**
     * Checks if the buffer contains a valid frame to parse
     *
     * @param string $buffer Binary buffer
     */
    public static function hasFrame(string $buffer): bool
    {
        $bufferLength = strlen($buffer);
        if ($bufferLength < FastCGI::HEADER_LEN) {
            return false;
        }

        $fastInfo = unpack(FastCGI::HEADER_FORMAT, $buffer);
        if ($bufferLength < FastCGI::HEADER_LEN + $fastInfo['contentLength'] + $fastInfo['paddingLength']) {
            return false;
        }

        return true;
    }

    /**
     * Parses a frame from the binary buffer
     *
     * @param string $buffer Binary buffer
     *
     * @return Record One of the corresponding FastCGI record
     */
    public static function parseFrame(string &$buffer): Record
    {
        $bufferLength = strlen($buffer);
        if ($bufferLength < FastCGI::HEADER_LEN) {
            throw new \RuntimeException('Not enough data in the buffer to parse');
        }
        $recordHeader = unpack(FastCGI::HEADER_FORMAT, $buffer);
        $recordType   = $recordHeader['type'];
        if (!isset(self::$classMapping[$recordType])) {
            throw new \DomainException("Invalid FastCGI record type {$recordType} received");
        }

        /** @var Record $className */
        $className = self::$classMapping[$recordType];
        $record    = $className::unpack($buffer);

        $offset = FastCGI::HEADER_LEN + $record->getContentLength() + $record->getPaddingLength();
        $buffer = substr($buffer, $offset);

        return $record;
    }
}
