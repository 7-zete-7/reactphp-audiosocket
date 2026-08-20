<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\AudioMessage;
use Zete7\AudioSocket\Protocol\Message;
use Zete7\React\AudioSocket\AbstractConnection;
use Zete7\React\AudioSocket\Exception\UnprocessedBufferError;
use Zete7\React\AudioSocket\Test\Connection;
use Zete7\React\AudioSocket\Test\MessageEncoderCaseTrait;
use Zete7\React\AudioSocket\Test\ReactConnectionCaseTrait;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
#[PHPUnit\CoversClass(AbstractConnection::class)]
final class ConnectionTest extends TestCase
{
    use MessageEncoderCaseTrait;
    use ReactConnectionCaseTrait;

    private Uuid $connectionUuid {
        get => $this->connectionUuid ??= Uuid::v7();
    }

    private string $connectionInitialBuffer = '';

    /**
     * @var int<1, max>
     */
    private int $connectionMaxBufferSize = AbstractConnection::DEFAULT_MAX_BUFFER_SIZE;

    /**
     * @var int<1, max>
     */
    private int $connectionMaxQueueSize = AbstractConnection::DEFAULT_MAX_QUEUE_SIZE;

    private Connection $connection {
        get => $this->connection ??= (function (): Connection {
            $connection = new Connection(
                connection: $this->reactConnection,
                uuid: $this->connectionUuid,
                buffer: $this->connectionInitialBuffer,
                messageEncoder: $this->messageEncoder,
                maxBufferSize: $this->connectionMaxBufferSize,
                maxQueueSize: $this->connectionMaxQueueSize,
            );

            $this->configureConnection($connection);

            return $connection;
        })();

        set(Connection $connection) {
            $this->connection = $connection;
            $this->configureConnection($connection);
        }
    }

    /**
     * @var int<0, max>
     */
    private int $connectionCloseEvents = 0;

    /**
     * @var list<\Throwable>
     */
    private array $connectionErrorEvents = [];

    /**
     * @var list<Message>
     */
    private array $connectionMessageEvents = [];

    #[PHPUnit\Test]
    public function testEarlyNonReadableReactConnection(): void
    {
        $this->reactConnectionReadable = false;

        self::assertTrue($this->connection->isClosed());
        $this->assertConnectionIsNotReadable();
        self::assertFalse($this->connection->isWritable());
        $this->assertConnectionIsNotInitialized();
    }

    #[PHPUnit\Test]
    public function testEarlyNonWritableReactConnection(): void
    {
        $this->reactConnectionWritable = false;

        self::assertTrue($this->connection->isClosed());
        $this->assertConnectionIsNotReadable();
        self::assertFalse($this->connection->isWritable());
        $this->assertConnectionIsNotInitialized();
    }

    #[PHPUnit\Test]
    public function testInitialState(): void
    {
        self::assertFalse($this->connection->isClosed());
        $this->assertConnectionIsReadable();
        self::assertTrue($this->connection->isWritable());
        $this->assertConnectionIsInitialized();
        self::assertSame(0, $this->connectionCloseEvents);
        self::assertEmpty($this->connectionErrorEvents);
        self::assertEmpty($this->connectionMessageEvents);
    }

    #[PHPUnit\Test]
    public function testErrorEventForwarding(): void
    {
        $reactConnection = $this->requireReactConnectionStub();

        $this->assertConnectionIsInitialized();

        $error = new \RuntimeException('69873d41-ced4-4b27-bef1-271d2a66af2e');
        $reactConnection->emitError($error);

        self::assertSame([$error], $this->connectionErrorEvents);
    }

    #[PHPUnit\Test]
    public function testRemoteAddressForwarding(): void
    {
        $this->reactConnectionRemoteAddress = '5046b28d-2339-4e27-a376-717a6eabff45';

        $this->assertConnectionIsInitialized();

        self::assertSame('5046b28d-2339-4e27-a376-717a6eabff45', $this->connection->getRemoteAddress());
    }

