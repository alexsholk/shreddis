<?php

declare(strict_types=1);

namespace App\Redis;

readonly class CopyResult
{
    public function __construct(
        public int $processed,
        public int $skipped,
        public int $written,
    ) {}
}
