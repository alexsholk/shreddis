<?php

declare(strict_types=1);

namespace App\Codec;

enum Compression: string
{
    public const int DEFAULT_GZIP_LEVEL = 6;

    case None = 'none';
    case Gzip = 'gzip';
    case Zstd = 'zstd';
    case Lzf = 'lzf';
    case Lz4 = 'lz4';

    private const array SHORT = [
        'none' => 'raw',
        'gzip' => 'gz',
        'zstd' => 'zst',
        'lzf' => 'lzf',
        'lz4' => 'lz4',
    ];

    private const array EXT_MAP = [
        'zstd' => 'zstd',
        'lzf' => 'lzf',
        'lz4' => 'lz4',
    ];

    public function short(): string
    {
        return self::SHORT[$this->value];
    }

    public function compress(string $data, int $gzipLevel = self::DEFAULT_GZIP_LEVEL): string
    {
        return match ($this) {
            self::None => $data,
            self::Gzip => gzencode($data, $gzipLevel),
            self::Zstd => zstd_compress($data),
            self::Lzf => lzf_compress($data),
            self::Lz4 => lz4_compress($data),
        };
    }

    public function uncompress(string $data): string
    {
        return match ($this) {
            self::None => $data,
            self::Gzip => gzdecode($data),
            self::Zstd => zstd_uncompress($data),
            self::Lzf => lzf_uncompress($data),
            self::Lz4 => lz4_uncompress($data),
        };
    }

    /**
     * @param array<string> $enable
     * @return array<Compression>
     */
    public static function enabled(array $enable = []): array
    {
        return array_filter(
            self::available(),
            fn (Compression $v) => empty($enable) || in_array($v->value, $enable),
        );
    }

    /**
     * @return array<Compression>
     */
    public static function available(): array
    {
        return array_filter(self::cases(), fn (self $comp) => !(
            isset(self::EXT_MAP[$comp->value])
            && !extension_loaded(self::EXT_MAP[$comp->value])
        ));
    }
}
