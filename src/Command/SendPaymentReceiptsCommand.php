<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PaymentReceiptSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:send-payment-receipts', description: 'Retry pending site payment receipt emails')]
final class SendPaymentReceiptsCommand extends Command
{
    public function __construct(private readonly PaymentReceiptSender $sender) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum notifications per run (1–500)', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->sender->isEnabled()) {
            $io->error('Receipt delivery is disabled. Configure a real mail transport and enable PAYMENT_RECEIPTS_SEND_ENABLED.');
            return Command::FAILURE;
        }
        $raw = $input->getOption('limit');
        $limit = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]) : false;
        if (!is_int($limit)) { $io->error('Limit must be an integer between 1 and 500.'); return Command::INVALID; }
        $result = $this->sender->sendPending($limit);
        $io->text(sprintf('%d sent, %d still pending in this batch.', $result['sent'], $result['pending']));
        return $result['pending'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
