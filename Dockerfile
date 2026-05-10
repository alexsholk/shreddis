FROM php:8.4-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    git unzip curl pkg-config build-essential autoconf g++ make re2c \
    libzstd-dev liblz4-dev liblzf-dev zlib1g-dev \
    hyperfine time procps \
    && rm -rf /var/lib/apt/lists/*

# Serializer extensions
RUN pecl install igbinary msgpack \
    && docker-php-ext-enable igbinary msgpack

# Compression extensions
RUN pecl install lzf zstd \
    && docker-php-ext-enable lzf zstd

# Manual LZ4 extension: PECL often does not leave lz4.so as expected
RUN git clone --depth=1 https://github.com/kjdev/php-ext-lz4.git /tmp/php-ext-lz4 \
    && cd /tmp/php-ext-lz4 \
    && phpize \
    && ./configure --with-lz4-includedir=/usr \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable lz4 \
    && rm -rf /tmp/php-ext-lz4

# PhpRedis with all serializer/compression support
RUN pecl install --configureoptions="\
enable-redis-igbinary='yes' \
enable-redis-msgpack='yes' \
enable-redis-lzf='yes' \
enable-redis-zstd='yes' \
enable-redis-lz4='yes' \
" redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

COPY . .

CMD ["bin/shreddis"]