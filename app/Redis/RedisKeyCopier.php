<?php

declare(strict_types=1);

namespace App\Redis;

use Predis\Client;
use Symfony\Component\Console\Style\SymfonyStyle;

readonly class RedisKeyCopier
{
    private const int BATCH_SIZE = 1000;
    private const int GZIP_LEVEL = 6;

    public function __construct(
        private Client $source,
        private Client $target,
        private KeyPrefixBuilder $prefixBuilder,
    ) {}

    public function scan(string $pattern, int $limit): array
    {
        $keys = [];
        $cursor = '0';

        do {
            $args = ['COUNT' => self::BATCH_SIZE];

            if ($pattern !== '') {
                $args['MATCH'] = $pattern;
            }

            [$cursor, $batch] = $this->source->scan($cursor, $args);

            $keys = array_merge($keys, $batch ?? []);

            if (count($keys) >= $limit) {
                return array_slice($keys, 0, $limit);
            }
        } while ($cursor !== '0');

        return $keys;
    }

    public function copy(array $keys, array $combinations, SymfonyStyle $io): CopyResult
    {
        $processed = $skipped = $written = 0;
        $processedKeys = [];

        $io->progressStart(count($keys));

        foreach ($keys as $key) {
            if ((string) $this->source->type($key) !== 'string') {
                $skipped++;
                $io->progressAdvance();
                continue;
            }

            $raw = $this->source->get($key);
            $decoded = json_decode($raw, true);
            if ($decoded === null && $raw !== 'null') {
                $skipped++;
                $io->progressAdvance();
                continue;
            }

            foreach ($combinations as [$ser, $cmp]) {
                $value = $this->compress($this->serialize($decoded, $ser), $cmp);
                $this->target->set($this->prefixBuilder->keyName($ser, $cmp, $key), $value);
                $written++;
            }

            $processedKeys[] = $key;
            $processed++;
            $io->progressAdvance();
        }

        $io->progressFinish();

        if (!empty($processedKeys)) {
            $this->target->sadd($this->prefixBuilder->processedSetKey(), ...$processedKeys);
        }

        return new CopyResult($processed, $skipped, $written);
    }

    private function serialize(mixed $data, string $method): string
    {
        return match ($method) {
            'json' => json_encode($data, JSON_THROW_ON_ERROR),
            'php' => serialize($data),
            'igbinary' => igbinary_serialize($data),
            'msgpack' => msgpack_pack($data),
        };
    }

    private function compress(string $data, string $method): string
    {
        return match ($method) {
            'none' => $data,
            'gzip' => gzencode($data, self::GZIP_LEVEL),
            'lz4' => lz4_compress($data),
            'zstd' => zstd_compress($data),
            'lzf' => lzf_compress($data),
        };
    }
}
