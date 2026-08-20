<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\AudioMessage;
use Zete7\AudioSocket\Protocol\DtmfMessage;
use Zete7\AudioSocket\Protocol\DtmfSignal;
use Zete7\AudioSocket\Protocol\ErrorMessage;
use Zete7\AudioSocket\Protocol\HangupMessage;
use Zete7\AudioSocket\Protocol\Kind;
use Zete7\AudioSocket\Protocol\UuidMessage;
use Zete7\React\AudioSocket\ClientConnection;
use Zete7\React\AudioSocket\Exception\UnexpectedMessageError;
use Zete7\React\AudioSocket\Test\MessageEncoderCaseTrait;
use Zete7\React\AudioSocket\Test\ReactConnectionCaseTrait;
use Zete7\React\AudioSocket\Test\Stub\MessageStub;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
#[PHPUnit\CoversClass(ClientConnection::class)]
final class ClientConnectionTest extends TestCase
{
    use MessageEncoderCaseTrait;
    use ReactConnectionCaseTrait;

    private Uuid $connectionUuid {
        get => $this->connectionUuid ??= Uuid::v7();
    }

    private ClientConnection $connection {
        get => $this->connection ??= (function (): ClientConnection {
            $connection = new ClientConnection(
                connection: $this->reactConnection,
                uuid: $this->connectionUuid,
                messageEncoder: $this->messageEncoder,
            );

            $this->configureConnection($connection);

            return $connection;
        })();

        set(ClientConnection $connection) {
            $this->connection = $connection;
            $this->configureConnection($connection);
        }
    }

    /**
     * @var list<array{ string, AudioFormat }>
     */
    private array $connectionAudioEvents = [];

    /**
     * @var int<0, max>
     */
    private int $connectionHangupEvents = 0;

    /**
     * @var list<\Throwable>
     */
    private array $connectionErrorEvents = [];

    /**
     * @var int<0, max>
     */
    private int $connectionCloseEvents = 0;

    #[PHPUnit\Test]
    public function testConnectionUuidOnInitialization(): void
    {
        $reactConnection = $this->requireReactConnectionMock();
        $messageEncoder = $this->requireMessageEncoderMock();

        $uuid = Uuid::fromString('c0131f6e-f6d6-4a9f-876b-2c145cc2ec95');

        $messageEncoder
            ->expects(self::once())
            ->method('encodeMessage')
            ->with(self::equalTo(new UuidMessage(
                uuid: $uuid,
            )))
            ->willReturn('encoded uuid message')
        ;

        $reactConnection
            ->expects(self::once())
            ->method('write')
            ->with('encoded uuid message')
            ->willReturn(true)
        ;

        $connection = new ClientConnection(
            connection: $this->reactConnection,
            uuid: $uuid,
            messageEncoder: $this->messageEncoder,
        );

        self::assertSame($uuid, $connection->getUuid());
    }

    #[PHPUnit\Test]
    #[PHPUnit\DataProvider('provideDtmfSignalCases')]
    public function testSendDtmf(DtmfSignal $dtmfSignal): void
    {
        $reactConnection = $this->requireReactConnectionMock();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsNotClosed();

        $messageEncoder
            ->expects(self::once())
            ->method('encodeMessage')
            ->with(self::equalTo(new DtmfMessage($dtmfSignal)))
            ->willReturn('encoded dtmf message')
        ;

        $reactConnection
            ->expects(self::once())
            ->method('write')
            ->with('encoded dtmf message')
            ->willReturn(true)
        ;

        $this->connection->sendDtmf($dtmfSignal);
    }

    #[PHPUnit\Test]
    #[PHPUnit\TestWith([''])]
    #[PHPUnit\TestWith(["\xFA"])]
    #[PHPUnit\TestWith(['foo'])]
    public function testSendError(string $errorPayload): void
    {
        $reactConnection = $this->requireReactConnectionMock();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsNotClosed();

        $messageEncoder
            ->expects(self::once())
            ->method('encodeMessage')
            ->with(self::equalTo(new ErrorMessage($errorPayload)))
            ->willReturn('encoded error message')
        ;

        $reactConnection
            ->expects(self::once())
            ->method('write')
            ->with('encoded error message')
            ->willReturn(true)
        ;

        $this->connection->sendError($errorPayload);
    }

