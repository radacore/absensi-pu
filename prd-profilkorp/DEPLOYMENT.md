# DEPLOYMENT.md: BBWS Pompengan Jeneberang

## Overview

This document provides comprehensive deployment strategies, infrastructure configuration, CI/CD pipeline setup, monitoring, and rollback procedures for **BBWS Pompengan Jeneberang**—a Laravel 13 (PHP 8.4+) + React 19 + Inertia.js v2 + Tailwind v4 + Vite 7 corporate profile application with a regional admin dashboard (per Kabupaten/Kota) and a mobile PWA for employees (email login, GPS+selfie absensi, cuti berjenjang, pengumuman) — tanpa public site.

The deployment architecture is designed for high availability, security (region isolation + PWA secure context, Love cron reset), and ease of maintenance on a VPS environment (Linode, DigitalOcean, or AWS EC2) running Ubuntu 24.04 LTS. Multi-tenant per region via `region_id`, RBAC 3 roles, S3 paths for attendance selfies & love dokumen.

## Environment Strategy

### Environment Tiers

| Environment | Purpose | Database | Storage | URL Pattern |
|:---|:---|:---|:---|:---|
| **Development** | Local development and feature testing. | Local MySQL 8.4 / 9.x | Local filesystem | `localhost:5173` (Vite 7) |
| **Staging** | Pre-production testing, UAT, and content review. | Staging MySQL 8.4 | AWS S3 (staging bucket) | `staging.profilkorp.local` |
| **Production** | Live public-facing application. | Production MySQL 8.4 LTS | AWS S3 (production bucket) | `www.profilkorp.com` |

### Environment Variables

Create `.env` files for each environment. Critical variables include:

```
APP_NAME=BBWS Pompengan Jeneberang
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.profilkorp.com

DB_CONNECTION=mysql
DB_HOST=<db-host>
DB_PORT=3306
DB_DATABASE=profilkorp_prod
DB_USERNAME=<db-user>
DB_PASSWORD=<secure-password>

AWS_ACCESS_KEY_ID=<key>
AWS_SECRET_ACCESS_KEY=<secret>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=profilkorp-prod
AWS_URL=https://profilkorp-prod.s3.amazonaws.com

SANCTUM_STATEFUL_DOMAINS=www.profilkorp.com
SESSION_DOMAIN=.profilkorp.com

SUPER_ADMIN_PATH=/super-admin-<random-hash>
WILAYAH_PATH=/wilayah-<random-hash>
KARYAWAN_PATH=/karyawan-<random-hash>
VAPID_PUBLIC_KEY=<vapid-public>
VAPID_PRIVATE_KEY=<vapid-private>
VAPID_SUBJECT=mailto:admin@profilkorp.com

# Geofence defaults (can be overridden per region in DB)
ATTENDANCE_STRICT_GEOFENCE=false
ATTENDANCE_DEFAULT_RADIUS_M=500
```

**Security Note:** Never commit `.env` files to version control. Use a secure secrets management system (e.g., AWS Secrets Manager, HashiCorp Vault, or environment-specific `.env.production` templates).

## Infrastructure & Hosting Setup

### VPS Configuration (Recommended: Linode 8GB RAM, 4 vCPU)

#### Server Specifications
- **OS:** Ubuntu 24.04 LTS
- **RAM:** 8 GB minimum
- **vCPU:** 4 cores minimum
- **Storage:** 100 GB SSD
- **Bandwidth:** Unmetered or ≥ 5 TB/month

#### Initial Server Setup

1. **SSH Key Authentication**
   ```bash
   ssh-keygen -t ed25519 -C "profilkorp-deploy"
   # Add public key to VPS authorized_keys
   ```

2. **System Updates**
   ```bash
   sudo apt update && sudo apt upgrade -y
   sudo apt install -y curl wget git build-essential
   ```

