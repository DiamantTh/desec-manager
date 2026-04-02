<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Führt alle noch nicht angewendeten SQL-Migrationsdateien aus.
 *
 * Migrations-Verzeichnis:  sql/{driver}/
 * Datei-Muster:            *.sql  (alphabetisch, idempotent durch IF NOT EXISTS)
 *
 * Unterstützte Treiber:  sqlite, mysql, postgresql
 */
#[AsCommand(
    name: 'db:migrate',
    description: 'Führt ausstehende SQL-Migrationsskripte aus.',
)]
final class DbMigrateCommand extends Command
{
    public function __construct(
        private readonly Connection $db,
        /** @var array<string, mixed> */
        private readonly array $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Zeigt die Migrationsskripte an ohne sie auszuführen.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $driver = $this->detectDriver();
        $sqlDir = dirname(__DIR__, 2) . "/sql/{$driver}";

        if (!is_dir($sqlDir)) {
            $output->writeln("<error>Kein Migrations-Verzeichnis gefunden: {$sqlDir}</error>");
            return Command::FAILURE;
        }

        $files = glob("{$sqlDir}/*.sql");
        if ($files === false || $files === []) {
            $output->writeln('<comment>Keine Migrationsskripte gefunden.</comment>');
            return Command::SUCCESS;
        }

        sort($files);
        $errors = 0;

        foreach ($files as $file) {
            $name = basename($file);
            $sql  = file_get_contents($file);

            if ($sql === false || trim($sql) === '') {
                $output->writeln("  <comment>Übersprungen (leer): {$name}</comment>");
                continue;
            }

            if ($dryRun) {
                $output->writeln("  [dry-run] {$name}");
                continue;
            }

            try {
                // Statements trennen (einfache Aufteilung; SQLite/MySQL/PostgreSQL kompatibel)
                // array_filter entfernt bereits leere Strings — kein zusätzlicher Leercheck nötig
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                    $this->db->executeStatement($statement);
                }
                $output->writeln("  <info>OK</info>  {$name}");
            } catch (\Throwable $e) {
                $output->writeln("  <error>FEHLER</error> {$name}: " . $e->getMessage());
                $errors++;
            }
        }

        if ($dryRun) {
            $output->writeln("\n<comment>Dry-run abgeschlossen – keine Änderungen vorgenommen.</comment>");
            return Command::SUCCESS;
        }

        if ($errors > 0) {
            $output->writeln("\n<error>{$errors} Migration(en) fehlgeschlagen.</error>");
            return Command::FAILURE;
        }

        $output->writeln("\nAlle Migrationen ausgeführt.");
        return Command::SUCCESS;
    }

    private function detectDriver(): string
    {
        $driver = (string) ($this->config['database']['driver'] ?? 'sqlite');
        return match (true) {
            str_contains($driver, 'sqlite') => 'sqlite',
            str_contains($driver, 'pgsql') || str_contains($driver, 'postgresql') => 'postgresql',
            default => 'mysql',
        };
    }
}
