<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Evenement\EventEmitterTrait;
use React\Promise\Stream;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as ReactServerInterface;
use React\Stream\Util;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\BinaryMessageEncoder;
use Zete7\AudioSocket\MessageEncoder;
use Zete7\AudioSocket\Protocol\UuidMessage;
use Zete7\React\AudioSocket\Exception\UninitializedConnectionError;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class Server implements ServerInterface
{
    use EventEmitterTrait;

    private bool $closed = false;

    /**
     * @param int<1, max> $connectionMaxBufferSize
     * @param int<1, max> $connectionMaxQueueSize
     */
    public function __construct(
        private readonly ReactServerInterface $server,

        private readonly MessageEncoder $messageEncoder = new BinaryMessageEncoder(),

        private readonly int $connectionMaxBufferSize = ServerConnection::DEFAULT_MAX_BUFFER_SIZE,

        private readonly int $connectionMaxQueueSize = ServerConnection::DEFAULT_MAX_QUEUE_SIZE,
    ) {
        $this->server->on('connection', $this->handleServerConnection(...));

        Util::forwardEvents($this->server, $this, ['error']);
    }

    #[\Override]
    public function getAddress(): ?string
    {
        return $this->server->getAddress();
    }

    #[\Override]
    public function pause(): void
    {
        $this->server->pause();
    }

    #[\Override]
    public function resume(): void
    {
        $this->server->resume();
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->closed;
    }

    #[\Override]
    public function close(): void
    {
        $this->closed = true;
        $this->server->close();
    }

    private function handleServerConnection(ConnectionInterface $connection): void
    {
        Stream\first($connection)
            ->then(function ($initialBuffer) use ($connection): void {
                \assert(\is_string($initialBuffer));

                $initialMessage = $this->messageEncoder->decodeMessage($initialBuffer);

                if (null === $initialMessage) {
                    throw new UninitializedConnectionError('AudioSocket connection has no message in first received data.');
                }

                if (!$initialMessage instanceof UuidMessage) {
                    throw new UninitializedConnectionError(\sprintf('AudioSocket connection was initialized with non-UUID message (0x%s).', bin2hex($initialMessage->kind->value)));
                }

                $audioSocketConnection = $this->createServerConnection($connection, $initialMessage->uuid, $initialBuffer);
                $this->emit('connection', [$audioSocketConnection]);
            }, static function (\Throwable $error): never {
                throw new UninitializedConnectionError('Unable to receive AudioSocket connection UUID: '.$error->getMessage(), previous: $error);
            })
            ->catch(function (\Throwable $error): void {
                $this->emit('error', [$error]);
            })
        ;
    }

    private function createServerConnection(ConnectionInterface $connection, Uuid $uuid, string $initialBuffer): ServerConnection
    {
        return new ServerConnection(
            connection: $connection,
            uuid: $uuid,
            buffer: $initialBuffer,
            messageEncoder: $this->messageEncoder,
            maxBufferSize: $this->connectionMaxBufferSize,
            maxQueueSize: $this->connectionMaxQueueSize,
        );
    }
}
