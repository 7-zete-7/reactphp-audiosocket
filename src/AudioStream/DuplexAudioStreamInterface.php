<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use React\Stream\DuplexStreamInterface;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
interface DuplexAudioStreamInterface extends ReadableAudioStreamInterface, WritableAudioStreamInterface, DuplexStreamInterface
{
}
