<?php

declare(strict_types=1);

namespace App\Command;

use App\Redis\Keys;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'check', description: 'Check that serialized and compressed Redis keys store same data')]
class Check extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Redis host')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Redis port')
            ->addOption('db', null, InputOption::VALUE_REQUIRED, 'Redis DB')
            ->addOption(
                'prefix',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Key prefix (key names will be retrieved from <prefix>%s)',
                    Keys::KEY_NAMES
                ),
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $prefix = $input->getOption('prefix');
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $db = $input->getOption('db');

        // todo

        return Command::SUCCESS;
    }
}
