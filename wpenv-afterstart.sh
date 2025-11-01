#!/bin/bash
# Install and enable PHP IMAP extension in wp-env container

echo "Installing PHP IMAP extension..."

# Install the IMAP extension package
sudo apk add --no-cache php83-imap c-client 2>/dev/null || true

# Create symlink to the extension in the correct PHP extensions directory
sudo ln -sf /usr/lib/php83/modules/imap.so /usr/local/lib/php/extensions/no-debug-non-zts-20230831/imap.so 2>/dev/null || true

# Enable the extension in PHP configuration
echo "extension=imap.so" | sudo tee /usr/local/etc/php/conf.d/docker-php-ext-imap.ini >/dev/null

echo "IMAP extension installed successfully!"