    #[PHPUnit\Test]
    public function testLocalAddressForwarding(): void
    {
        $this->reactConnectionLocalAddress = '2753aa39-49b0-4479-9437-91c607f3c975';

        $this->assertConnectionIsInitialized();

        self::assertSame('2753aa39-49b0-4479-9437-91c607f3c975', $this->connection->getLocalAddress());
    }

    #[PHPUnit\Test]
    public function testConnectionClose(): void
    {
        $initialBuffer = "\x01\x81\xEE\x5B\x8B\x7C\x47\x8C\x82\x53\x9E\xCB\xEE\xCF\x48\x35";

        $reactConnection = $this->requireReactConnectionMock();

        $this->connection = new Connection(
            connection: $this->reactConnection,
            uuid: Uuid::fromString('679cb916-dba8-49e6-b0ca-c2c06d8da8d8'),
            buffer: $initialBuffer,
        );

        $this->assertConnectionIsInitialized();
        $this->assertConnectionIsNotClosed();
        $this->assertConnectionIsReadable();
        $this->assertConnectionIsWritable();
        $this->assertConnectionBufferContentIs($initialBuffer);
        $this->assertConnectionCloseCount(0);

        $reactConnection
            ->expects(self::once())
            ->method('close')
        ;

        $this->connection->close();

        $this->assertConnectionIsClosed();
        $this->assertConnectionIsNotReadable();
        $this->assertConnectionIsNotWritable();
        $this->assertConnectionBufferIsEmpty();
        $this->assertConnectionQueueIsEmpty();
        $this->assertConnectionErrorEventsAre([
            new UnprocessedBufferError('AudioSocket server connection was closed with non-empty buffer.'),
        ]);
        $this->assertConnectionCloseCount(1);

        $this->connection->close();

        $this->assertConnectionBufferIsEmpty();
        $this->assertConnectionQueueIsEmpty();
        $this->assertConnectionErrorEventsAre([
            new UnprocessedBufferError('AudioSocket server connection was closed with non-empty buffer.'),
        ]);
        $this->assertConnectionCloseCount(1);

        $this->connection->emit('close');

        $this->assertConnectionBufferIsEmpty();
        $this->assertConnectionQueueIsEmpty();
        $this->assertConnectionErrorEventsAre([
            new UnprocessedBufferError('AudioSocket server connection was closed with non-empty buffer.'),
        ]);
        $this->assertConnectionCloseCount(1);
    }

    #[PHPUnit\Test]
    public function testConnectionCloseWhenReactConnectionClosed(): void
    {
        $reactConnection = $this->requireReactConnectionStub();

        $this->assertConnectionIsInitialized();
        $this->assertConnectionIsReadable();
        $this->assertConnectionCloseCount(0);
        $this->assertConnectionErrorAreEmpty();

        $reactConnection->emitClose();

        $this->assertConnectionIsNotReadable();
        $this->assertConnectionCloseCount(1);
        $this->assertConnectionErrorAreEmpty();

        $reactConnection->emitClose();

        $this->assertConnectionIsNotReadable();
        $this->assertConnectionCloseCount(1);
        $this->assertConnectionErrorAreEmpty();
    }

    #[PHPUnit\Test]
    public function testConnectionEmitQueuedMessagesBeforeClose(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        $reactConnection->emitEnd();

        $this->connection->pause();

        $this->assertConnectionIsPaused();

        $firstMessage = self::createStub(Message::class);
        $secondMessage = self::createStub(Message::class);

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $messageEncoder
            ->expects(self::exactly(3))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
                ['second buffer'],
                ['third buffer'],
            )
            ->willReturnCallback(static function (string &$buffer) use (&$decodeMessageCalls, $firstMessage, $secondMessage): ?Message {
                [$result, $buffer] = match (++$decodeMessageCalls) {
                    1 => [$firstMessage, 'second buffer'],
                    2 => [$secondMessage, 'third buffer'],
                    3 => [null, ''],
                    default => throw new \LogicException('Extra method call'),
                };

                return $result;
            })
        ;

        $reactConnection->emitData('first buffer');

