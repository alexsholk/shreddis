<?php

declare(strict_types=1);

namespace App\Codec;

enum Serialization: string
{
    case Json = 'json';
    case Php = 'php';
    case Igbinary = 'igbinary';
    case Msgpack = 'msgpack';

    private const array SHORT = [
        'json' => 'json',
        'php' => 'php',
        'igbinary' => 'igb',
        'msgpack' => 'msg',
    ];

    private const array EXT_MAP = [
        'igbinary' => 'igbinary',
        'msgpack' => 'msgpack',
    ];

    public function short(): string
    {
        return self::SHORT[$this->value];
    }

    public function serialize(mixed $data)
    {
        return match ($this) {
            self::Json => json_encode($data, flags: JSON_THROW_ON_ERROR),
            self::Php => serialize($data),
            self::Igbinary => igbinary_serialize($data),
            self::Msgpack => msgpack_pack($data),
        };
    }

    public function unserialize(string $data): mixed
    {
        return match ($this) {
            self::Json => json_decode($data, true, flags: JSON_THROW_ON_ERROR),
            self::Php => unserialize($data),
            self::Igbinary => igbinary_unserialize($data),
            self::Msgpack => msgpack_unpack($data),
        };
    }
    /**
     * @param array<string> $enable
     * @return array<Serialization>
     */
    public static function enabled(array $enable = []): array
    {
        return array_filter(
            self::available(),
            fn (Serialization $v) => empty($enable) || in_array($v->value, $enable),
        );
    }

    /**
     * @return array<Serialization>
     */
    public static function available(): array
    {
        return array_filter(self::cases(), fn (self $comp) => !(
            isset(self::EXT_MAP[$comp->value])
            && !extension_loaded(self::EXT_MAP[$comp->value])
        ));
    }
}
