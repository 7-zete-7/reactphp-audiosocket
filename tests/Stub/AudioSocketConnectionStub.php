<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Stub;

use Evenement\EventEmitterTrait;
use React\Promise\Deferred;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\DtmfSignal;
use Zete7\React\AudioSocket\ClientConnectionInterface;
use Zete7\React\AudioSocket\ServerConnectionInterface;

use function React\Async\await;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class AudioSocketConnectionStub implements ClientConnectionInterface, ServerConnectionInterface
{
    use EventEmitterTrait;

    private bool $closed = false;

    private bool $readable = true;

    private bool $writable = true;

    private bool $paused = false;

    /**
     * @var int<0, max>
     */
    public private(set) int $hangupSentCount = 0;

    /**
     * @var list<array{ AudioFormat, string }>
     */
    public private(set) array $sentAudio = [];

    /**
     * @var list<DtmfSignal>
     */
    public private(set) array $sentDtmfSignals = [];

    /**
     * @var list<string>
     */
    public private(set) array $sentErrorPayloads = [];

    /**
     * @var Deferred<null>|null
     */
    private ?Deferred $sendDeferred = null;

    public function __construct(
        private readonly Uuid $uuid,

        private readonly ?string $remoteAddress = null,

        private readonly ?string $localAddress = null,
    ) {
    }

    #[\Override]
    public function getRemoteAddress(): ?string
    {
        return $this->remoteAddress;
    }

    #[\Override]
    public function getLocalAddress(): ?string
    {
        return $this->localAddress;
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

    public function setReadable(bool $readable): void
    {
        $this->readable = $readable;
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

    #[\Override]
    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function setWritable(bool $writable): void
    {
        $this->writable = $writable;
    }

    public function waitForSend(): void
    {
        $this->sendDeferred ??= new Deferred();

        await($this->sendDeferred->promise());
    }

    #[\Override]
    public function sendAudio(AudioFormat $audioFormat, string $audioChunk): void
    {
        $this->sentAudio[] = [$audioFormat, $audioChunk];

        if (null !== $deferred = $this->sendDeferred) {
            $this->sendDeferred = null;
            $deferred->resolve(null);
        }
    }

    #[\Override]
    public function sendHangup(): void
    {
        ++$this->hangupSentCount;

        if (null !== $deferred = $this->sendDeferred) {
            $this->sendDeferred = null;
            $deferred->resolve(null);
        }
    }

    #[\Override]
    public function sendDtmf(DtmfSignal $signal): void
    {
        $this->sentDtmfSignals[] = $signal;

        if (null !== $deferred = $this->sendDeferred) {
            $this->sendDeferred = null;
            $deferred->resolve(null);
        }
    }

    #[\Override]
    public function sendError(string $payload): void
    {
        $this->sentErrorPayloads[] = $payload;

        if (null !== $deferred = $this->sendDeferred) {
            $this->sendDeferred = null;
            $deferred->resolve(null);
        }
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
    }

    public function emitHangup(): void
    {
        $this->emit('hangup');
    }

    public function emitDtmf(DtmfSignal $signal): void
    {
        $this->emit('dtmf', [$signal]);
    }

    public function emitAudio(string $payload, AudioFormat $audioFormat): void
    {
        $this->emit('audio', [$payload, $audioFormat]);
    }

    public function emitError(\Throwable $error): void
    {
        $this->emit('error', [$error]);
    }

    public function emitClose(): void
    {
        $this->emit('close');
    }
}
