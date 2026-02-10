# HTTPS & Security Configuration Guide

## Overview

This document explains how ASPRI handles HTTPS and prevents mixed content errors in production.

## Mixed Content Error

**Problem**: When a site runs on HTTPS but tries to load resources via HTTP, browsers block these requests with a "mixed content" error.

**Solution**: ASPRI automatically enforces HTTPS in production and trusts proxy headers.

## Configuration Components

### 1. AppServiceProvider - HTTPS Forcing

**File**: `app/Providers/AppServiceProvider.php`

The `forceHttpsInProduction()` method automatically forces all URL generation to use HTTPS when `APP_ENV=production`.

```php
protected function forceHttpsInProduction(): void
{
    if (app()->isProduction()) {
        URL::forceScheme('https');
    }
}
```

This ensures:
- All `url()`, `route()`, `asset()` calls generate HTTPS URLs
- Redirects use HTTPS
- Form actions use HTTPS

### 2. TrustProxies Middleware

**File**: `app/Http/Middleware/TrustProxies.php`

When behind a reverse proxy (Nginx, Cloudflare, AWS ALB), the application needs to trust proxy headers to correctly detect HTTPS.

```php
protected $proxies = '*';  // Trust all proxies

protected $headers =
    Request::HEADER_X_FORWARDED_FOR |
    Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT |
    Request::HEADER_X_FORWARDED_PROTO |
    Request::HEADER_X_FORWARDED_AWS_ELB;
```

This middleware:
- Trusts `X-Forwarded-Proto` header from reverse proxy
- Correctly identifies HTTPS requests
- Enables proper URL generation in proxied environments

### 3. Environment Configuration

**File**: `.env`

Critical settings:

```env
# Set to production on live server
APP_ENV=production

# MUST use https:// for production domain
APP_URL=https://aspriai.my.id

# Disable debug in production
APP_DEBUG=false
```

## Deployment Checklist

### Before Deploying

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_URL=https://yourdomain.com` (with https://)
- [ ] Set `APP_DEBUG=false`
- [ ] Verify SSL certificate is installed on server/proxy
- [ ] Configure reverse proxy to pass correct headers

### Nginx Configuration Example

```nginx
server {
    listen 443 ssl http2;
    server_name aspriai.my.id;

    # SSL certificate configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    location / {
        proxy_pass http://localhost:8000;
        
        # Important: Pass these headers to Laravel
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name aspriai.my.id;
    return 301 https://$server_name$request_uri;
}
```

### Apache Configuration Example

```apache
<VirtualHost *:443>
    ServerName aspriai.my.id
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    # Proxy Configuration
    ProxyPreserveHost On
    
    # Important: Pass these headers to Laravel
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"
    
    ProxyPass / http://localhost:8000/
    ProxyPassReverse / http://localhost:8000/
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName aspriai.my.id
    Redirect permanent / https://aspriai.my.id/
</VirtualHost>
```

## Troubleshooting

### Still Getting Mixed Content Errors?

1. **Check `.env` file**:
   ```bash
   grep APP_URL .env
   # Should output: APP_URL=https://yourdomain.com
   ```

2. **Check APP_ENV**:
   ```bash
   grep APP_ENV .env
   # Should output: APP_ENV=production
   ```

3. **Clear cache**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   php artisan view:clear
   ```

4. **Check reverse proxy headers**:
   
   Add this to a controller temporarily:
   ```php
   dd([
       'scheme' => request()->getScheme(),
       'host' => request()->getHost(),
       'url' => url('/'),
       'asset' => asset('test'),
       'headers' => request()->headers->all(),
   ]);
   ```
   
   Verify:
   - `scheme` should be "https"
   - `url` should start with "https://"
   - `x-forwarded-proto` header should be ["https"]

5. **Verify SSL certificate**:
   ```bash
   curl -I https://yourdomain.com
   # Should return HTTP/2 200 or similar
   ```

### Cloudflare Users

If using Cloudflare:
- Set SSL/TLS mode to "Full" or "Full (Strict)"
- Ensure "Always Use HTTPS" is enabled
- The TrustProxies middleware will handle Cloudflare's headers

### Docker/Container Environments

If running in Docker behind a proxy:
- Ensure the proxy container passes `X-Forwarded-*` headers
- Map port 443 correctly in docker-compose.yml
- Set `APP_URL` to the public HTTPS domain

## Testing HTTPS Configuration

Run this command to verify HTTPS URLs:

```bash
php artisan tinker
```

Then in tinker:
```php
url('/');        // Should output: https://yourdomain.com
route('login');  // Should output: https://yourdomain.com/login
asset('css/app.css');  // Should output: https://yourdomain.com/css/app.css
```

All should return HTTPS URLs in production.

## Security Best Practices

1. **Always use HTTPS in production** - No exceptions
2. **Redirect HTTP to HTTPS** at the reverse proxy level
3. **Enable HSTS** (HTTP Strict Transport Security):
   ```nginx
   add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
   ```
4. **Keep SSL certificates updated** - Use Let's Encrypt for free auto-renewal
5. **Use strong SSL configuration** - Follow Mozilla's SSL Configuration Generator

## Additional Security Headers

Consider adding these headers in your reverse proxy:

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

## Support

If you continue experiencing mixed content errors after following this guide:
1. Check browser console for specific error messages
2. Identify which resources are being loaded via HTTP
3. Verify all third-party integrations use HTTPS
4. Check for hardcoded HTTP URLs in database content
