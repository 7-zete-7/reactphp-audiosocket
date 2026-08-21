<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use Evenement\EventEmitterTrait;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\React\AudioSocket\ClientConnectionInterface;
use Zete7\React\AudioSocket\ConnectionInterface;

/**
 * @internal
 *
 * @require-implements ReadableAudioStreamInterface
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
trait ReadableAudioStreamTrait
{
    use EventEmitterTrait;

    private readonly ConnectionInterface $connection;

    public readonly AudioFormat $format;

    private bool $readable = true;

    /**
     * @var \Closure(string, AudioFormat): void
     */
    private \Closure $connectionAudioListener {
        get => $this->connectionAudioListener ??= $this->handleConnectionAudio(...);
    }

    /**
     * @var \Closure(): void
     */
    private \Closure $connectionHangupListener {
        get => $this->connectionHangupListener ??= $this->handleConnectionHangup(...);
    }

    #[\Override]
    public function isReadable(): bool
    {
        return $this->readable;
    }

    #[\Override]
    public function pause(): void
    {
        $this->connection->pause();
    }

    #[\Override]
    public function resume(): void
    {
        $this->connection->resume();
    }

    #[\Override]
    public function pipe(WritableStreamInterface $dest, array $options = []): WritableStreamInterface
    {
        return Util::pipe($this, $dest, $options);
    }

    #[\Override]
    abstract public function close(): void;

    private function setupReadable(): void
    {
        $this->connection->on('audio', $this->connectionAudioListener);

        if ($this->connection instanceof ClientConnectionInterface) {
            $this->connection->on('hangup', $this->connectionHangupListener);
        }
    }

    private function closeReadable(): void
    {
        $this->readable = false;

        $this->connection->removeListener('audio', $this->connectionAudioListener);

        if ($this->connection instanceof ClientConnectionInterface) {
            $this->connection->removeListener('hangup', $this->connectionHangupListener);
        }
    }

    private function handleConnectionAudio(string $payload, AudioFormat $audioFormat): void
    {
        if ($this->format !== $audioFormat) {
            $this->emit('error', [new \RuntimeException(\sprintf('Expected to AudioFormat "%s", "%s" received.', $this->format->name, $audioFormat->name))]);
            $this->close();

            // @infection-ignore-all A "data" event nobody listen at this point. Optimization to prevent unnecessary code execution.
            return;
        }

        $this->emit('data', [$payload]);
    }

    private function handleConnectionHangup(): void
    {
        $this->emit('end');
    }
}
