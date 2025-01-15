<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Protocol;

/**
 * An error, if present
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
enum ErrorCode : int
{
    /**
     * No error is present
     */
    case None = 0x00;

    /**
     * The call has hung up
     */
    case AstHangup = 0x01;

    /**
     * Asterisk had an error trying to forward an audio frame
     */
    case AstFrameForwarding = 0x02;

    /**
     * Asterisk had a memory/allocation erorr
     */
    case AstMemory = 0x04;

    /**
     * The received error from Asterisk is unknown
     */
    case Unknown = 0xff;
}
