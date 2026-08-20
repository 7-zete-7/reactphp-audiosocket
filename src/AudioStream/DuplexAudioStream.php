<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\React\AudioSocket\ConnectionInterface;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class DuplexAudioStream implements DuplexAudioStreamInterface
{
    use CloseableAudioStreamTrait;
    use ReadableAudioStreamTrait;
    use WritableAudioStreamTrait;

    public function __construct(
        private readonly ConnectionInterface $connection,

        public readonly AudioFormat $format,

        ?LoopInterface $loop = null,

        public readonly float $softLimitSeconds = 1.0,

        public readonly float $hardLimitSeconds = 5.0,
    ) {
        $this->loop = $loop ?? Loop::get();

        if (!$this->connection->isReadable()) {
            $this->close();

            return;
        }

        $this->setupClosable();
        $this->setupReadable();
        $this->setupWritable();
    }
}
