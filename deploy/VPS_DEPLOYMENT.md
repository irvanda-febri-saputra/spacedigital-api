# VPS Deployment Guide - SpaceDigital Dashboard

## Prerequisites
- Ubuntu/Debian VPS with root access
- PHP 8.1+ with required extensions
- Composer
- Nginx or Apache
- MySQL/MariaDB or SQLite
- Node.js (for frontend build)
- Supervisor (for daemon management)

---

## Step 1: Install Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP and extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-sqlite3 \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js (for frontend build)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Supervisor
sudo apt install -y supervisor
```

---

## Step 2: Clone and Setup Project

```bash
# Create web directory
sudo mkdir -p /var/www/spacedigital-dashboard
cd /var/www

# Clone your project (or upload via FTP/SCP)
git clone https://github.com/yourusername/spacedigital-dashboard.git
# OR upload files via SCP

# Set permissions
sudo chown -R www-data:www-data /var/www/spacedigital-dashboard
sudo chmod -R 755 /var/www/spacedigital-dashboard

# Navigate to project
cd /var/www/spacedigital-dashboard

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies and build frontend
npm install
npm run build

# Copy environment file
cp .env.example .env

# Edit .env with your production settings
nano .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Cache config and routes
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Step 3: Setup Supervisor for Atlantic Daemon

```bash
# Copy supervisor config
sudo cp deploy/supervisor/atlantic-daemon.conf /etc/supervisor/conf.d/

# Edit the config to match your path
sudo nano /etc/supervisor/conf.d/atlantic-daemon.conf

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Start the daemon
sudo supervisorctl start atlantic-daemon

# Check status
sudo supervisorctl status atlantic-daemon
```

### Supervisor Commands
```bash
# Check status
sudo supervisorctl status

# Start daemon
sudo supervisorctl start atlantic-daemon

# Stop daemon
sudo supervisorctl stop atlantic-daemon

# Restart daemon
sudo supervisorctl restart atlantic-daemon

# View logs
tail -f /var/log/supervisor/atlantic-daemon.log
```

---

## Step 4: Setup Nginx

```nginx
# /etc/nginx/sites-available/spacedigital-dashboard

server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/spacedigital-dashboard/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/spacedigital-dashboard /etc/nginx/sites-enabled/

# Test config
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx
```

---

## Step 5: Setup SSL (HTTPS)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get SSL certificate
sudo certbot --nginx -d yourdomain.com

# Auto-renewal
sudo certbot renew --dry-run
```

---

## Step 6: Update Atlantic Webhook URL

In your Atlantic Pedia dashboard, set the callback URL to:
```
https://yourdomain.com/api/payments/webhook/atlantic
```

---

## Troubleshooting

### Check daemon logs
```bash
tail -f /var/log/supervisor/atlantic-daemon.log
```

### Check Laravel logs
```bash
tail -f /var/www/spacedigital-dashboard/storage/logs/laravel.log
```

### Restart all services
```bash
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
sudo supervisorctl restart atlantic-daemon
```

### Clear Laravel cache
```bash
cd /var/www/spacedigital-dashboard
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

## Summary

After deployment, you'll have:
1. ✅ Laravel dashboard running via Nginx + PHP-FPM
2. ✅ Atlantic daemon running via Supervisor (auto-restart, auto-start on boot)
3. ✅ SSL via Let's Encrypt
4. ✅ Automatic payment processing every 15-60 seconds

The daemon handles ALL bots from a single process - no need to run multiple daemons.
