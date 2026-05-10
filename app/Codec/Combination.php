<?php

declare(strict_types=1);

namespace App\Codec;

readonly class Combination
{
    public function __construct(
        public Serialization $serialization,
        public Compression $compression,
        private int $gzipLevel = Compression::DEFAULT_GZIP_LEVEL,
    ) {}

    public function encode(mixed $data): string
    {
        return $this->compression->compress(
            $this->serialization->serialize($data),
            $this->gzipLevel,
        );
    }

    public function decode(string $data): mixed
    {
        return $this->serialization->unserialize(
            $this->compression->uncompress($data),
        );
    }

    public function label(): string
    {
        return sprintf(
            '%s+%s',
            $this->serialization->short(),
            $this->compression->short(),
        );
    }

    /**
     * @param array<Serialization> $serializations
     * @param array<Compression> $compressions
     * @return array<Combination>
     */
    public static function combine(
        array $serializations,
        array $compressions,
    ): array {
        $combinations = [];
        foreach ($serializations as $serialization) {
            foreach ($compressions as $compression) {
                $combinations[] = new Combination($serialization, $compression);
            }
        }

        return $combinations;
    }
}
