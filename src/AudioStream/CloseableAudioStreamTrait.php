<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use Evenement\EventEmitterTrait;
use Zete7\React\AudioSocket\ConnectionInterface;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
trait CloseableAudioStreamTrait
{
    use EventEmitterTrait;

    private bool $closed = false;

    private readonly ConnectionInterface $connection;

    /**
     * @var \Closure(): void
     */
    private \Closure $connectionCloseListener {
        get => $this->connectionCloseListener ??= $this->close(...);
    }

    private function setupClosable(): void
    {
        $this->connection->on('close', $this->connectionCloseListener);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        if (method_exists($this, 'closeReadable')) {
            $this->closeReadable();
        }

        if (method_exists($this, 'closeWritable')) {
            $this->closeWritable();
        }

        $this->connection->removeListener('close', $this->connectionCloseListener);

        $this->emit('close');
        $this->removeAllListeners();
    }
}
