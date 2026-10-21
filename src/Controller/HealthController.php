<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(private Connection $connection, private LoggerInterface $logger) {}

    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->fetchOne('SELECT 1');
            $response = new JsonResponse(['status' => 'ok']);
        } catch (\Doctrine\DBAL\Exception $exception) {
            $this->logger->error('Readiness database check failed', ['error_class' => $exception::class]);
            $response = new JsonResponse(['status' => 'unavailable'], 503);
        }
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
