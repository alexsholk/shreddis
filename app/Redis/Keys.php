<?php

declare(strict_types=1);

namespace App\Redis;

use App\Codec\Combination;

readonly class Keys
{
    public const string KEY_NAMES = 'key_names';

    public function __construct(
        private string $prefix
    ) {}

    public function name(Combination $combination, string $key): string
    {
        return sprintf('%s%s:%s', $this->prefix(), $combination->label(), $key);
    }

    public function keyNames(): string
    {
        return $this->prefix() . self::KEY_NAMES;
    }

    private function prefix(): string
    {
        return !empty($this->prefix) ? $this->prefix . ':' : '';
    }
}
