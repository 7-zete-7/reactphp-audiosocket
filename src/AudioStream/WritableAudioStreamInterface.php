<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use React\Stream\WritableStreamInterface;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
interface WritableAudioStreamInterface extends AudioStreamInterface, WritableStreamInterface
{
}
