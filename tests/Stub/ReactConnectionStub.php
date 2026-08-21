<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Stub;

use Evenement\EventEmitterTrait;
use React\Socket\ConnectionInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class ReactConnectionStub implements ConnectionInterface
{
    use EventEmitterTrait;

    private bool $readable = true;

    private bool $writable = true;

    private bool $paused = false;

    /**
     * @var \Closure(string): bool
     */
    private \Closure $writeListener;

    /**
     * @var \Closure(string|null, \Closure): void
     */
    private \Closure $endListener;

    /**
     * @var \Closure(): void
     */
    private \Closure $closeListener;

    public function __construct(
        private readonly ?string $remoteAddress = null,
        private readonly ?string $localAddress = null,
    ) {
        $this->writeListener = static fn (): bool => true;
        $this->endListener = static function (): void {};
        $this->closeListener = static function (): void {};
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
    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function setReadable(bool $readable): void
    {
        $this->readable = $this->readable && $readable;
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
    public function pipe(WritableStreamInterface $dest, array $options = []): WritableStreamInterface
    {
        return Util::pipe($this, $dest, $options);
    }

    #[\Override]
    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function setWritable(bool $writable): void
    {
        $this->writable = $this->writable && $writable;
    }

    /**
     * @param \Closure(string $data): bool $listener
     *
     * @param-later-invoked-callable $listener
     */
    public function onWrite(\Closure $listener): void
    {
        $this->writeListener = $listener;
    }

    #[\Override]
    public function write($data): bool
    {
        \assert(\is_string($data));

        return ($this->writeListener)($data);
    }

    /**
     * @param \Closure(string|null $data, \Closure $end): void $listener
     *
     * @param-later-invoked-callable $listener
     */
    public function onEnd(\Closure $listener): void
    {
        $this->endListener = $listener;
    }

    #[\Override]
    public function end($data = null): void
    {
        \assert(null === $data || \is_string($data));

        $listener = $this->endListener;

        if (2 > new \ReflectionFunction($listener)->getNumberOfParameters()) {
            // @phpstan-ignore arguments.count (Listener doesn't accept second argument.)
            $listener($data);

            $this->writable = false;
        } else {
            $listener($data, function (): void {
                $this->writable = false;
            });
        }
    }

    /**
     * @param \Closure(): void $listener
     *
     * @param-later-invoked-callable $listener
     */
    public function onClose(\Closure $listener): void
    {
        $this->closeListener = $listener;
    }

    #[\Override]
    public function close(): void
    {
        $this->readable = false;
        $this->writable = false;

        ($this->closeListener)();
    }

    /**
     * @impure
     */
    public function emitData(string $data): void
    {
        $this->emit('data', [$data]);
    }

    /**
     * @impure
     */
    public function emitEnd(): void
    {
        $this->emit('end');
    }

    /**
     * @impure
     */
    public function emitDrain(): void
    {
        $this->emit('drain');
    }

    /**
     * @impure
     */
    public function emitPipe(ReadableStreamInterface $source): void
    {
        $this->emit('pipe', [$source]);
    }

    /**
     * @impure
     */
    public function emitError(\Throwable $error): void
    {
        $this->emit('error', [$error]);
    }

    /**
     * @impure
     */
    public function emitClose(): void
    {
        $this->emit('close');
    }
}
