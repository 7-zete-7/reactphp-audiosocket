<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use Zete7\AudioSocket\Protocol\AudioFormat;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
interface AudioStreamInterface
{
    public const float CHUNK_DURATION = 0.02;

    public AudioFormat $format { get; }
}
