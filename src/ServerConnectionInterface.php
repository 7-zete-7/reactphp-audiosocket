<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

/**
 * Events:
 *
 * dtmf event
 * audio event
 * error event
 * close event
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
interface ServerConnectionInterface extends ConnectionInterface
{
    public function sendHangup(): void;
}
