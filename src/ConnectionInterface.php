<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Evenement\EventEmitterInterface;
use React\Socket\ConnectionInterface as ReactConnectionInterface;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\Protocol\AudioFormat;

/**
 * Events:
 *
 * audio event
 * error event
 * close event
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
interface ConnectionInterface extends EventEmitterInterface
{
    /**
     * Returns the full remote address (URI) where this connection has been established with.
     *
     * @see ReactConnectionInterface::getRemoteAddress()
     */
    public function getRemoteAddress(): ?string;

    /**
     * Returns the full local address (full URI with scheme, IP and port) where this connection has been established with.
     *
     * @see ReactConnectionInterface::getLocalAddress()
     */
    public function getLocalAddress(): ?string;

    public function getUuid(): Uuid;

    public function isReadable(): bool;

    public function pause(): void;

    public function resume(): void;

    public function isWritable(): bool;

    public function sendAudio(AudioFormat $audioFormat, string $audioChunk): void;

    public function isClosed(): bool;

    public function close(): void;
}
