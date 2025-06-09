<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Evenement\EventEmitter;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\Uid\Uuid;
use Zete7\React\AudioSocket\Protocol\Kind;
use Zete7\React\AudioSocket\Protocol\Message;
use Zete7\React\AudioSocket\Protocol\Parser;

use function React\Promise\resolve;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
class Client extends EventEmitter
{
    private ?ConnectionInterface $stream;
    private readonly Parser $parser;
    private bool $ending = false;

    private ?Uuid $id = null;

    /**
     * @var Deferred<Uuid>|null
     */
    private ?Deferred $idDeferred = null;

    public function __construct(ConnectionInterface $stream, ?Parser $parser = null)
    {
        $this->stream = $stream;
        $this->parser = $parser ?? new Parser();

        $stream->on('data', $this->handleChunk(...));

        $this->on('error', $this->close(...));

        $stream->on('close', $this->close(...));
    }

    public function send(Message $message): bool
    {
        if ($this->ending) {
            return false;
        }
        if (null === $this->stream) {
            return false;
        }

        return $this->stream->write($message->getRaw());
    }

    public function pause(): void
    {
        $this->stream?->pause();
    }

    public function resume(): void
    {
        $this->stream?->resume();
    }

    public function end(): void
    {
        if ($this->ending) {
            return;
        }

        $this->ending = true;
        $this->stream->pause();

        if (!$this->stream->write(Message::createHangupMessage()->getRaw())) {
            $this->emit('error', [new \RuntimeException('Failed to send hangup message')]);
        }
    }

    public function close(): void
    {
        if (null === $this->stream) {
            return;
        }

        $this->ending = true;

        $this->idDeferred?->reject(new \RuntimeException('Client is closed'));

        $stream = $this->stream;
        $this->stream = null;
        $stream->close();

        $this->emit('close', [$this]);
    }

    /**
     * @return PromiseInterface<Uuid>
     */
    public function getId(): PromiseInterface
    {
        if (null !== $this->id) {
            return resolve($this->id);
        }

        $deferred = new Deferred();
        $this->idDeferred = $deferred;

        return $deferred->promise();
    }

    private function handleChunk(string $chunk): void
    {
        foreach ($this->parser->push($chunk) as $message) {
            $messageKind = $message->getKind();

            if (Kind::Error === $messageKind) {
                $this->emit('error', [new ErrorMessageException('Error message received', $message->getErrorCode())]);

                return;
            }

            if (Kind::Hangup === $messageKind) {
                $this->close();

                return;
            }

            if (Kind::ID === $messageKind) {
                $id = $message->getId();
                $this->id = $id;

                if (null !== $this->idDeferred) {
                    $this->idDeferred->resolve($id);
                    $this->idDeferred = null;
                }

                return;
            }

            $this->emit('data', [$message]);
        }
    }
}
