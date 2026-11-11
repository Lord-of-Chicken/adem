<?php

declare(strict_types=1);

use App\Entity\Order;
use App\Kernel;
use App\Repository\OrderRepository;
use App\Service\PaymentReceiptEmailFactory;
use App\Service\PaymentReceiptSender;
use App\Service\StripeReceiptProvider;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) !== 'test') {
    throw new LogicException('Receipt worker requires APP_ENV=test.');
}
$kernel = new Kernel('test', false);
$kernel->boot();
$doctrine = $kernel->getContainer()->get('doctrine');
$connection = $doctrine->getConnection();
if (!$connection instanceof Connection || !str_contains($connection->getDatabase() ?? '', '_test')) {
    throw new LogicException('Receipt concurrency fixture requires an isolated test database.');
}
$manager = $doctrine->getManager();
$orders = $doctrine->getRepository(Order::class);
if (!$manager instanceof EntityManagerInterface || !$orders instanceof OrderRepository) {
    throw new LogicException('Invalid receipt worker repositories.');
}
$barrier = $argv[1] ?? '';
$orderId = $argv[2] ?? '';
if (!str_starts_with($barrier, sys_get_temp_dir().'/adem-receipt-barrier-') || !ctype_digit($orderId)) {
    throw new LogicException('Invalid receipt worker arguments.');
}
$provider = new readonly class extends StripeReceiptProvider {
    public function __construct() {}

    /** @return array<string, mixed> */
    public function fetch(Order $order): array
    {
        return [
            'receipt_url' => 'https://pay.stripe.com/receipts/payment/concurrency-fixture',
            'amount_cents' => $order->getTotalCents(), 'currency' => 'eur', 'livemode' => false,
            'session_id' => $order->getStripeCheckoutSessionId(), 'payment_intent_id' => $order->getStripePaymentIntentId(),
            'charge_id' => 'ch_concurrency_fixture', 'customer_email' => 'fixture@example.test',
            'lines' => [['title' => 'Fixture', 'quantity' => 1, 'unit_amount_cents' => $order->getTotalCents(), 'total_cents' => $order->getTotalCents()]],
        ];
    }
};
$mailer = new class($barrier.'.deliveries') implements MailerInterface {
    public function __construct(private readonly string $deliveryFile) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if (!$message instanceof Email) { throw new LogicException('Unexpected receipt message.'); }
        // Simulate a slow accepted SMTP message while the first worker owns the lock.
        usleep(150000);
        file_put_contents($this->deliveryFile, json_encode([
            'to' => $message->getTo()[0]->getAddress(), 'text' => $message->getTextBody(),
        ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    }
};
$sender = new PaymentReceiptSender($manager, $orders, $provider, new PaymentReceiptEmailFactory('site@example.test'), $mailer, new NullLogger(), true);
file_put_contents($barrier.'.'.getmypid().'.ready', 'ready');
$deadline = microtime(true) + 10;
while (!is_file($barrier)) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Receipt barrier timed out.'); }
    usleep(1000);
}
echo $sender->sendForOrder((int) $orderId) ? 'sent' : 'pending';
$kernel->shutdown();
