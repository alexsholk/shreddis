<?php

declare(strict_types=1);

namespace App\Command;

use App\Codec\Compression;
use App\Codec\Serialization;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'info', description: 'Show available serialization and compression algorithms')]
class Info extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->section('Available serialization algorithms');
        $io->listing(array_map(fn (Serialization $v) => $v->value, Serialization::available()));
        $io->section('Available compression algorithms');
        $io->listing(array_map(fn (Compression $v) => $v->value, Compression::available()));

        return Command::SUCCESS;
    }
}
