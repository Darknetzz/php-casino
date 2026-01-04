FROM php:8.2-apache

# Install required PHP extensions, curl, and git for healthcheck and git clone
RUN apt-get update && \
    apt-get install -y \
        curl \
        git \
        libsqlite3-dev \
        && \
    docker-php-ext-install pdo_sqlite && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Clone the repository using HTTPS
ARG GIT_REPO=https://github.com/Darknetzz/php-casino.git
ARG GIT_BRANCH=main

# Clone the repository
RUN git clone ${GIT_REPO} /tmp/casino && \
    cd /tmp/casino && \
    git checkout ${GIT_BRANCH} 2>/dev/null || git checkout main && \
    cp -r /tmp/casino/* /var/www/html/ && \
    cp -r /tmp/casino/.* /var/www/html/ 2>/dev/null || true && \
    rm -rf /tmp/casino

# Create data directory and set permissions
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/data

# Configure Apache
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
