<?php

declare(strict_types=1);

namespace App\Command;

use App\Codec\Combination;
use App\Codec\Compression;
use App\Codec\Serialization;
use App\Redis\Keys;
use App\Redis\Scanner;
use App\Redis\Settings;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'copy', description: 'Copy Redis keys with serialization and compression')]
class Copy extends Command
{
    private const int CHUNK_SIZE = 100;

    protected function configure(): void
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Source Redis URL')
            ->addOption('target-url', null, InputOption::VALUE_OPTIONAL, 'Target Redis URL (defaults to --url)')
            ->addOption('pattern', null, InputOption::VALUE_REQUIRED, 'SCAN MATCH pattern')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max keys to scan', 10_000)
            ->addOption('serialization', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Serialization: json, php, igbinary, msgpack (repeatable, default: all available)', [])
            ->addOption('compression', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Compression: none, gzip, lz4, zstd, lzf (repeatable, default: all available)', [])
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Key prefix');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = (string) $input->getOption('url');
        $targetUrl = $input->getOption('target-url') ?? $url;
        $pattern = (string) $input->getOption('pattern');
        $limit = (int) $input->getOption('limit');
        $prefix = (string)$input->getOption('prefix');

        // Combinations of serializations and compressions
        {
            $serializations = Serialization::enabled($input->getOption('serialization'));
            $compressions = Compression::enabled($input->getOption('compression'));
            $combinations = Combination::combine($serializations, $compressions);

            if (empty($combinations)) {
                $io->error('No valid serialization/compression methods remaining.');

                return Command::FAILURE;
            }
        }

        // Redis connections
        {
            $io->section('Connecting');

            try {
                $redis = Settings::fromUrl($url)->connect();
                $redis->ping() ?: throw new RuntimeException("cannot connect to source: $url");
                $io->text("Source: $url");

                $targetRedis = Settings::fromUrl($targetUrl)->connect();
                $targetRedis->ping() ?: throw new RuntimeException("cannot connect to target: $url");
                $io->text("Target: $targetUrl");
            } catch (\Exception $e) {
                $io->error('Connection failed: ' . $e->getMessage());

                return Command::FAILURE;
            }
        }

        // Show plan and confirm
        {
            $io->section('Plan');
            $io->definitionList(
                ['Source' => $url],
                ['Target' => $targetUrl],
                ['Pattern' => $pattern !== '' ? $pattern : '(all keys)'],
                ['Limit' => $limit],
                ['Prefix' => $prefix],
                ['Variants' => implode(', ', array_map(fn (Combination $c) => $c->label(), $combinations))],
            );

            $io->text(sprintf(
                'Each matching key will be written as <info>%d</info> copies',
                count($combinations),
            ));

            if (!$io->confirm('Proceed?', false)) {
                $io->text('Aborted.');

                return Command::SUCCESS;
            }
        }

        // Execute
        {
            $io->section('Scanning');
            $scanner = new Scanner($redis);
            $keys = iterator_to_array($scanner->scan($limit, $pattern, 'STRING'));
            $count = count($keys);
            $io->text(sprintf('Keys found: %d', $count));

            $io->section('Copying');
            $io->progressStart($count);

            $keyManager = new Keys($prefix);
            $processed = $skipped = $written = 0;

            foreach (array_chunk($keys, self::CHUNK_SIZE) as $chunk) {
                $values = array_combine($chunk, $redis->mget($chunk));
                $values = array_filter(
                    array_map(function (string $raw) use (&$skipped) {
                        $decoded = json_decode($raw, associative: true);
                        if ($decoded === null && $raw !== 'null') {
                            $skipped++;
                        }

                        return $decoded;
                    }, $values)
                );

                $copied = [];
                foreach ($values as $key => $value) {
                    foreach ($combinations as $combination) {
                        $copied[$keyManager->name($combination, $key)] = $combination->encode($value);
                    }
                    $processed++;
                    $io->progressAdvance();
                }

                $targetRedis->mset($copied);
                $targetRedis->sAddArray($keyManager->keyNames(), array_keys($copied));
                $written += count($copied);
            }

            $io->progressFinish();
            $io->success(sprintf(
                'Done. Processed: %d, skipped: %d, written: %d keys (%d variants × %d keys).',
                $processed,
                $skipped,
                $written,
                count($combinations),
                $processed,
            ));
        }

        return Command::SUCCESS;
    }
}
