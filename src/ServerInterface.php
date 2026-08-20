<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Evenement\EventEmitterInterface;
use React\Socket\ServerInterface as ReactServerInterface;

/**
 * Events:
 *
 * connection event
 * error event
 *
 * @see ReactServerInterface
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
interface ServerInterface extends EventEmitterInterface
{
    /**
     * Returns the full address (URI) this server is currently listening on.
     *
     * @see ReactServerInterface::getAddress()
     */
    public function getAddress(): ?string;

    /**
     * Pauses accepting new incoming connections.
     *
     * @see ReactServerInterface::pause()
     */
    public function pause(): void;

    /**
     * Resumes accepting new incoming connections.
     *
     * @see ReactServerInterface::resume()
     */
    public function resume(): void;

    public function isClosed(): bool;

    /**
     * Shuts down this listening socket.
     *
     * @see ReactServerInterface::close()
     */
    public function close(): void;
}
