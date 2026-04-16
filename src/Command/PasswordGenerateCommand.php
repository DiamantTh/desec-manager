<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\PasswordGenerator;
use App\Security\PasswordPolicy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'password:generate',
    description: 'Erzeugt zufällige Passwörter oder Passphrasen und bewertet ihre Stärke (zxcvbn).',
)]
final class PasswordGenerateCommand extends Command
{
    public function __construct(
        private readonly PasswordGenerator $generator,
        private readonly PasswordPolicy    $policy,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'type', 't',
                InputOption::VALUE_REQUIRED,
                'Typ: "random" (Zufallszeichen) oder "xkcd" (Passphrase)',
                'random'
            )
            ->addOption(
                'count', 'c',
                InputOption::VALUE_REQUIRED,
                'Anzahl der Vorschläge',
                '5'
            )
            ->addOption(
                'length', 'l',
                InputOption::VALUE_REQUIRED,
                'Zeichenanzahl für random-Passwörter',
                '20'
            )
            ->addOption(
                'words', 'w',
                InputOption::VALUE_REQUIRED,
                'Wortanzahl für xkcd-Passphrasen',
                '5'
            )
            ->addOption(
                'no-symbols', null,
                InputOption::VALUE_NONE,
                'Keine Sonderzeichen (nur für --type=random)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type    = strtolower((string)($input->getOption('type')   ?? 'random'));
        $count   = max(1, (int)($input->getOption('count')  ?? 5));
        $length  = max(8, (int)($input->getOption('length') ?? 20));
        $words   = max(3, (int)($input->getOption('words')  ?? 5));
        $noSym   = (bool)$input->getOption('no-symbols');

        if (!in_array($type, ['random', 'xkcd'], true)) {
            $output->writeln('<error>Ungültiger Typ. Erlaubt: random, xkcd</error>');
            return Command::FAILURE;
        }

        $minScore = $this->policy->getMinScore();
        $scores   = ['sehr schwach', 'schwach', 'mittel', 'stark', 'sehr stark'];

        $output->writeln('');

        for ($i = 0; $i < $count; $i++) {
            $pw = match ($type) {
                'random' => $this->generator->random($length, !$noSym),
                'xkcd'   => $this->generator->passphrase($words, '-'),
            };

            // zxcvbn-Bewertung (falls aktiv)
            $scoreInfo = '';
            if ($minScore > 0 || $output->isVerbose()) {
                $result    = $this->policy->score($pw);
                $s         = $result['score'];
                $label     = $scores[$s] ?? '?';
                $scoreInfo = "  <comment>[Score {$s}/4 — {$label}]</comment>";
            }

            $output->writeln("  <info>{$pw}</info>{$scoreInfo}");
        }

        $output->writeln('');

        if ($type === 'xkcd') {
            $size    = $this->generator->wordlistSize();
            $lang    = $this->generator->getActiveLang();
            $entropy = round($words * log($size, 2), 1);
            $output->writeln(
                "<comment>Passphrasen-Entropie: ~{$entropy} bit " .
                "({$words} Wörter × log₂({$size}) ≈ " . round(log($size, 2), 1) . " bit/Wort, " .
                "Sprache: {$lang})</comment>"
            );
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