    #[PHPUnit\Test]
    #[PHPUnit\DataProvider('provideAudioFormatCases')]
    public function testReceiveAudioMessage(AudioFormat $audioFormat): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsNotClosed();

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $messageEncoder
            ->expects(self::exactly(2))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
                ['second buffer'],
            )
            ->willReturnCallback(static function (&$buffer) use (&$decodeMessageCalls, $audioFormat): ?AudioMessage {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [new AudioMessage($audioFormat, random_bytes(16)), 'second buffer'],
                    2 => [null, ''],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $this->assertConnectionAudioAreEmpty();

        $reactConnection->emitData('first buffer');

        $this->assertConnectionIsNotClosed();
        $this->assertConnectionAudioCount(1);
    }

    #[PHPUnit\Test]
    public function testReceiveHangupMessage(): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsNotClosed();

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $messageEncoder
            ->expects(self::exactly(1))
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
            )
            ->willReturnCallback(static function (&$buffer) use (&$decodeMessageCalls): HangupMessage {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [new HangupMessage(), ''],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $this->assertConnectionHangupEmpty();

        $reactConnection->emitData('first buffer');

        $this->assertConnectionIsClosed();
        $this->assertConnectionHangupCount(1);
        $this->assertConnectionCloseCount(1);
        $this->assertConnectionErrorAreEmpty();
    }

    #[PHPUnit\Test]
    #[PHPUnit\TestWith([Kind::Uuid])]
    #[PHPUnit\TestWith([Kind::Error])]
    public function testReceiveUnsupportedMessage(Kind $messageKind): void
    {
        $reactConnection = $this->requireReactConnectionStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsNotClosed();

        $message = new MessageStub(
            kind: $messageKind,
            payload: 'some insignificant payload',
        );

        /** @var int<0, max> $decodeMessageCalls */
        $decodeMessageCalls = 0;

        $messageEncoder
            ->expects(self::once())
            ->method('decodeMessage')
            ->withParameterSetsInOrder(
                ['first buffer'],
            )
            ->willReturnCallback(static function (&$buffer) use (&$decodeMessageCalls, $message): MessageStub {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [$message, ''],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $this->assertConnectionErrorAreEmpty();

        $reactConnection->emitData('first buffer');

        self::assertSame(1, $decodeMessageCalls);
        $this->assertConnectionIsClosed();
        $this->assertConnectionErrorEventsIs([
            new UnexpectedMessageError($message, \sprintf('Unexpected message kind "%s" received.', $messageKind->name)),
        ]);
    }

    /**
     * @return iterable<string, array{ DtmfSignal }>
     */
    public static function provideDtmfSignalCases(): iterable
    {
        foreach (DtmfSignal::cases() as $dtmfSignal) {
            yield $dtmfSignal->name => [$dtmfSignal];
        }
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

    private function configureConnection(ClientConnection $connection): void
    {
        $connection->on('audio', function (string $chunk, AudioFormat $format): void {
            $this->connectionAudioEvents[] = [$chunk, $format];
        });

        $connection->on('hangup', function (): void {
            ++$this->connectionHangupEvents;
        });

        $connection->on('error', function (\Throwable $error): void {
            $this->connectionErrorEvents[] = $error;
        });

        $connection->on('close', function (): void {
            ++$this->connectionCloseEvents;
        });
    }

    private function assertConnectionIsClosed(): void
    {
        self::assertTrue($this->connection->isClosed());
    }

    private function assertConnectionIsNotClosed(): void
    {
        self::assertFalse($this->connection->isClosed());
    }

    private function assertConnectionAudioAreEmpty(string $message = ''): void
    {
        self::assertEmpty($this->connectionAudioEvents, $message);
    }

    /**
     * @param int<0, max> $expected
     */
    private function assertConnectionAudioCount(int $expected, string $message = ''): void
    {
        self::assertCount($expected, $this->connectionAudioEvents, $message);
    }

    private function assertConnectionHangupEmpty(string $message = ''): void
    {
        self::assertSame(0, $this->connectionHangupEvents, $message);
    }

    /**
     * @param int<0, max> $expected
     */
    private function assertConnectionHangupCount(int $expected, string $message = ''): void
    {
        self::assertSame($expected, $this->connectionHangupEvents, $message);
    }

    private function assertConnectionErrorAreEmpty(string $message = ''): void
    {
        self::assertEmpty($this->connectionErrorEvents, $message);
    }

    /**
     * @param list<\Throwable> $expected
     */
    private function assertConnectionErrorEventsIs(array $expected, string $message = ''): void
    {
        self::assertEquals($expected, $this->connectionErrorEvents, $message);
    }

    /**
     * @param int<0, max> $expected
     */
    private function assertConnectionCloseCount(int $expected, string $message = ''): void
    {
        self::assertSame($expected, $this->connectionCloseEvents, $message);
    }
}
