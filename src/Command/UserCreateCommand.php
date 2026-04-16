<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Security\PasswordGenerator;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
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
        private readonly UserRepository    $users,
        private readonly PasswordHasher    $passwordHasher,
        private readonly PasswordPolicy    $passwordPolicy,
        private readonly PasswordGenerator $passwordGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'Benutzername')
            ->addOption('email',    'e', InputOption::VALUE_REQUIRED, 'E-Mail-Adresse')
            ->addOption('admin',    null, InputOption::VALUE_NONE,    'Benutzer als Admin anlegen')
            ->addOption('generate', 'g', InputOption::VALUE_NONE,    'Passwort-Vorschläge anzeigen');
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

        $pwQuestion = new Question(sprintf('Passwort (min. %d Zeichen): ', $this->passwordPolicy->getMinLength()));
        $pwQuestion->setHidden(true);
        $pwQuestion->setHiddenFallback(false);

        if ((bool)$input->getOption('generate')) {
            $output->writeln('');
            $output->writeln('<comment>Passwort-Vorschläge:</comment>');
            $suggestions = $this->passwordGenerator->suggestions(5);
            $all = [];
            foreach ($suggestions['random'] as $i => $pw) {
                $all[] = $pw;
                $output->writeln(sprintf('  <info>[%d]</info> %s  <comment>(zufällig)</comment>', $i + 1, $pw));
            }
            foreach ($suggestions['passphrase'] as $i => $pw) {
                $all[] = $pw;
                $output->writeln(sprintf('  <info>[%d]</info> %s  <comment>(Passphrase)</comment>', $i + 6, $pw));
            }
            $output->writeln('');
            $pickQ = new Question('Nummer wählen oder eigenes Passwort eingeben: ');
            $pickQ->setHidden(true);
            $pickQ->setHiddenFallback(false);
            $pick = trim((string)$helper->ask($input, $output, $pickQ));
            $password = is_numeric($pick) && isset($all[(int)$pick - 1])
                ? $all[(int)$pick - 1]
                : $pick;
        } else {
            $password = (string) $helper->ask($input, $output, $pwQuestion);
        }

        try {
            $this->passwordPolicy->assertValid($password);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
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
