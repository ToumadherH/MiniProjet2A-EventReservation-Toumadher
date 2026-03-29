<?php

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
    name: 'app:create-user',
    description: 'Create a user with optional admin role.',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username for the account')
            ->addArgument('password', InputArgument::REQUIRED, 'Plain password for the account')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Assign ROLE_ADMIN to this user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = (string) $input->getArgument('username');
        $plainPassword = (string) $input->getArgument('password');
        $isAdmin = (bool) $input->getOption('admin');

        $existing = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if ($existing instanceof User) {
            $io->error(sprintf('User "%s" already exists.', $username));

            return Command::FAILURE;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setRoles($isAdmin ? ['ROLE_ADMIN'] : []);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'User "%s" created with roles: %s',
            $username,
            implode(', ', $user->getRoles())
        ));

        return Command::SUCCESS;
    }
}
