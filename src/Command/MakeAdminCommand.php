<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:make-admin', description: 'Give a user the admin role')]
class MakeAdminCommand extends Command
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email of the account to promote');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = strtolower(trim((string) $input->getArgument('email')));
        $user = $this->users->findOneBy(['email' => $email]);

        if (!$user) {
            $output->writeln('<error>No user found with email ' . $email . '</error>');

            return Command::FAILURE;
        }

        $user->setRoles(array_values(array_unique([...$user->getRoles(), 'ROLE_ADMIN'])));
        $this->em->flush();

        $output->writeln('<info>' . $email . ' is now an admin. Log out and log in again.</info>');

        return Command::SUCCESS;
    }
}