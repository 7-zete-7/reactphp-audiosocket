<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
use Zete7\React\AudioSocket\Protocol\Kind;
use Zete7\React\AudioSocket\Protocol\Message;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class ReceiveSlinStream extends EventEmitter implements ReadableStreamInterface
{
    private ?Client $stream;

    public function __construct(Client $stream)
    {
        $this->stream = $stream;

        $stream->on('data', $this->onData(...));
        $this->on('error', $this->close(...));
        $stream->on('close', $this->onClientClose(...));
    }

    public function isReadable(): bool
    {
        return null !== $this->stream;
    }

    public function pause(): void
    {
        $this->stream?->pause();
    }

    public function resume(): void
    {
        $this->stream?->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array()): void
    {
        Util::pipe($this, $dest, $options);
    }

    public function close(): void
    {
        if (null === $this->stream) {
            return;
        }

        $this->pause();

        $stream = $this->stream;
        $this->stream = null;
        $stream->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    private function onData(Message $message): void
    {
        if (Kind::Slin === $message->getKind()) {
            $this->emit('data', [$message->getPayload()]);
        }
    }

    private function onClientClose(): void
    {
        $this->emit('end');
        $this->close();
    }
}
