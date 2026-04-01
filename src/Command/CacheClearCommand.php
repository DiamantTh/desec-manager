<?php

declare(strict_types=1);

namespace App\Command;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:clear',
    description: 'Leert den PSR-16-Anwendungscache (Filesystem, APCu, Redis, Memcached).',
)]
final class CacheClearCommand extends Command
{
    public function __construct(private readonly CacheInterface $cache)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write('Cache leeren… ');

        if ($this->cache->clear()) {
            $output->writeln('<info>OK</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>FEHLER – Cache konnte nicht vollständig geleert werden.</error>');
        return Command::FAILURE;
    }
}
