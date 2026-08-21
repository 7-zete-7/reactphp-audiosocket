<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use Zete7\AudioSocket\Protocol\AudioFormat;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
abstract class Util
{
    private const float CHUNKS_IN_SECOND = 50.0; // = 1 / AudioStream::CHUNK_DURATION

    /**
     * @return int<320, max>
     */
    public static function durationToBytesCount(AudioFormat $audioFormat, float $seconds): int
    {
        \assert(0 < $seconds, \sprintf('Duration (%f) must be positive.', $seconds));
        \assert(self::isFactorOfChunkDuration($seconds), \sprintf('Duration (%f) must be a factor of chunk duration (%f).', $seconds, AudioStreamInterface::CHUNK_DURATION));

        $bytesCount = (int) round($audioFormat->getChunkSize() * $seconds * self::CHUNKS_IN_SECOND);

        // @infection-ignore-all It is covered by assertions above.
        \assert(320 <= $bytesCount);

        // @infection-ignore-all It is covered by assertions above.
        \assert(0 === $bytesCount % $audioFormat->getChunkSize());

        return $bytesCount;
    }

    public static function isFactorOfChunkDuration(float $seconds): bool
    {
        if ($seconds < AudioStreamInterface::CHUNK_DURATION) {
            return false;
        }

        $modulo = fmod($seconds, AudioStreamInterface::CHUNK_DURATION);

        // @infection-ignore-all It is covered by assertions above.
        \assert(0.0 <= $modulo);

        // @infection-ignore-all
        if (\PHP_FLOAT_EPSILON > $modulo) {
            // $module is near to zero
            return true;
        }

        // @infection-ignore-all
        if (\PHP_FLOAT_EPSILON > abs($modulo - AudioStreamInterface::CHUNK_DURATION)) {
            // $module is near to chunk duration
            return true;
        }

        return false;
    }
}
