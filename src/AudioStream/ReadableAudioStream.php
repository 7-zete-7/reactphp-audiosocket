<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\AudioStream;

use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\React\AudioSocket\ConnectionInterface;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class ReadableAudioStream implements ReadableAudioStreamInterface
{
    use CloseableAudioStreamTrait;
    use ReadableAudioStreamTrait;

    public function __construct(
        private readonly ConnectionInterface $connection,

        public readonly AudioFormat $format,
    ) {
        if (!$this->connection->isReadable()) {
            $this->close();

            return;
        }

        $this->setupClosable();
        $this->setupReadable();
    }
}
