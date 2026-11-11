<?php

declare(strict_types=1);
namespace App\Tests\Service;
use App\Service\SensitiveLogProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
final class SensitiveLogProcessorTest extends TestCase
{
    public function testRedactsBufferedRouteParametersCredentialsAndExceptions(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'request', Level::Error, 'Failure for person@example.test /fr/reset-password/private-token', [
            'route_parameters' => ['token' => 'private-token', 'id' => 42],
            'request_uri' => '/fr/newsletter/confirmer/private-token?session_id=cs_private',
            'password' => 'private-password',
            'exception' => new \RuntimeException('private message and person@example.test'),
            'params' => ['person@example.test'],
        ]);
        $safe = (new SensitiveLogProcessor())($record);
        self::assertStringNotContainsString('person@example.test', $safe->message);
        self::assertStringNotContainsString('private-token', $safe->message);
        self::assertSame(['token' => '[redacted]', 'id' => 42], $safe->context['route_parameters']);
        self::assertSame('[redacted]', $safe->context['password']);
        self::assertSame('/fr/newsletter/confirmer/[redacted]?session_id=[redacted]', $safe->context['request_uri']);
        self::assertSame(['[redacted-email]'], $safe->context['params']);
        self::assertIsArray($safe->context['exception']);
        self::assertSame(\RuntimeException::class, $safe->context['exception']['error_class']);
        self::assertArrayNotHasKey('message', $safe->context['exception']);
        self::assertSame('private-password', $record->context['password']);
    }
}
