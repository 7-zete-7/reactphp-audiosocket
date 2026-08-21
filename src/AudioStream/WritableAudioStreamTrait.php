<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\React\AudioSocket\ConnectionInterface;
use Zete7\React\AudioSocket\Exception\OverflowError;
use Zete7\React\AudioSocket\ServerConnectionInterface;

/**
 * @internal
 *
 * @require-implements WritableAudioStream
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
trait WritableAudioStreamTrait
{
    use EventEmitterTrait;

    private readonly ConnectionInterface $connection;

    public readonly AudioFormat $format;

    private readonly LoopInterface $loop;

    public readonly float $softLimitSeconds;

    public readonly float $hardLimitSeconds;

    /**
     * @var int<1, max>
     */
    private int $chunksSize {
        /**
         * @return int<1, max>
         */
        get => $this->chunksSize ??= $this->format->getChunkSize();
    }

    /**
     * @var int<1, max>
     */
    private int $softLimit {
        /**
         * @return int<1, max>
         */
        get => $this->softLimit ??= Util::durationToBytesCount($this->format, $this->softLimitSeconds);
    }

    /**
     * @var int<1, max>
     */
    private int $hardLimit {
        get => $this->hardLimit ??= Util::durationToBytesCount($this->format, $this->hardLimitSeconds);
    }

    /**
     * @var int<0, max>
     */
    private int $chunkSizeLastByteOffset {
        get => $this->chunkSizeLastByteOffset ??= $this->chunksSize - 1;
    }

    /**
     * @var int<0, max>
     */
    private int $softLimitFirstByteOffset {
        get => $this->softLimitFirstByteOffset ??= $this->softLimit - 1;
    }

    /**
     * @var int<0, max>
     */
    private int $hardLimitFirstByteOffset {
        get => $this->hardLimitFirstByteOffset ??= $this->hardLimit - 1;
    }

    private bool $writable = true;

    private string $buffer = '';

    private ?TimerInterface $timer = null;

    #[\Override]
    public function isWritable(): bool
    {
        return $this->writable;
    }

    #[\Override]
    public function write($data): bool
    {
        \assert(\is_string($data));

        if (!$this->writable) {
            return false;
        }

        $this->buffer .= $data;

        if ($this->isExceedHardLimit()) {
            $this->fatalError(new OverflowError('Audio buffer overflow.'));

            // @infection-ignore-all Soft limit is already exceeded. Optimization.
            return false;
        }

        return !$this->isExceedSoftLimit();
    }

    #[\Override]
    public function end($data = null): void
    {
        \assert(null === $data || \is_string($data));

        if (!$this->writable) {
            return;
        }

        $this->writable = false;

        if (null !== $data) {
            $this->buffer .= $data;

            if ($this->isExceedHardLimit()) {
                $this->fatalError(new OverflowError('Audio buffer overflow.'));

                // @infection-ignore-all Chunk is already exists at this point. Optimization.
                return;
            }
        }

        if (!$this->hasChunk()) {
            if ($this->connection instanceof ServerConnectionInterface) {
                $this->connection->sendHangup();
            }
            $this->close();
        }
    }

    #[\Override]
    abstract public function close(): void;

    private function setupWritable(): void
    {
        \assert(2 * AudioStreamInterface::CHUNK_DURATION <= $this->softLimitSeconds, \sprintf('Soft limit duration (%f) must be greater than duration of two chunks (%f).', $this->softLimitSeconds, 2 * AudioStreamInterface::CHUNK_DURATION));
        \assert($this->softLimitSeconds + AudioStreamInterface::CHUNK_DURATION <= $this->hardLimitSeconds, \sprintf('Hard limit duration (%f) must be greater than soft limit duration with extra chunk (%f).', $this->hardLimitSeconds, $this->softLimitSeconds + AudioStreamInterface::CHUNK_DURATION));

        \assert(Util::isFactorOfChunkDuration($this->softLimitSeconds), \sprintf('Soft limit (%f) must be dividable by chunk duration (%f).', $this->softLimitSeconds, AudioStreamInterface::CHUNK_DURATION));
        \assert(Util::isFactorOfChunkDuration($this->hardLimitSeconds), \sprintf('Hard limit (%f) must be dividable by chunk duration (%f).', $this->hardLimitSeconds, AudioStreamInterface::CHUNK_DURATION));

        // @infection-ignore-all It is covered by assertions above.
        \assert(1 < $this->chunksSize, 'Chunk size must be positive.');

        // @infection-ignore-all It is covered by assertions above.
        \assert(2 * $this->chunksSize <= $this->softLimit, \sprintf('Soft limit (%d) must contain at least 2 chunks (%d).', $this->softLimit, 2 * $this->chunksSize));

        // @infection-ignore-all It is covered by assertions above.
        \assert($this->softLimit + $this->chunksSize <= $this->hardLimit, \sprintf('Hard limit (%d) must contain at least 1 chunk more than soft limit (%d).', $this->hardLimit, $this->softLimit + $this->chunksSize));

        $this->timer = $this->loop->addTimer(\PHP_FLOAT_MIN, function (): void {
            $this->timer = $this->loop->addPeriodicTimer(AudioStreamInterface::CHUNK_DURATION, $this->flushChunk(...));
            $this->flushChunk();
        });
    }

    private function closeWritable(): void
    {
        $this->writable = false;

        if (null !== $timer = $this->timer) {
            $this->timer = null;
            $this->loop->cancelTimer($timer);
        }
    }

    private function fatalError(\Throwable $error): void
    {
        $this->emit('error', [$error]);
        $this->close();
    }

    private function flushChunk(): void
    {
        if ($this->hasChunk()) {
            $exceeded = $this->isExceedSoftLimit();
            $chunk = substr($this->buffer, 0, $this->chunksSize);
            $this->buffer = substr($this->buffer, $this->chunksSize);

            $this->connection->sendAudio($this->format, $chunk);

            if ($exceeded && !$this->isExceedSoftLimit()) {
                $this->emit('drain');
            }
        }

        if (!$this->writable && !$this->hasChunk()) {
            if ($this->connection instanceof ServerConnectionInterface) {
                $this->connection->sendHangup();
            }
            $this->close();
        }
    }

    private function hasChunk(): bool
    {
        return isset($this->buffer[$this->chunkSizeLastByteOffset]);
    }

    private function isExceedSoftLimit(): bool
    {
        return isset($this->buffer[$this->softLimitFirstByteOffset]);
    }

    private function isExceedHardLimit(): bool
    {
        return isset($this->buffer[$this->hardLimitFirstByteOffset]);
    }
}
