<?php

declare(strict_types=1);

use React\Socket\SocketServer;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\DtmfSignal;
use Zete7\React\AudioSocket\AudioStream\DuplexAudioStream;
use Zete7\React\AudioSocket\ConnectionInterface;
use Zete7\React\AudioSocket\Server;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$server = new Server(new SocketServer('tcp://127.0.0.1:9092', [
    'tcp' => [
        'tcp_nodelay' => true,
    ],
]));

$server->on('connection', static function (ConnectionInterface $connection): void {
    $remoteAddress = $connection->getRemoteAddress();
    printf("+ %s connected with UUID %s\n", $remoteAddress, $connection->getUuid()->toRfc4122());

    $connection->on('close', static function () use ($remoteAddress) {
        printf("- %s disconnected\n", $remoteAddress);
    });

    $connection->on('error', static function (Throwable $error) use ($remoteAddress) {
        printf("  %s connection error: %s\n", $remoteAddress, $error->getMessage());
    });

    $connection->on('dtmf', static function (DtmfSignal $signal): void {
        printf("  dtmf signal \"%s\"\n", $signal->value);
    });

    $sourceResource = fopen(__DIR__.'/test.slin', 'r');
    assert(false !== $sourceResource);
    $sourceStream = new ReadableResourceStream($sourceResource, readChunkSize: AudioFormat::Slin->getChunkSize());
    $sourceStream->on('end', static function (): void {
        printf("  source stream ended\n");
    });
    $sourceStream->on('error', static function (Throwable $error): void {
        printf("  source stream error: %s\n", $error->getMessage());
    });
    $sourceStream->on('close', static function (): void {
        printf("  source stream closed\n");
    });

    $audioStream = new DuplexAudioStream($connection, AudioFormat::Slin);
    $audioStream->on('end', static function (): void {
        printf("  audio stream ended\n");
    });
    $audioStream->on('error', static function (Throwable $error): void {
        printf("  audio stream error: %s\n", $error->getMessage());
    });
    $audioStream->on('close', static function (): void {
        printf("  audio stream closed\n");
    });

    $destinationFileName = sprintf('%s.slin', $connection->getUuid()->toRfc4122());
    $destinationResource = fopen(__DIR__.'/'.$destinationFileName, 'w');
    assert(false !== $destinationResource);
    $destinationStream = new WritableResourceStream($destinationResource);
    $destinationStream->on('close', static function (): void {
        printf("  destination stream closed\n");
    });

    $audioStream->on('close', static fn () => $destinationStream->end());

    $sourceStream
        ->pipe($audioStream, ['end' => true])
        ->pipe($destinationStream, ['end' => true])
    ;
});

$server->on('error', static function (Throwable $e) {
    printf("e Error: %s\n", $e->getMessage());
});

printf("Listening on %s\n", $server->getAddress());
