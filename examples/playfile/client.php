<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use React\Socket\TcpConnector;
use React\Stream\WritableResourceStream;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\DtmfSignal;
use Zete7\React\AudioSocket\AudioStream\AudioStreamInterface;
use Zete7\React\AudioSocket\AudioStream\ReadableAudioStream;
use Zete7\React\AudioSocket\ClientConnection;

use function React\Async\await;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$connector = new TcpConnector(context: [
    'tcp' => [
        'tcp_nodelay' => true,
    ],
]);

$uuid = Uuid::v7();

printf("Connecting to tcp://127.0.0.1:9092 with UUID %s\n", $uuid->toRfc4122());

$connection = await($connector->connect('tcp://127.0.0.1:9092'));
$client = new ClientConnection($connection, $uuid);

printf("+ connected\n");

$client->on('close', static function (): void {
    printf("- disconnected\n");
});
$client->on('hangup', static function (): void {
    printf("  connection hangup\n");
});

$audioStream = new ReadableAudioStream($client, AudioFormat::Slin);
$audioStream->on('end', static function (): void {
    printf("  audio stream ended\n");
});
$audioStream->on('error', static function (Throwable $error): void {
    printf("  audio stream error: %s\n", $error->getMessage());
});
$audioStream->on('close', static function (): void {
    printf("  audio stream closed\n");
});

$destinationResource = fopen(__DIR__.'/client_result.slin', 'w');
assert(false !== $destinationResource);
$destinationStream = new WritableResourceStream($destinationResource);
$destinationStream->on('error', static function (Throwable $error): void {
    printf("  destination stream error: %s\n", $error->getMessage());
});
$destinationStream->on('close', static function (): void {
    printf("  destination stream closed\n");
});

$audioStream
    ->pipe($destinationStream, ['end' => true])
;

$silenceSendingTimer = Loop::addPeriodicTimer(AudioStreamInterface::CHUNK_DURATION, static function () use ($client): void {
    $client->sendAudio(AudioFormat::Slin, str_repeat("\0", AudioFormat::Slin->getChunkSize()));
});
$client->on('close', static fn () => Loop::cancelTimer($silenceSendingTimer));

Loop::addTimer(2, static fn () => $client->sendDtmf(DtmfSignal::Star));
Loop::addTimer(3, static fn () => $client->sendDtmf(DtmfSignal::Square));
