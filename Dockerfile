# Base image
FROM php:8-apache

# Install Node.js + npm
RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install InstaTunnel globally via npm
RUN npm install -g instatunnel

# Suppress Apache's startup warning about a missing global ServerName.
RUN printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Optional: set working directory
WORKDIR /var/www/html

# Copy application files into the image.
COPY . /var/www/html/

# Add the container startup script.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose Apache port
EXPOSE 80

# Start Apache and publish it through InstaTunnel.
CMD ["/usr/local/bin/docker-entrypoint.sh"]