        self::assertSame(3, $decodeMessageCalls);
        $this->assertConnectionIsPaused();
        $this->assertConnectionIsReadable();
        $this->assertConnectionIsWritable();
        $this->assertConnectionCloseCount(0);
        $this->assertConnectionQueueItemsAre([$firstMessage, $secondMessage]);

        $reactConnection->setReadable(false);
        $reactConnection->setWritable(false);
        $reactConnection->emitClose();

        $this->assertConnectionIsNotClosed();
        $this->assertConnectionIsReadable();
        $this->assertConnectionIsPaused();
        $this->assertConnectionIsNotWritable();
        $this->assertConnectionCloseCount(0);
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionQueueItemsAre([$firstMessage, $secondMessage]);
        $this->assertConnectionMessageAreEmpty();

        $this->connection->once('message', $this->connection->pause(...));
        $this->connection->resume();

        $this->assertConnectionIsNotClosed();
        $this->assertConnectionIsReadable();
        $this->assertConnectionIsPaused();
        $this->assertConnectionCloseCount(0);
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionQueueItemsAre([$secondMessage]);
        $this->assertConnectionMessageEventsAre([$firstMessage]);

        $this->connection->once('message', $this->connection->pause(...));
        $this->connection->resume();

        $this->assertConnectionIsClosed();
        $this->assertConnectionIsNotReadable();
        $this->assertConnectionCloseCount(1);
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionQueueItemsAre([]);
        $this->assertConnectionMessageEventsAre([$firstMessage, $secondMessage]);

        $this->connection->once('message', $this->connection->pause(...));
        $this->connection->resume();

