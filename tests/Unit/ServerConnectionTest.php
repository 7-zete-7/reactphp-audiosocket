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
use Zete7\React\AudioSocket\Exception\AsteriskError;
use Zete7\React\AudioSocket\Exception\UnexpectedMessageError;
use Zete7\React\AudioSocket\ServerConnection;
use Zete7\React\AudioSocket\Test\MessageEncoderCaseTrait;
use Zete7\React\AudioSocket\Test\ReactConnectionCaseTrait;
use Zete7\React\AudioSocket\Test\Stub\MessageStub;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
#[PHPUnit\CoversClass(ServerConnection::class)]
final class ServerConnectionTest extends TestCase
{
    use MessageEncoderCaseTrait;
    use ReactConnectionCaseTrait;

    private Uuid $connectionUuid {
        get => $this->connectionUuid ??= Uuid::v7();
    }

    private ServerConnection $connection {
        get => $this->connection ??= (function (): ServerConnection {
            $connection = new ServerConnection(
                connection: $this->reactConnection,
                uuid: $this->connectionUuid,
                messageEncoder: $this->messageEncoder,
            );

            $this->configureConnection($connection);

            return $connection;
        })();

        set(ServerConnection $connection) {
            $this->connection = $connection;
            $this->configureConnection($connection);
        }
    }

    /**
     * @var list<array{ string, AudioFormat }>
     */
    private array $connectionAudioEvents = [];

    /**
     * @var list<DtmfSignal>
     */
    private array $connectionDtmfEvents = [];

    /**
     * @var list<\Throwable>
     */
    private array $connectionErrorEvents = [];

    /**
     * @var int<0, max>
     */
    private int $connectionCloseEvents = 0;

    #[PHPUnit\Test]
    public function testSendHangup(): void
    {
        $reactConnection = $this->requireReactConnectionMock();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertConnectionIsNotClosed();

        $messageEncoder
            ->expects(self::once())
            ->method('encodeMessage')
            ->with(self::equalTo(new HangupMessage()))
            ->willReturn('encoded hangup message')
        ;

        $reactConnection
            ->expects(self::once())
            ->method('end')
            ->with('encoded hangup message')
        ;

        $this->assertConnectionIsWritable();

        $this->connection->sendHangup();

        $this->assertConnectionIsNotWritable();
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
    #[PHPUnit\DataProvider('provideDtmfSignalCases')]
    public function testReceiveDtmfMessage(DtmfSignal $dtmfSignal): void
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
            ->willReturnCallback(static function (&$buffer) use (&$decodeMessageCalls, $dtmfSignal): ?DtmfMessage {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [new DtmfMessage($dtmfSignal), 'second buffer'],
                    2 => [null, ''],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $this->assertConnectionDtmfEmpty();

        $reactConnection->emitData('first buffer');

        $this->assertConnectionIsNotClosed();
        $this->assertConnectionDtmfSignalsAre([$dtmfSignal]);
    }

    #[PHPUnit\Test]
    #[PHPUnit\TestWith([''])]
    #[PHPUnit\TestWith(["\xFA"])]
    #[PHPUnit\TestWith(['foo'])]
    public function testReceiveError(string $errorPayload): void
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
            ->willReturnCallback(static function (&$buffer) use (&$decodeMessageCalls, $errorPayload): ErrorMessage {
                [$message, $buffer] = match (++$decodeMessageCalls) {
                    1 => [new ErrorMessage($errorPayload), ''],
                    default => throw new \LogicException('Extra method call'),
                };

                return $message;
            })
        ;

        $this->assertConnectionErrorAreEmpty();

        $reactConnection->emitData('first buffer');

        $this->assertConnectionIsClosed();
        $this->assertConnectionErrorEventsIs([
            new AsteriskError($errorPayload, \sprintf('Asterisk error 0x%s.', bin2hex($errorPayload))),
        ]);
    }

    #[PHPUnit\Test]
    #[PHPUnit\TestWith([Kind::Hangup])]
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

    private function configureConnection(ServerConnection $connection): void
    {
        $connection->on('audio', function (string $chunk, AudioFormat $format): void {
            $this->connectionAudioEvents[] = [$chunk, $format];
        });

        $connection->on('dtmf', function (DtmfSignal $dtmfSignal): void {
            $this->connectionDtmfEvents[] = $dtmfSignal;
        });

        $connection->on('error', function (\Throwable $error): void {
            $this->connectionErrorEvents[] = $error;
        });

        $connection->on('close', function (): void {
            ++$this->connectionCloseEvents;
        });
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

    private function assertConnectionDtmfEmpty(string $message = ''): void
    {
        self::assertEmpty($this->connectionDtmfEvents, $message);
    }

    /**
     * @param list<DtmfSignal> $expected
     */
    private function assertConnectionDtmfSignalsAre(array $expected, string $message = ''): void
    {
        self::assertSame($expected, $this->connectionDtmfEvents, $message);
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
}
