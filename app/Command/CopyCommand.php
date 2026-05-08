<?php

declare(strict_types=1);

namespace App\Command;

use App\Redis\KeyPrefixBuilder;
use App\Redis\RedisKeyCopier;
use Predis\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'copy', description: 'Copy Redis keys with serialization and compression')]
class CopyCommand extends Command
{
    private const array SERIALIZATIONS = ['json', 'php', 'igbinary', 'msgpack'];
    private const array COMPRESSIONS = ['none', 'gzip', 'lz4', 'zstd', 'lzf'];

    private const array EXT_MAP = [
        'igbinary' => 'igbinary',
        'msgpack' => 'msgpack',
        'lz4' => 'lz4',
        'zstd' => 'zstd',
        'lzf' => 'lzf',
    ];

    protected function configure(): void
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Source Redis URL')
            ->addOption('target-url', null, InputOption::VALUE_OPTIONAL, 'Target Redis URL (defaults to --url)')
            ->addOption('pattern', null, InputOption::VALUE_REQUIRED, 'SCAN MATCH pattern')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max keys to scan', 10_000)
            ->addOption('serialization', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Serialization: json, php, igbinary, msgpack (repeatable, default: all available)', [])
            ->addOption('compression', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Compression: none, gzip, lz4, zstd, lzf (repeatable, default: all available)', [])
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Key prefix (required — prevents overwriting production keys)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = $input->getOption('url');
        $targetUrl = $input->getOption('target-url') ?? $url;
        $pattern = (string) $input->getOption('pattern');
        $limit = (int) $input->getOption('limit');
        $prefix = $input->getOption('prefix');

        if (empty($prefix)) {
            $io->error('--prefix is required. It prevents accidentally overwriting keys in production.');
            return Command::FAILURE;
        }

        $serializations = $input->getOption('serialization') ?: self::SERIALIZATIONS;
        $compressions = $input->getOption('compression') ?: self::COMPRESSIONS;

        foreach ($serializations as $s) {
            if (!in_array($s, self::SERIALIZATIONS, true)) {
                $io->error("Unknown serialization: \"$s\". Available: " . implode(', ', self::SERIALIZATIONS));
                return Command::FAILURE;
            }
        }

        foreach ($compressions as $c) {
            if (!in_array($c, self::COMPRESSIONS, true)) {
                $io->error("Unknown compression: \"$c\". Available: " . implode(', ', self::COMPRESSIONS));
                return Command::FAILURE;
            }
        }

        $serializations = $this->filterAvailable($serializations, $io);
        $compressions = $this->filterAvailable($compressions, $io);

        if (empty($serializations) || empty($compressions)) {
            $io->error('No valid serialization/compression methods remaining.');
            return Command::FAILURE;
        }

        $combinations = [];
        foreach ($serializations as $ser) {
            foreach ($compressions as $cmp) {
                $combinations[] = [$ser, $cmp];
            }
        }

        $io->section('Connecting');
        try {
            $source = new Client($url);
            $source->ping();
            $io->text("Source : $url");

            $sameTarget = $targetUrl === $url;
            $target = $sameTarget ? $source : new Client($targetUrl);
            if (!$sameTarget) {
                $target->ping();
            }
            $io->text("Target : $targetUrl");
        } catch (\Exception $e) {
            $io->error('Connection failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $prefixBuilder = new KeyPrefixBuilder($prefix);
        $variantLabels = array_map(fn ($c) => $prefixBuilder->variantLabel($c[0], $c[1]), $combinations);

        $io->section('Plan');
        $io->definitionList(
            ['Source' => $url],
            ['Target' => $targetUrl],
            ['Pattern' => $pattern !== '' ? $pattern : '(all keys)'],
            ['Limit' => $limit],
            ['Prefix' => $prefix],
            ['Variants' => implode(', ', $variantLabels)],
            ['Source SET' => $prefixBuilder->processedSetKey()],
        );

        $io->text(sprintf(
            'Each matching key will be written as <info>%d</info> copies: <comment>%s:&lt;variant&gt;:&lt;original-key&gt;</comment>',
            count($combinations),
            $prefix,
        ));

        if (!$io->confirm('Proceed?', false)) {
            $io->text('Aborted.');

            return Command::SUCCESS;
        }

        $copier = new RedisKeyCopier($source, $target, $prefixBuilder);

        $io->section("Scanning (pattern: \"$pattern\", limit: $limit)");
        $keys = $copier->scan($pattern, $limit);
        $io->text('Found ' . count($keys) . ' keys.');

        $io->section('Writing');
        $result = $copier->copy($keys, $combinations, $io);

        $io->success(sprintf(
            'Done. Processed: %d, skipped: %d, written: %d keys (%d variants × %d keys).',
            $result->processed,
            $result->skipped,
            $result->written,
            count($combinations),
            $result->processed,
        ));

        return Command::SUCCESS;
    }

    private function filterAvailable(array $methods, SymfonyStyle $io): array
    {
        return array_filter($methods, function (string $method) use ($io): bool {
            if (isset(self::EXT_MAP[$method]) && !extension_loaded(self::EXT_MAP[$method])) {
                $io->warning("ext-{$method} not loaded — skipping \"$method\".");
                return false;
            }
            return true;
        });
    }
}
