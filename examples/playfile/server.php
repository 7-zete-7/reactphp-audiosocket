<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\TcpServer;
use React\Stream\CompositeStream;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use Symfony\Component\Uid\Uuid;
use Zete7\React\AudioSocket\Client;
use Zete7\React\AudioSocket\ReceiveSlinStream;
use Zete7\React\AudioSocket\SendSlinStream;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$server = new TcpServer('0.0.0.0:8080');

$server->on('connection', static function (ConnectionInterface $connection): void {
    $remoteAddress = $connection->getRemoteAddress();
    printf("+ %s connected\n", $remoteAddress);

    $connection->on('close', static function () use ($remoteAddress) {
        printf("- %s disconnected\n", $remoteAddress);
    });

    $client = new Client($connection);

    $slinStream = new CompositeStream(new ReceiveSlinStream($client), new SendSlinStream($client));

    $client->getId()->then(static function (Uuid $id) use ($slinStream, $remoteAddress): void {
        printf("  %s calls ID %s\n", $remoteAddress, $id->toRfc4122());

        $receivedSlinFileName = \sprintf('%s.slin', $id->toRfc4122());
        $receivedSlinFileStream = new WritableResourceStream(\fopen(__DIR__.'/'.$receivedSlinFileName, 'wb'));

        $slinStream->pipe($receivedSlinFileStream, ['end' => true]);

        Loop::addTimer(3, static function () use ($slinStream): void {
            $sendSlinFileStream = new ReadableResourceStream(\fopen(__DIR__.'/test.slin', 'rb'));

            $sendSlinFileStream->pipe($slinStream, ['end' => true]);
        });
    }, static function (\Throwable $e) use ($remoteAddress): void {
        printf("  %s failed to get ID: %s\n", $remoteAddress, $e->getMessage());
    });
});

$server->on('error', static function (Throwable $e) {
    printf("e Error: %s\n", $e->getMessage());
});

printf("Listening on %s\n", $server->getAddress());
