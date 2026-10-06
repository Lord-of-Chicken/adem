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
            ->addArgument('password', InputArgument::OPTIONAL, 'Password (prefer the hidden interactive prompt)')
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

        if ($password === '' && $input->isInteractive()) {
            $answer = $io->askHidden('Admin password');
            $password = is_string($answer) ? $answer : '';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180
            || mb_strlen($password) < 8 || mb_strlen($password) > 4096
            || mb_strlen($firstName ?? '') > 100 || mb_strlen($lastName ?? '') > 100) {
            $io->error('Invalid email, password length or name length.');
            return Command::INVALID;
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            if ($existingUser->isAnonymized()) {
                $io->error('An anonymized account cannot be promoted or reactivated.');
                return Command::INVALID;
            }
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
