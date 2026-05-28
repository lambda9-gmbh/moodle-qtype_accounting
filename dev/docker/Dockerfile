ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libpq-dev \
    unzip \
    git \
    curl \
    locales \
    && rm -rf /var/lib/apt/lists/*

# Install composer (used by moodle-cs and moodle-plugin-ci installs below).
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js 22 (required by Moodle's grunt tasks).
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install moodle-cs (phpcs ruleset) used by scripts/ci.sh codecheck.
RUN mkdir -p /opt/moodle-cs && cd /opt/moodle-cs \
    && printf '%s\n' \
        '{' \
        '    "require-dev": {"moodlehq/moodle-cs": "^3.7"},' \
        '    "config": {"allow-plugins": {"dealerdirect/phpcodesniffer-composer-installer": true}}' \
        '}' > composer.json \
    && composer install --no-interaction --no-progress

# Install moodle-plugin-ci (used by scripts/ci.sh for phpcs/phpmd/phpdoc/...).
RUN composer create-project moodlehq/moodle-plugin-ci /opt/moodle-plugin-ci ^4 \
    --no-interaction --no-progress \
    || true # post-install hook attempts npm install in vendor/.../moodle-local_ci; harmless if it fails

# Install required locales for PHPUnit tests
RUN sed -i '/en_AU.UTF-8/s/^# //g' /etc/locale.gen && locale-gen en_AU.UTF-8

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo \
    pdo_mysql \
    zip \
    intl \
    xml \
    soap \
    opcache \
    exif

# Install additional PHP extensions
RUN pecl install redis && docker-php-ext-enable redis

# Configure PHP
RUN echo "max_input_vars = 5000" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "upload_max_filesize = 256M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "post_max_size = 256M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/moodle.ini

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Download Moodle
ARG MOODLE_VERSION=MOODLE_405_STABLE
RUN curl -L https://github.com/moodle/moodle/archive/refs/heads/${MOODLE_VERSION}.tar.gz | tar xz --strip-components=1

# Create moodledata directory
RUN mkdir -p /var/www/moodledata && chown -R www-data:www-data /var/www/moodledata

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80

CMD ["apache2-foreground"]
