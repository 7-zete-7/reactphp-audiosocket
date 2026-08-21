<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test;

use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\AudioMessage;
use Zete7\AudioSocket\Protocol\Message;
use Zete7\React\AudioSocket\AbstractConnection;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class Connection extends AbstractConnection
{
    private bool $initialized = false;

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * @return list<Message>
     */
    public function getQueue(): array
    {
        return [...$this->queue];
    }

    #[\Override]
    protected function processMessage(Message $message): void
    {
        $this->emit('message', [$message]);
    }

    #[\Override]
    protected function initializeConnection(): void
    {
        $this->initialized = true;
    }

    public function emitAudio(string $payload, AudioFormat $audioFormat): void
    {
        $this->emit('message', [new AudioMessage(
            audioFormat: $audioFormat,
            payload: $payload,
        )]);
    }

    public function emitError(\Throwable $error): void
    {
        $this->emit('error', [$error]);
    }
}
