<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use React\Stream\ReadableStreamInterface;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
interface ReadableAudioStreamInterface extends AudioStreamInterface, ReadableStreamInterface
{
}
