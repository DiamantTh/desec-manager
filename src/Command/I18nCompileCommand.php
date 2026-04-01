<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PoToMoCompiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'i18n:compile',
    description: 'Kompiliert alle .po-Übersetzungsdateien in das binäre .mo-Format.',
)]
final class I18nCompileCommand extends Command
{
    private const LOCALES = [
        'en_US', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pl_PL',
        'nl_NL', 'pt_PT', 'cs_CZ', 'ro_RO', 'hu_HU', 'sv_SE',
    ];

    public function __construct(private readonly PoToMoCompiler $compiler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $localeDir = dirname(__DIR__, 2) . '/locale';
        $errors    = 0;

        $output->writeln('Kompiliere .po → .mo …');

        foreach (self::LOCALES as $locale) {
            $poFile = "{$localeDir}/{$locale}/LC_MESSAGES/desec-manager.po";
            $moFile = "{$localeDir}/{$locale}/LC_MESSAGES/desec-manager.mo";

            if (!file_exists($poFile)) {
                $output->writeln("  <comment>Übersprungen {$locale}: keine .po-Datei</comment>");
                continue;
            }

            try {
                $this->compiler->compile($poFile, $moFile);
                $output->writeln("  <info>OK</info>     {$locale}");
            } catch (\Throwable $e) {
                $output->writeln("  <error>FEHLER</error> {$locale}: " . $e->getMessage());
                $errors++;
            }
        }

        if ($errors > 0) {
            $output->writeln("\n<error>{$errors} Fehler aufgetreten.</error>");
            return Command::FAILURE;
        }

        $output->writeln("\nAlle Locales kompiliert.");
        return Command::SUCCESS;
    }
}
