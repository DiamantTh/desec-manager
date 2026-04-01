<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'user:create',
    description: 'Erstellt einen neuen Benutzer.',
)]
final class UserCreateCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'Benutzername')
            ->addOption('email',    'e', InputOption::VALUE_REQUIRED, 'E-Mail-Adresse')
            ->addOption('admin',    null, InputOption::VALUE_NONE,     'Benutzer als Admin anlegen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QuestionHelper $helper */
        $helper   = $this->getHelper('question');
        $username = (string) ($input->getOption('username') ?? '');
        $email    = (string) ($input->getOption('email') ?? '');
        $isAdmin  = (bool)    $input->getOption('admin');

        if ($username === '') {
            $q        = new Question('Benutzername: ');
            $username = (string) $helper->ask($input, $output, $q);
        }
        if ($email === '') {
            $q     = new Question('E-Mail: ');
            $email = (string) $helper->ask($input, $output, $q);
        }

        $pwQuestion = new Question('Passwort (min. 12 Zeichen): ');
        $pwQuestion->setHidden(true);
        $pwQuestion->setHiddenFallback(false);
        $password = (string) $helper->ask($input, $output, $pwQuestion);

        if (strlen($password) < 12) {
            $output->writeln('<error>Passwort muss mindestens 12 Zeichen lang sein.</error>');
            return Command::FAILURE;
        }

        if ($this->users->findByUsername($username) !== null) {
            $output->writeln("<error>Benutzername '{$username}' existiert bereits.</error>");
            return Command::FAILURE;
        }

        $hash = $this->passwordHasher->hash($password);

        $userId = $this->users->create([
            'username' => $username,
            'email'    => $email,
            'password_hash' => $hash,
            'is_admin' => $isAdmin ? 1 : 0,
        ]);

        $role   = $isAdmin ? 'Admin' : 'User';
        $output->writeln("<info>Benutzer '{$username}' (ID: {$userId}, Rolle: {$role}) erfolgreich angelegt.</info>");

        return Command::SUCCESS;
    }
}
