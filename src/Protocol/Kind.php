<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Protocol;

/**
 * A message type indicator
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
enum Kind : int
{
    /**
     * The message is a hangup signal
     */
    case Hangup = 0x00;

    /**
     * The message contains the unique identifier of the call
     */
    case ID = 0x01;

    /**
     * The presence of silence on the line
     */
    case Silence = 0x02;

    /**
     * The message contains signed-linear audio data
     */
    case Slin = 0x10;

    /**
     * The message contains an error code
     */
    case Error = 0xff;
}
