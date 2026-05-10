<?php

declare(strict_types=1);

namespace App\Redis;

use Redis;

readonly class Settings
{
    public function __construct(
        public string $host,
        public int $port,
        public int $db,
    ) {}

    public static function fromUrl(string $url): self
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new \InvalidArgumentException("Invalid Redis URL: $url");
        }

        return new self(
            host: $parsed['host'] ?? '127.0.0.1',
            port: $parsed['port'] ?? 6379,
            db: isset($parsed['path']) ? (int) ltrim($parsed['path'], '/') : 0,
        );
    }

    public function connect(): Redis
    {
        $redis = new Redis();
        $redis->connect($this->host, $this->port);
        $redis->select($this->db);

        return $redis;
    }
}
