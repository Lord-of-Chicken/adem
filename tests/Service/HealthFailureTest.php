<?php

declare(strict_types=1);
namespace App\Tests\Service;
use App\Controller\HealthController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
final class HealthFailureTest extends TestCase
{
    public function testDatabaseFailureReturnsGenericUnavailableWithoutSecrets(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willThrowException(new InvalidArgumentException('sensitive connection detail'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Readiness database check failed', ['error_class' => InvalidArgumentException::class]);
        $response = (new HealthController($connection, $logger))();
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('{"status":"unavailable"}', $response->getContent());
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }
}
