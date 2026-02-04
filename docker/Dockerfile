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
