<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Evenement\EventEmitterTrait;
use React\Socket\ConnectionInterface as ReactConnectionInterface;
use React\Stream\Util;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\BinaryMessageEncoder;
use Zete7\AudioSocket\MessageEncoder;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\AudioMessage;
use Zete7\AudioSocket\Protocol\ErrorMessage;
use Zete7\AudioSocket\Protocol\Message;
use Zete7\React\AudioSocket\Exception\AsteriskError;
use Zete7\React\AudioSocket\Exception\OverflowError;
use Zete7\React\AudioSocket\Exception\UnprocessedBufferError;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
abstract class AbstractConnection implements ConnectionInterface
{
    use EventEmitterTrait;

    public const int DEFAULT_MAX_BUFFER_SIZE = 131078; // 2 full-sized messages = 2 * (3 + 65536)

    public const int DEFAULT_MAX_QUEUE_SIZE = 10; // approximately 2 seconds of audio

    protected bool $closed = false;

    protected bool $readable = true;

    protected bool $writable = true;

    protected bool $paused = false;

    /**
     * @var \SplQueue<Message>
     */
    protected \SplQueue $queue;

    private bool $connectionEnded = false;

    /**
     * @param int<1, max> $maxBufferSize
     * @param int<1, max> $maxQueueSize
     */
    public function __construct(
        protected readonly ReactConnectionInterface $connection,

        protected readonly Uuid $uuid,

        protected string $buffer = '',

        protected readonly MessageEncoder $messageEncoder = new BinaryMessageEncoder(),

        private readonly int $maxBufferSize = self::DEFAULT_MAX_BUFFER_SIZE,

        private readonly int $maxQueueSize = self::DEFAULT_MAX_QUEUE_SIZE,
    ) {
        $this->queue = new \SplQueue();

        if (!$this->connection->isReadable() || !$this->connection->isWritable()) {
            $this->close();

            return;
        }

        $this->connection->on('data', $this->handleConnectionData(...));
        $this->connection->on('end', $this->handleConnectionEnd(...));
        $this->connection->on('close', $this->handleConnectionClose(...));

        Util::forwardEvents($this->connection, $this, ['error']);

        $this->initializeConnection();
    }

    #[\Override]
    public function getRemoteAddress(): ?string
    {
        return $this->connection->getRemoteAddress();
    }

    #[\Override]
    public function getLocalAddress(): ?string
    {
        return $this->connection->getLocalAddress();
    }

    #[\Override]
    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    #[\Override]
    public function isReadable(): bool
    {
        return $this->readable;
    }

    #[\Override]
    public function pause(): void
    {
        if (!$this->readable) {
            return;
        }

        $this->paused = true;
        $this->connection->pause();
    }

    #[\Override]
    public function resume(): void
    {
        if (!$this->readable) {
            return;
        }

        $this->paused = false;
        $this->flushQueue();
        $this->connection->resume();
    }

    #[\Override]
    public function isWritable(): bool
    {
        return $this->writable;
    }

    #[\Override]
    public function sendAudio(AudioFormat $audioFormat, string $audioChunk): void
    {
        if (!$this->writable) {
            return;
        }

        $message = new AudioMessage(
            audioFormat: $audioFormat,
            payload: $audioChunk,
        );

        $bytes = $this->messageEncoder->encodeMessage($message);
        $this->connection->write($bytes);
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->closed;
    }

    #[\Override]
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->readable = false;
        $this->writable = false;

        $bufferWasEmpty = '' === $this->buffer;

        $this->buffer = '';
        $this->queue = new \SplQueue();

        $this->connection->close();

        if (!$bufferWasEmpty) {
            $this->emit('error', [new UnprocessedBufferError('AudioSocket server connection was closed with non-empty buffer.')]);
        }

        $this->emit('close');
        $this->removeAllListeners();
    }

    abstract protected function processMessage(Message $message): void;

    protected function initializeConnection(): void
    {
    }

    private function handleConnectionData(string $data): void
    {
        if (!$this->readable) {
            return;
        }

        $this->buffer .= $data;

        if ($this->isBufferExcideMaxSize()) {
            $this->fatalError(new OverflowError('Too lagre buffer received.'));

            // @infection-ignore-all This return is only for optimization reason.
            return;
        }

        while ($this->readable && null !== $message = $this->messageEncoder->decodeMessage($this->buffer)) {
            if ($message instanceof ErrorMessage) {
                $this->fatalError(AsteriskError::createFromErrorMessage($message));

                // @infection-ignore-all This return is only for optimization reason.
                return;
            }

            if (!$this->paused) {
                $this->processMessage($message);

                continue;
            }

            $this->queue->enqueue($message);

            if ($this->isQueueExcideMaxSize()) {
                $this->fatalError(new OverflowError('Received messages queue size limit reached.'));

                // @infection-ignore-all This return is only for optimization reason.
                return;
            }
        }
    }

    private function handleConnectionEnd(): void
    {
        $this->connectionEnded = true;
    }

    private function handleConnectionClose(): void
    {
        $this->writable = false;

        if (!$this->connectionEnded || $this->queue->isEmpty()) {
            $this->close();
        }
    }

    private function flushQueue(): void
    {
        while (!$this->paused && !$this->queue->isEmpty()) {
            $message = $this->queue->dequeue();
            $this->processMessage($message);
        }

        if (!$this->connection->isReadable() && $this->queue->isEmpty()) {
            $this->close();
        }
    }

    final protected function fatalError(\Throwable $error): void
    {
        $this->emit('error', [$error]);
        $this->close();
    }

    private function isBufferExcideMaxSize(): bool
    {
        return isset($this->buffer[$this->maxBufferSize]);
    }

    private function isQueueExcideMaxSize(): bool
    {
        return isset($this->queue[$this->maxQueueSize]);
    }
}
