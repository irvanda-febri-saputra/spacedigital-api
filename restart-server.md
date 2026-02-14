# Restart Server untuk Apply CORS Changes

## SSH ke Server
```bash
ssh user@spacedigital.czel.me
cd /path/to/spacedigital-api
```

## Restart dan Apply Changes
```bash
# Option 1: Full update (recommended)
./deploy.sh update

# Option 2: Manual step by step
./deploy.sh restart
./deploy.sh artisan config:clear
./deploy.sh artisan config:cache
./deploy.sh artisan route:cache
```

## Test CORS
Setelah restart, coba akses https://client.veincloud.net/login lagi.

## Alternative via Panel/cPanel
Jika ada akses ke hosting panel:
1. File Manager -> restart service
2. Atau restart dari control panel
