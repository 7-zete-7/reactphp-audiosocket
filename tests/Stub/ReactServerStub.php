<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Stub;

use Evenement\EventEmitterTrait;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class ReactServerStub implements ServerInterface
{
    use EventEmitterTrait;

    private bool $paused = false;

    private bool $closed = false;

    public function __construct(
        private readonly ?string $address = null,
    ) {
    }

    #[\Override]
    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    #[\Override]
    public function pause(): void
    {
        $this->paused = true;
    }

    #[\Override]
    public function resume(): void
    {
        $this->paused = false;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    #[\Override]
    public function close(): void
    {
        $this->closed = true;
    }

    public function emitConnection(ConnectionInterface $connection): void
    {
        $this->emit('connection', [$connection]);
    }

    public function emitError(\Throwable $error): void
    {
        $this->emit('error', [$error]);
    }
}
