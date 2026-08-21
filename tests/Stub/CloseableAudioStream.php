<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Stub;

use Zete7\React\AudioSocket\AudioStream\CloseableAudioStreamTrait;
use Zete7\React\AudioSocket\ConnectionInterface;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class CloseableAudioStream
{
    use CloseableAudioStreamTrait {
        close as private internalClose;
    }

    public private(set) int $closeCalled = 0;

    public private(set) int $closeReadableCalled = 0;

    public private(set) int $closeWritableCalled = 0;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
        if ($this->connection->isClosed()) {
            $this->close();

            return;
        }

        $this->setupClosable();
    }

    public function close(): void
    {
        ++$this->closeCalled;

        $this->internalClose();
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    private function closeReadable(): void
    {
        ++$this->closeReadableCalled;
    }

    private function closeWritable(): void
    {
        ++$this->closeWritableCalled;
    }
}