3. **Install PHP & Dependencies**
    ```bash
    sudo add-apt-repository ppa:ondrej/php
    sudo apt update
    sudo apt install -y php8.4 php8.4-fpm php8.4-mysql php8.4-curl \
      php8.4-gd php8.4-mbstring php8.4-xml php8.4-zip php8.4-bcmath php8.4-redis
    ```

4. **Install MySQL 8.4 LTS**
    ```bash
    sudo apt install -y mysql-server
    sudo mysql_secure_installation
    # Alternatively: MySQL 9.x innovation release via MySQL APT repo
    ```

5. **Install Node.js 22 LTS & npm**
    ```bash
    curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    sudo apt install -y nodejs
    node -v # should be v22.x
    npm -v  # 10.x+
    ```

6. **Install Composer**
   ```bash
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```

7. **Install Nginx**
   ```bash
   sudo apt install -y nginx
   sudo systemctl enable nginx
   ```

8. **Install Redis (for caching & sessions)**
   ```bash
   sudo apt install -y redis-server
   sudo systemctl enable redis-server
   ```

### PWA Assets & Service Worker

- Serve `manifest.json` and `service-worker.js` from `public/` with correct MIME + cache headers (`no-cache` for SW, long cache for manifest icons).
- Ensure HTTPS is enforced (PWA requires secure context for GPS/camera + installability).
- Add `VAPID` keys for push notifications (future pengumuman push).

### Nginx Configuration

Create `/etc/nginx/sites-available/profilkorp`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name www.profilkorp.com profilkorp.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name www.profilkorp.com profilkorp.com;

    ssl_certificate /etc/letsencrypt/live/profilkorp.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/profilkorp.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root /var/www/profilkorp/public;
    index index.php index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';" always;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1000;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/profilkorp /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### SSL Certificate (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d profilkorp.com -d www.profilkorp.com
sudo certbot renew --dry-run  # Test auto-renewal
```

## Application Deployment

### Repository Setup

```bash
cd /var/www
sudo git clone https://github.com/your-org/profilkorp.git
cd profilkorp
sudo chown -R www-data:www-data .
```

### Laravel Installation & Configuration

```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies
npm install

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed initial data (if applicable)
php artisan db:seed

# Build frontend assets
npm run build

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### File Permissions

```bash
sudo chown -R www-data:www-data /var/www/profilkorp
sudo chmod -R 755 /var/www/profilkorp
sudo chmod -R 775 /var/www/profilkorp/storage
sudo chmod -R 775 /var/www/profilkorp/bootstrap/cache
```

### AWS S3 Configuration

1. **Create IAM User for Application**
   ```
   Policy: AmazonS3FullAccess (scoped to profilkorp-prod bucket)
   ```

2. **Create S3 Buckets**
   ```bash
   # Production bucket
   aws s3 mb s3://profilkorp-prod --region us-east-1
   
   # Enable versioning
   aws s3api put-bucket-versioning \
     --bucket profilkorp-prod \
     --versioning-configuration Status=Enabled
   
   # Block public access
   aws s3api put-public-access-block \
     --bucket profilkorp-prod \
     --public-access-block-configuration \
     "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
   ```

3. **Configure CORS for S3**
   ```json
   [
     {
       "AllowedHeaders": ["*"],
       "AllowedMethods": ["GET", "PUT", "POST"],
       "AllowedOrigins": ["https://www.profilkorp.com"],
       "ExposeHeaders": ["ETag"],
       "MaxAgeSeconds": 3000
     }
   ]
   ```

## CI/CD Pipeline

### GitHub Actions Workflow

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy BBWS Pompengan Jeneberang

on:
  push:
    branches:
      - main
      - staging

