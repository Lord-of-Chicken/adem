<?php

declare(strict_types=1);

use App\Kernel;
use App\Service\StripeWebhookHandler;
use Symfony\Component\Dotenv\Dotenv;
use Stripe\Event;

require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new Kernel('test', false);
$kernel->boot();
$doctrine = $kernel->getContainer()->get('doctrine');
$connection = $doctrine->getConnection();
if (!$connection instanceof Doctrine\DBAL\Connection) { throw new LogicException('Invalid worker connection.'); }
if (!str_contains($connection->getDatabase() ?? '', '_test')) {
    throw new LogicException('Concurrency fixture requires an isolated test database.');
}
$manager = $doctrine->getManager();
if (!$manager instanceof Doctrine\ORM\EntityManagerInterface) { throw new LogicException('Invalid worker manager.'); }
$handler = new StripeWebhookHandler($manager, $doctrine->getRepository(App\Entity\Order::class), new Psr\Log\NullLogger());
$input = stream_get_contents(STDIN);
$data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($data)) { throw new UnexpectedValueException('Invalid worker input.'); }
$barrier = $argv[1] ?? '';
if (!str_starts_with($barrier, sys_get_temp_dir().'/adem-barrier-')) { throw new LogicException('Invalid barrier.'); }
file_put_contents($barrier.'.'.getmypid().'.ready', 'ready');
$deadline = microtime(true) + 10;
while (!is_file($barrier)) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Barrier timed out.'); }
    usleep(1000);
}
echo $handler->handle(Event::constructFrom($data));
$kernel->shutdown();
