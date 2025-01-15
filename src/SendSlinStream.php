<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Evenement\EventEmitter;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Stream\WritableStreamInterface;
use Zete7\React\AudioSocket\Protocol\Message;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class SendSlinStream extends EventEmitter implements WritableStreamInterface
{
    private ?Client $stream;

    /**
     * In seconds
     */
    private readonly float $chunkDuration;

    /**
     * Based on 8kHz, 16-bit signed linear
     *
     * In bytes.
     */
    private readonly int $chunkSize;

    private readonly int $softLimit;

    private string $data = '';
    private bool $writable = true;
    private ?TimerInterface $timer = null;

    /**
     * @param float $chunkDuration In seconds
     * @param int   $softLimit     In bytes
     */
    public function __construct(Client $stream, float $chunkDuration = 0.02, int $softLimit = 65536)
    {
        $this->stream = $stream;
        $this->chunkDuration = $chunkDuration;
        $this->chunkSize = (int) ceil(round(16000 * $chunkDuration, 1));
        $this->softLimit = $softLimit;

        $this->on('error', $this->close(...));
        $stream->on('close', $this->close(...));
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write($data): bool
    {
        if (!$this->writable) {
            return false;
        }

        $this->data .= $data;
        $this->startTimer();

        return !isset($this->data[$this->softLimit - 1]);
    }

    public function end($data = null): void
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->writable = false;

        // close immediately if buffer is already empty
        // otherwise wait for buffer to flush first
        if ('' === $this->data) {
            $this->stream->end();
        }
    }

    public function close(): void
    {
        if (null === $this->stream) {
            return;
        }

        $this->stopTimer();

        $this->writable = false;
        $this->data = '';

        $stream = $this->stream;
        $this->stream = null;
        $stream->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    private function startTimer(): void
    {
        if (null !== $this->timer) {
            return;
        }

        $this->timer = Loop::addPeriodicTimer($this->chunkDuration, $this->onTimer(...));
    }

    private function stopTimer(): void
    {
        if (null === $this->timer) {
            return;
        }

        Loop::cancelTimer($this->timer);
    }

    private function onTimer(): void
    {
        if (null === $this->stream) {
            return;
        }
        if ('' === $this->data) {
            return;
        }

        $chunk = (string) \substr($this->data, 0, $this->chunkSize);

        if (!$this->stream->send(Message::createSlinMessage($chunk))) {
            $this->emit('error', [new \RuntimeException('Failed to send SLIN message')]);
        }

        $exceeded = isset($this->data[$this->softLimit - 1]);
        $this->data = (string) \substr($this->data, $this->chunkSize);

        // buffer has been above limit and is now below limit
        if ($exceeded && !isset($this->data[$this->softLimit - 1])) {
            $this->emit('drain');
        }

        // buffer is now completely empty => stop trying to write
        if ('' === $this->data) {
            $this->stopTimer();

            // buffer is end()ing and now completely empty => close buffer
            if (!$this->writable) {
                $this->stream->end();
            }
        }
    }
}