jobs:
  test:
    runs-on: ubuntu-24.04
    services:
      mysql:
        image: mysql:8.4
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: profilkorp_test
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mysql, gd, mbstring, xml, zip, bcmath, redis

      - name: Install Composer Dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Install Node Dependencies
        run: npm install

      - name: Copy .env.testing
        run: cp .env.testing .env

      - name: Generate Application Key
        run: php artisan key:generate

      - name: Run Migrations
        run: php artisan migrate --env=testing

      - name: Run Tests
        run: php artisan test

      - name: Build Frontend
        run: npm run build

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main' || github.ref == 'refs/heads/staging'

    steps:
      - uses: actions/checkout@v3

      - name: Deploy to VPS
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.VPS_HOST }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          script: |
            cd /var/www/profilkorp
            git fetch origin
            git checkout ${{ github.ref_name }}
            git pull origin ${{ github.ref_name }}
            
            composer install --optimize-autoloader --no-dev
            npm install
            npm run build
            
            php artisan migrate --force
            php artisan cache:clear
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            
            sudo systemctl restart php8.4-fpm
            sudo systemctl restart nginx
```

### Deployment Checklist

Before each deployment:

- [ ] All tests pass locally and in CI/CD
- [ ] Database migrations are reversible
- [ ] Environment variables are correctly set
- [ ] Static assets are built and optimized
- [ ] Backup of production database created
- [ ] Rollback plan documented and tested

## Monitoring & Logging

### Application Logging

Configure Laravel logging in `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'syslog'],
    ],
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],
    'syslog' => [
        'driver' => 'syslog',
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

### Server Monitoring

1. **Install Monitoring Tools**
   ```bash
   sudo apt install -y htop iotop nethogs
   ```

2. **Setup Uptime Monitoring**
   - Use UptimeRobot or Pingdom to monitor `https://www.profilkorp.com`
   - Configure alerts for downtime > 5 minutes

3. **Performance Monitoring**
   - Integrate Google Analytics for user behavior tracking
   - Use Laravel Telescope (development) or Sentry (production) for error tracking
   - Monitor database query performance with Laravel Debugbar (staging only)

### Sentry Integration (Error Tracking)

```bash
composer require sentry/sentry-laravel
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
```

Configure in `.env`:
```
SENTRY_LARAVEL_DSN=https://<key>@sentry.io/<project-id>
SENTRY_ENVIRONMENT=production
```

### Log Aggregation

For production, consider centralized logging:
- **ELK Stack** (Elasticsearch, Logstash, Kibana) on a separate server
- **Papertrail** or **Loggly** for cloud-based log management

## Caching Strategy

### Redis Configuration

Configure in `config/cache.php`:

```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',
    'lock_connection' => 'default',
],
```

### Cache Layers

1. **Page Caching** (for public pages)
   ```php
   Route::get('/about', function () {
       return cache()->remember('page.about', 3600, function () {
           return view('pages.about', ['data' => Page::find('about')]);
       });
   })->middleware('cache.headers:public;max_age=3600');
   ```

2. **Query Caching** (for frequently accessed data)
   ```php
   $services = cache()->remember('services.all', 86400, function () {
       return Service::all();
   });
   ```

3. **Config Caching**
   ```bash
   php artisan config:cache
   ```

4. **Route Caching**
   ```bash
   php artisan route:cache
   ```

### Cache Invalidation

Implement cache invalidation on content updates:

```php
// In Admin Controllers
public function updateService(Request $request, Service $service)
{
    $service->update($request->validated());
    cache()->forget('services.all');
    cache()->forget('page.services');
    return redirect()->back()->with('success', 'Service updated.');
}
```

## Database Management

### Backup Strategy

1. **Automated Daily Backups**
   ```bash
   # Create backup script: /usr/local/bin/backup-profilkorp.sh
   #!/bin/bash
   BACKUP_DIR="/backups/profilkorp"
   DATE=$(date +%Y%m%d_%H%M%S)
   
   mkdir -p $BACKUP_DIR
   mysqldump -u root -p$DB_PASSWORD profilkorp_prod > $BACKUP_DIR/profilkorp_$DATE.sql
   gzip $BACKUP_DIR/profilkorp_$DATE.sql
   
   # Upload to S3
   aws s3 cp $BACKUP_DIR/profilkorp_$DATE.sql.gz s3://profilkorp-backups/
   
   # Retain only last 30 days
   find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete
   ```

2. **Cron Job**
   ```bash
   sudo crontab -e
   # Add: 0 2 * * * /usr/local/bin/backup-profilkorp.sh
   ```

3. **S3 Backup Bucket**
   ```bash
   aws s3 mb s3://profilkorp-backups --region us-east-1
   aws s3api put-bucket-versioning \
     --bucket profilkorp-backups \
     --versioning-configuration Status=Enabled
   ```

### Database Optimization

```bash
# Run periodically
php artisan tinker
>>> DB::statement('OPTIMIZE TABLE services, blog_posts, team_members, testimonials, contact_submissions');
```

## Rollback Procedures

### Application Rollback

If a deployment introduces critical issues:

```bash
# 1. Identify the last stable commit
git log --oneline | head -20

# 2. Revert to previous version
git revert HEAD
# OR
git reset --hard <commit-hash>

# 3. Rebuild and restart
composer install --optimize-autoloader --no-dev
npm run build
php artisan migrate:rollback
php artisan migrate
    sudo systemctl restart php8.4-fpm nginx
    ```

### Database Rollback

```bash
# 1. Restore from backup
mysql -u root -p profilkorp_prod < /backups/profilkorp/profilkorp_<date>.sql

# 2. Verify data integrity
php artisan tinker
>>> DB::table('services')->count();
```

### Zero-Downtime Deployment (Advanced)

For critical updates, use a blue-green deployment strategy:

1. Deploy to a secondary server (green)
2. Run migrations and tests on green
3. Switch Nginx upstream to green
4. Keep blue as fallback

```nginx
upstream profilkorp_backend {
    server 192.168.1.10:9000 weight=100;  # Blue
    server 192.168.1.11:9000 weight=0;    # Green (standby)
}
```

## Security Hardening

### Application Security

1. **HTTPS Enforcement**
   - All traffic redirected to HTTPS
   - HSTS header enabled (see Nginx config above)

2. **CSRF Protection**
   - Enabled by default in Laravel
   - Verify in forms: `@csrf`

3. **Rate Limiting**
   ```php
   // In routes/web.php
   Route::post('/contact', 'ContactController@store')
       ->middleware('throttle:5,1');  // 5 requests per minute
   
    Route::post('/super-admin/login', 'AuthController@loginSuperAdmin')
        ->middleware('throttle:5,1');  // guard super_admin
    Route::post('/wilayah/login', 'AuthController@loginWilayah')
        ->middleware('throttle:5,1');  // guard wilayah — terpisah
    ```

4. **Admin Path Obfuscation — Opsi B Pisah URL (Tiga Path)**
   - **Opsi B:** pisah `SUPER_ADMIN_PATH` + `WILAYAH_PATH` + `KARYAWAN_PATH` — masing-masing random hash, guard terpisah, tidak cross-login.
   - Contoh prod: `SUPER_ADMIN_PATH=/super-admin-a7f3k9x2`, `WILAYAH_PATH=/wilayah-m2p8q1z4`, `KARYAWAN_PATH=/karyawan-b4n6r9w0` (dev: `/super-admin`, `/wilayah`, `/karyawan`).
   - `/admin` generik tidak ada di production — legacy alias hanya untuk backward compat.
   - Store in `.env`: `SUPER_ADMIN_PATH`, `WILAYAH_PATH`, `KARYAWAN_PATH`.

5. **Two-Factor Authentication (2FA)**
   ```bash
   composer require laravel-notification-channels/twilio
   # Implement TOTP-based 2FA for admin login
   ```

### Server Security

```bash
# Firewall configuration
sudo ufw enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw deny 3306/tcp  # MySQL only accessible locally

# Fail2Ban for brute-force protection
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
```

## Performance Optimization

### Frontend Optimization

1. **Asset Minification & Bundling**
    ```bash
    npm run build  # Vite 7 + Tailwind v4 handles minification
    ```

2. **Image Optimization**
   - Use WebP format for images
   - Implement lazy loading for portfolio images
   - Compress images before upload to S3

3. **Code Splitting**
    - Vite 7 automatically code-splits React 19 components
    - Lazy-load admin dashboard routes (React.lazy / Inertia prefetch)

### Backend Optimization

1. **Database Query Optimization**
   ```php
   // Use eager loading
   $services = Service::with('images', 'testimonials')->get();
   
   // Use select() to fetch only needed columns
   $posts = BlogPost::select('id', 'title', 'slug', 'created_at')->get();
   ```

2. **API Response Caching**
   ```php
   Route::get('/api/services', function () {
       return cache()->remember('api.services', 3600, function () {
           return Service::all();
       });
   });
   ```

3. **Database Indexing**
   ```php
   // In migrations
   Schema::create('blog_posts', function (Blueprint $table) {
       $table->id();
       $table->string('slug')->unique()->index();
       $table->string('title')->index();
       $table->timestamps();
   });
   ```

## Disaster Recovery Plan

### Recovery Time Objective (RTO) & Recovery Point Objective (RPO)

| Scenario | RTO | RPO | Action |
|:---|:---|:---|:---|
| **Database Corruption** | 30 min | 1 hour | Restore from S3 backup |
| **Server Failure** | 2 hours | 1 hour | Provision new VPS, restore DB, redeploy app |
| **Data Loss (S3)** | 24 hours | 24 hours | Restore from S3 versioning |
| **Complete Outage** | 4 hours | 1 hour | Failover to standby server |

### Disaster Recovery Checklist

- [ ] Weekly backup restoration test
- [ ] Document all critical credentials (stored in secure vault)
- [ ] Maintain runbook for common failure scenarios
- [ ] Test DNS failover procedures quarterly
- [ ] Document all third-party service dependencies (AWS, email provider)

## Maintenance & Updates

### Regular Maintenance Tasks

| Task | Frequency | Owner |
|:---|:---|:---|
| **Security Updates** | As released | DevOps |
| **Database Optimization** | Monthly | DevOps |
| **Log Rotation** | Weekly | Automated |
| **SSL Certificate Renewal** | Auto (60 days before expiry) | Certbot |
| **Backup Verification** | Weekly | DevOps |

### Laravel & Dependency Updates

```bash
# Check for outdated packages
composer outdated
npm outdated

# Update minor versions (safe)
composer update
npm update

# Update major versions (requires testing) — contoh untuk next major
composer require laravel/framework:^14
npm install react@20
```

## Deployment Runbook

### Standard Deployment Steps

1. **Pre-Deployment**
   ```bash
   # Create backup
   /usr/local/bin/backup-profilkorp.sh
   
   # Verify tests pass
   php artisan test
   npm run build
   ```

2. **Deployment**
   ```bash
   cd /var/www/profilkorp
   git pull origin main
   composer install --optimize-autoloader --no-dev
   npm install && npm run build
   php artisan migrate --force
   php artisan cache:clear
   ```

3. **Post-Deployment**
   ```bash
   # Verify application health
   curl -I https://www.profilkorp.com
   
   # Check error logs
   tail -f storage/logs/laravel.log
   
   # Monitor performance
   htop
   ```

4. **Rollback (if needed)**
```bash
# 3. Rebuild and restart
composer install --optimize-autoloader --no-dev
npm run build
php artisan migrate:rollback
php artisan migrate
sudo systemctl restart php8.4-fpm nginx
```

## Contact & Support

For deployment issues or questions:
- **DevOps Lead:** [contact information]
- **Emergency Hotline:** [phone number]
- **Incident Response:** See INCIDENT_RESPONSE.md