FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Create the Supervisor configuration directory
RUN mkdir -p /etc/supervisor/conf.d

# Copy your Supervisor configuration file
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy your PHP application code
COPY . /var/www/html

# Create log directories
RUN mkdir -p /var/www/html/var/log /var/log/supervisor

# Make sure Supervisor configurations are readable
RUN chmod 644 /etc/supervisor/conf.d/supervisord.conf

# Expose port 9000 for PHP-FPM and 9001 for Supervisor
EXPOSE 9000 9001

# Run Supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]