        $this->assertConnectionCloseCount(1);
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionMessageEventsAre([$firstMessage, $secondMessage]);
    }

    #[PHPUnit\Test]
    public function testConnectionCloseForEmptyQueueWhenReactConnectionEnd(): void
    {
        $reactConnection = $this->requireReactConnectionStub();

        $this->assertConnectionIsInitialized();

        $reactConnection->emitEnd();

        $this->assertConnectionIsReadable();
        $this->assertConnectionIsWritable();
        $this->assertConnectionQueueIsEmpty();
        $this->assertConnectionCloseCount(0);

        $reactConnection->emitClose();

        $this->assertConnectionIsClosed();
        $this->assertConnectionIsNotReadable();
        $this->assertConnectionIsNotWritable();
        $this->assertConnectionCloseCount(1);
        $this->assertConnectionErrorAreEmpty();
    }

    #[PHPUnit\Test]
    public function testConnectionDropQueueWhenReactConnectionClosesWithoutEnd(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        $this->connection->pause();

        $this->assertConnectionIsPaused();

        $message = self::createStub(Message::class);

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $messageEncoder
            ->expects(self::exactly(2))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
                ['second buffer'],
            )
            ->willReturnCallback(static function (string &$buffer) use (&$decodeMessageCalls, $message): ?Message {
                [$result, $buffer] = match (++$decodeMessageCalls) {
                    1 => [$message, 'second buffer'],
                    2 => [null, ''],
                    default => throw new \LogicException('Extra method call'),
                };

                return $result;
            })
        ;

        $reactConnection->emitData('first buffer');

        $this->assertConnectionIsReadable();
        $this->assertConnectionCloseCount(0);
        $this->assertConnectionQueueItemsAre([$message]);
        $this->assertConnectionMessageAreEmpty();

        $reactConnection->setReadable(false);
        $reactConnection->setWritable(false);
        $reactConnection->emitClose();

        $this->connection->resume();

        $this->assertConnectionIsNotReadable();
        $this->assertConnectionCloseCount(1);
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionQueueIsEmpty();
        $this->assertConnectionMessageAreEmpty();
    }

    #[PHPUnit\Test]
    public function testConnectionUuid(): void
    {
        $this->connectionUuid = $uuid = Uuid::fromString('7d2a47fb-d933-49bf-a35d-c8efd19dd8c5');

        $this->assertConnectionIsInitialized();

        self::assertSame($uuid, $this->connection->getUuid());
    }

    #[PHPUnit\Test]
    public function testPauseOnReadableConnection(): void
    {
        $reactConnection = $this->requireReactConnectionMock();

        $this->assertConnectionIsInitialized();
        $this->assertConnectionIsNotPaused();

        $reactConnection
            ->expects(self::once())
            ->method('pause')
        ;

        $this->connection->pause();

        $this->assertConnectionIsPaused();
    }

    #[PHPUnit\Test]
    public function testPauseOnNonReadableConnection(): void
    {
        $reactConnection = $this->requireReactConnectionMock();

        $this->assertConnectionIsInitialized();
        $this->assertConnectionIsNotPaused();

        $this->connection->close();

        $this->assertConnectionIsNotReadable();

        $reactConnection
            ->expects(self::never())
            ->method('pause')
        ;

        $this->connection->pause();

        $this->assertConnectionIsNotPaused();
    }

    #[PHPUnit\Test]
    public function testResumeOnReadableConnection(): void
    {
        $reactConnection = $this->requireReactConnectionMock();

        $this->assertConnectionIsInitialized();

        $this->connection->pause();

        $this->assertConnectionIsReadable();
        $this->assertConnectionIsPaused();

        $reactConnection
            ->expects(self::once())
            ->method('resume')
        ;

        $this->connection->resume();

        $this->assertConnectionIsNotPaused();
    }

    #[PHPUnit\Test]
    public function testResumeOnNonReadableConnection(): void
    {
        $reactConnection = $this->requireReactConnectionMock();

        $this->assertConnectionIsInitialized();

        $this->connection->pause();
        $this->connection->close();

        $this->assertConnectionIsNotReadable();
        $this->assertConnectionIsPaused();

        $reactConnection
            ->expects(self::never())
            ->method('resume')
        ;

        $this->connection->resume();

        $this->assertConnectionIsPaused();
    }

    #[PHPUnit\Test]
    public function testBufferToNonReadableConnection(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        $this->connection->close();

        $this->assertConnectionIsNotReadable();

        $messageEncoder
            ->expects(self::never())
            ->method('decodeMessage')
        ;

        $reactConnection->emitData("\xE8\x61\x5F\xB9\x82\xE3\x4E\x1B\x8D\x6E\x43\xF9\x02\x54\x7C\xD6");

        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionMessageAreEmpty();
        $this->assertConnectionBufferIsEmpty();
        $this->assertConnectionQueueIsEmpty();
    }

    #[PHPUnit\Test]
    public function testTooShortBuffer(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        $messageEncoder
            ->expects(self::exactly(3))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                [''],
                ['1'],
                ['12'],
            )
            ->willReturn(null)
        ;

        $reactConnection->emitData('');
        $reactConnection->emitData('1');
        $reactConnection->emitData('2');

        $this->assertConnectionIsReadable();
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionMessageAreEmpty();
        $this->assertConnectionBufferContentIs('12');
        $this->assertConnectionQueueIsEmpty();
    }

    #[PHPUnit\Test]
    public function testOneMessageBySingleBuffer(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $oneMessage = self::createStub(Message::class);

        $messageEncoder
            ->expects(self::exactly(2))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
                ['second buffer'],
            )
            ->willReturnCallback(static function (string &$buffer) use (&$decodeMessageCalls, $oneMessage): ?Message {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [$oneMessage, 'second buffer'],
                    2 => [null, 'final buffer'],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $reactConnection->emitData('first buffer');

        self::assertSame(2, $decodeMessageCalls);
        $this->assertConnectionIsReadable();
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionMessageEventsAre([$oneMessage]);
        $this->assertConnectionBufferContentIs('final buffer');
        $this->assertConnectionQueueIsEmpty();
    }

    #[PHPUnit\Test]
    public function testTwoMessagesBySingleBuffer(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        $firstMessage = self::createStub(Message::class);
        $secondMessage = self::createStub(Message::class);

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $messageEncoder
            ->expects(self::exactly(3))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
                ['second buffer'],
                ['third buffer'],
            )
            ->willReturnCallback(static function (string &$buffer) use (&$decodeMessageCalls, $firstMessage, $secondMessage): ?Message {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [$firstMessage, 'second buffer'],
                    2 => [$secondMessage, 'third buffer'],
                    3 => [null, 'final buffer'],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $reactConnection->emitData('first buffer');

        self::assertSame(3, $decodeMessageCalls);
        $this->assertConnectionIsReadable();
        $this->assertConnectionMessageEventsAre([$firstMessage, $secondMessage]);
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionBufferContentIs('final buffer');
        $this->assertConnectionQueueIsEmpty();
    }

    #[PHPUnit\Test]
    public function testOneMessageByMultipleBuffers(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        $message = self::createStub(Message::class);

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $messageEncoder
            ->expects(self::exactly(3))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
                ['second buffer+suffix'],
                ['third buffer'],
            )
            ->willReturnCallback(static function (string &$buffer) use (&$decodeMessageCalls, $message): ?Message {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [null, 'second buffer'],
                    2 => [$message, 'third buffer'],
                    3 => [null, 'final buffer'],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $reactConnection->emitData('first buffer');

        self::assertSame(1, $decodeMessageCalls);
        $this->assertConnectionMessageAreEmpty();
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionBufferContentIs('second buffer');
        $this->assertConnectionQueueIsEmpty();

        $reactConnection->emitData('+suffix');

        self::assertSame(3, $decodeMessageCalls); // @phpstan-ignore staticMethod.impossibleType(variable changes during ReactConnection::emitData() call)
        $this->assertConnectionIsReadable();
        $this->assertConnectionMessageEventsAre([$message]);
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionBufferContentIs('final buffer');
        $this->assertConnectionQueueIsEmpty();
    }

    #[PHPUnit\Test]
    public function testMessagesWithPause(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        $this->connection->pause();

        $this->assertConnectionIsPaused();
        $this->assertConnectionBufferIsEmpty();
        $this->assertConnectionQueueIsEmpty();

        $firstMessage = self::createStub(Message::class);
        $secondMessage = self::createStub(Message::class);

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $messageEncoder
            ->expects(self::exactly(3))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
                ['second buffer'],
                ['third buffer'],
            )
            ->willReturnCallback(static function (string &$buffer) use (&$decodeMessageCalls, $firstMessage, $secondMessage): ?Message {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [$firstMessage, 'second buffer'],
                    2 => [$secondMessage, 'third buffer'],
                    3 => [null, 'final buffer'],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $reactConnection->emitData('first buffer');

        self::assertSame(3, $decodeMessageCalls);
        $this->assertConnectionIsReadable();
        $this->assertConnectionIsPaused();
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionMessageAreEmpty();
        $this->assertConnectionBufferContentIs('final buffer');
        $this->assertConnectionQueueItemsAre([$firstMessage, $secondMessage]);

        $this->connection->once('message', $this->connection->pause(...));
        $this->connection->resume();

        $this->assertConnectionIsReadable();
        $this->assertConnectionIsPaused();
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionMessageEventsAre([$firstMessage]);
        $this->assertConnectionBufferContentIs('final buffer');
        $this->assertConnectionQueueItemsAre([$secondMessage]);

        $this->connection->once('message', $this->connection->pause(...));
        $this->connection->resume();

        $this->assertConnectionIsReadable();
        $this->assertConnectionIsPaused();
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionMessageEventsAre([$firstMessage, $secondMessage]);
        $this->assertConnectionBufferContentIs('final buffer');
        $this->assertConnectionQueueIsEmpty();

        $this->connection->resume();

        $this->assertConnectionIsReadable();
        $this->assertConnectionIsNotPaused();
        $this->assertConnectionErrorAreEmpty();
        $this->assertConnectionMessageEventsAre([$firstMessage, $secondMessage]);
        $this->assertConnectionBufferContentIs('final buffer');
        $this->assertConnectionQueueIsEmpty();
    }

    #[PHPUnit\Test]
    #[PHPUnit\DataProvider('provideAudioFormatCases')]
    public function testSendAudio(AudioFormat $audioFormat): void
    {
        $reactConnection = $this->requireReactConnectionMock();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsInitialized();

        $audioChunk = random_bytes(16);

        $messageEncoder
            ->expects(self::once())
            ->method('encodeMessage')
            ->with(self::equalTo(new AudioMessage(
                audioFormat: $audioFormat,
                payload: $audioChunk,
            )))
            ->willReturn('encoded audio message')
        ;

        $reactConnection
            ->expects(self::once())
            ->method('write')
            ->with('encoded audio message')
            ->willReturn(true)
        ;

        $this->connection->sendAudio($audioFormat, $audioChunk);
    }

    /**
     * @return iterable<string, array{ AudioFormat }>
     */
    public static function provideAudioFormatCases(): iterable
    {
        foreach (AudioFormat::cases() as $audioFormat) {
            yield $audioFormat->name => [$audioFormat];
        }
    }

    private function configureConnection(Connection $connection): void
    {
        $connection->on('close', function (): void {
            ++$this->connectionCloseEvents;
        });

        $connection->on('error', function (\Throwable $error): void {
            $this->connectionErrorEvents[] = $error;
        });

        $connection->on('message', function (Message $message): void {
            $this->connectionMessageEvents[] = $message;
        });
    }

    private function assertConnectionIsReadable(): void
    {
        self::assertTrue($this->connection->isReadable());
    }

    private function assertConnectionIsNotReadable(): void
    {
        self::assertFalse($this->connection->isReadable());
    }

    private function assertConnectionIsWritable(): void
    {
        self::assertTrue($this->connection->isWritable());
    }

    private function assertConnectionIsNotWritable(): void
    {
        self::assertFalse($this->connection->isWritable());
    }

    private function assertConnectionIsClosed(): void
    {
        self::assertTrue($this->connection->isClosed());
    }

    private function assertConnectionIsNotClosed(): void
    {
        self::assertFalse($this->connection->isClosed());
    }

    private function assertConnectionIsInitialized(): void
    {
        self::assertTrue($this->connection->isInitialized());
    }

    private function assertConnectionIsNotInitialized(): void
    {
        self::assertFalse($this->connection->isInitialized());
    }

    private function assertConnectionIsPaused(): void
    {
        self::assertTrue($this->connection->isPaused());
    }

    private function assertConnectionIsNotPaused(): void
    {
        self::assertFalse($this->connection->isPaused());
    }

    private function assertConnectionCloseCount(int $expected, string $message = ''): void
    {
        self::assertSame($expected, $this->connectionCloseEvents, $message);
    }

    private function assertConnectionErrorAreEmpty(string $message = ''): void
    {
        self::assertEmpty($this->connectionErrorEvents, $message);
    }

    /**
     * @param list<\Throwable> $expected
     */
    private function assertConnectionErrorEventsAre(array $expected, string $message = ''): void
    {
        self::assertEquals($expected, $this->connectionErrorEvents, $message);
    }

    private function assertConnectionMessageAreEmpty(string $message = ''): void
    {
        self::assertEmpty($this->connectionMessageEvents, $message);
    }

    /**
     * @param list<Message> $expected
     */
    private function assertConnectionMessageEventsAre(array $expected, string $message = ''): void
    {
        self::assertEquals($expected, $this->connectionMessageEvents, $message);
    }

    private function assertConnectionBufferIsEmpty(string $message = ''): void
    {
        self::assertSame('', $this->connection->getBuffer(), $message);
    }

    private function assertConnectionBufferContentIs(string $expected, string $message = ''): void
    {
        self::assertSame($expected, $this->connection->getBuffer(), $message);
    }

    private function assertConnectionQueueIsEmpty(string $message = ''): void
    {
        self::assertEmpty($this->connection->getQueue(), $message);
    }

    /**
     * @param list<Message> $expected
     */
    private function assertConnectionQueueItemsAre(array $expected, string $message = ''): void
    {
        self::assertEquals($expected, $this->connection->getQueue(), $message);
    }
}
