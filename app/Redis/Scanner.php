<?php

declare(strict_types=1);

namespace App\Redis;

use Generator;
use Redis;

readonly class Scanner
{
    private const int BATCH_SIZE = 1000;

    public function __construct(
        private Redis $redis,
        private int $batchSize = self::BATCH_SIZE,
    ) {}

    /**
     * @return Generator<int, string>
     */
    public function scan(
        int $limit,
        ?string $pattern = null,
        ?string $type = null,
    ): Generator {
        $cursor = null;
        $yielded = 0;

        do {
            $batch = $this->redis->scan(
                $cursor,
                $pattern,
                $this->batchSize,
                $type,
            );

            foreach ($batch ?: [] as $key) {
                yield $key;

                if (++$yielded >= $limit) {
                    return;
                }
            }
        } while ($cursor);
    }
}
