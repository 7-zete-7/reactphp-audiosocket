<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Zete7\AudioSocket\Protocol\DtmfSignal;

/**
 * Events:
 *
 * hangup event
 * audio event
 * error event
 * close event
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
interface ClientConnectionInterface extends ConnectionInterface
{
    public function sendDtmf(DtmfSignal $signal): void;

    public function sendError(string $payload): void;
}
