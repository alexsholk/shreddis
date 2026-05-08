<?php

declare(strict_types=1);

namespace App\Redis;

class KeyPrefixBuilder
{
    private const array SHORT = [
        // serialization
        'json' => 'json',
        'php' => 'php',
        'igbinary' => 'igb',
        'msgpack' => 'msg',
        // compression
        'none' => 'raw',
        'gzip' => 'gz',
        'lz4' => 'lz4',
        'zstd' => 'zst',
        'lzf' => 'lzf',
    ];

    public function __construct(
        private readonly string $prefix
    ) {}

    public function keyName(string $ser, string $cmp, string $originalKey): string
    {
        return sprintf('%s:%s:%s', $this->prefix, $this->variantLabel($ser, $cmp), $originalKey);
    }

    public function variantLabel(string $ser, string $cmp): string
    {
        return self::SHORT[$ser] . '+' . self::SHORT[$cmp];
    }

    public function processedSetKey(): string
    {
        return $this->prefix . ':source_keys';
    }
}
