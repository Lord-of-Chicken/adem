<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates a new admin user',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The user email')
            ->addArgument('password', InputArgument::REQUIRED, 'The user password')
            ->addOption('first-name', null, InputOption::VALUE_OPTIONAL, 'The user first name')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'The user last name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emailArg = $input->getArgument('email');
        $passwordArg = $input->getArgument('password');
        $email = is_string($emailArg) ? $emailArg : '';
        $password = is_string($passwordArg) ? $passwordArg : '';
        $firstNameOption = $input->getOption('first-name');
        $lastNameOption = $input->getOption('last-name');
        $firstName = is_string($firstNameOption) ? $firstNameOption : null;
        $lastName = is_string($lastNameOption) ? $lastNameOption : null;

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->note(sprintf('User with email "%s" already exists. Updating to admin...', $email));
            $existingUser->setPassword($this->passwordHasher->hashPassword($existingUser, $password));
            $existingUser->setRoles(['ROLE_ADMIN']);
            
            if ($firstName) {
                $existingUser->setFirstName($firstName);
            }
            
            if ($lastName) {
                $existingUser->setLastName($lastName);
            }

            $this->entityManager->flush();
            $io->success(sprintf('Admin user "%s" updated successfully!', $email));
            return Command::SUCCESS;
        }

        // Create new user
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_ADMIN']);
        
        if ($firstName) {
            $user->setFirstName($firstName);
        }
        
        if ($lastName) {
            $user->setLastName($lastName);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Admin user "%s" created successfully!', $email));

        return Command::SUCCESS;
    }
}
