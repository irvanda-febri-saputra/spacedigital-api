# 🐳 Docker Deployment Guide - SpaceDigital Dashboard

Panduan lengkap untuk deploy SpaceDigital Dashboard menggunakan Docker di VPS.

---

## 📋 Prerequisites

- VPS dengan Ubuntu/Debian
- Docker & Docker Compose sudah terinstall
- Cloudflare account (untuk tunnel)

---

## 🚀 Quick Start (5 Menit!)

### 1. Upload Project ke VPS

```bash
# Di VPS, clone atau upload project
cd /var/www
git clone https://github.com/your-username/spacedigital-dashboard.git
cd spacedigital-dashboard

# Atau upload via SCP dari komputer lokal:
# scp -r ./spacedigital-dashboard user@your-vps:/var/www/
```

### 2. Setup Environment

```bash
# Copy environment file
cp .env.docker .env

# Edit dengan nilai production kamu
nano .env
```

**Yang WAJIB diubah di `.env`:**
```env
APP_KEY=                              # Akan di-generate otomatis
APP_URL=https://your-domain.com       # Domain kamu
DB_PASSWORD=your_secure_password      # Password database
CLOUDFLARE_TUNNEL_TOKEN=xxx           # Token dari Cloudflare
```

### 3. Deploy! 🎉

```bash
# Beri permission pada script
chmod +x deploy.sh

# Jalankan deployment
./deploy.sh deploy
```

Selesai! Aplikasi akan jalan 24/7 dengan auto-restart.

---

## 📁 File Structure

```
project/
├── Dockerfile              # Multi-stage build Laravel
├── docker-compose.yml      # Service definitions
├── .dockerignore           # Files to exclude from build
├── .env.docker             # Environment template
├── deploy.sh               # Deployment script
└── docker/
    ├── nginx.conf          # Nginx configuration
    ├── supervisord.conf    # Process manager config
    ├── php.ini             # PHP settings
    └── mysql-init/         # MySQL initialization scripts
```

---

## 🔧 Useful Commands

```bash
# Start semua containers
./deploy.sh start

# Stop semua containers
./deploy.sh stop

# Restart containers
./deploy.sh restart

# Lihat logs
./deploy.sh logs

# Lihat status
./deploy.sh status

# Update aplikasi (pull, rebuild, migrate)
./deploy.sh update

# Masuk ke shell container
./deploy.sh shell

# Jalankan artisan command
./deploy.sh artisan migrate
./deploy.sh artisan tinker
./deploy.sh artisan queue:restart
```

---

## 🌐 Cloudflare Tunnel Setup

### 1. Buat Tunnel di Cloudflare

1. Buka [Cloudflare Zero Trust Dashboard](https://one.dash.cloudflare.com)
2. Pergi ke **Access** → **Tunnels**
3. Klik **Create a tunnel**
4. Beri nama tunnel (misal: `spacedigital`)
5. Copy **tunnel token**

### 2. Configure Tunnel Routes

Di Cloudflare dashboard, tambahkan routes:

| Public hostname | Service |
|----------------|---------|
| `yourdomain.com` | `http://app:80` |
| `ws.yourdomain.com` | `http://reverb:8080` |

### 3. Update .env

```env
CLOUDFLARE_TUNNEL_TOKEN=eyJhIjoixx...
VITE_REVERB_HOST=ws.yourdomain.com
```

---

## 🔍 Troubleshooting

### Container tidak start

```bash
# Cek logs
docker-compose logs app
docker-compose logs mysql

# Cek apakah port sudah dipakai
sudo lsof -i :80
sudo lsof -i :3306
```

### Database connection error

```bash
# Tunggu MySQL ready (biasanya 30 detik)
docker-compose logs mysql

# Test connection
docker-compose exec app php artisan db:show
```

### WebSocket tidak connect

```bash
# Cek Reverb logs
docker-compose logs reverb

# Test dari dalam container
docker-compose exec app curl http://reverb:8080
```

### Clear cache

```bash
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

---

## 📊 Service Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Internet                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Cloudflare Tunnel                            │
│                    (cloudflared container)                      │
└─────────────────────────────────────────────────────────────────┘
                    │                      │
                    ▼                      ▼
          ┌─────────────────┐    ┌─────────────────┐
          │   Nginx (:80)   │    │  Reverb (:8080) │
          │   Static files  │    │   WebSocket     │
          └─────────────────┘    └─────────────────┘
                    │
                    ▼
          ┌─────────────────────────────────────────┐
          │           App Container                 │
          │  ┌─────────────────────────────────┐   │
          │  │         PHP-FPM (:9000)         │   │
          │  └─────────────────────────────────┘   │
          │  ┌─────────────────────────────────┐   │
          │  │      Queue Worker (2 procs)     │   │
          │  └─────────────────────────────────┘   │
          │  ┌─────────────────────────────────┐   │
          │  │      Atlantic Daemon            │   │
          │  └─────────────────────────────────┘   │
          │  ┌─────────────────────────────────┐   │
          │  │      Scheduler (cron)           │   │
          │  └─────────────────────────────────┘   │
          └─────────────────────────────────────────┘
                    │
                    ▼
          ┌─────────────────┐    ┌─────────────────┐
          │  MySQL (:3306)  │    │  Redis (:6379)  │
          │    Database     │    │     Cache       │
          └─────────────────┘    └─────────────────┘
```

---

## ✅ What's Running 24/7

| Service | Description |
|---------|-------------|
| **PHP-FPM** | Laravel application server |
| **Nginx** | Web server for HTTP requests |
| **Queue Worker** | Background job processing |
| **Atlantic Daemon** | Payment polling service |
| **Scheduler** | Laravel scheduled tasks |
| **Reverb** | WebSocket server |
| **MySQL** | Database |
| **Redis** | Cache & sessions |
| **Cloudflared** | Tunnel to internet |

Semua service memiliki:
- ✅ Auto-restart jika crash
- ✅ Auto-start saat VPS reboot
- ✅ Health checks
- ✅ Log rotation

---

## 🔐 Security Notes

1. **Ubah semua passwords** di `.env` dengan nilai yang kuat
2. **Jangan commit** file `.env` ke git
3. **Gunakan HTTPS** via Cloudflare tunnel
4. **Batasi akses** ke port MySQL (hanya internal network)

---

## 💡 Tips

- Gunakan `./deploy.sh logs` untuk monitor real-time
- Backup database rutin: `docker-compose exec mysql mysqldump -u root -p spacedigital > backup.sql`
- Monitor disk space: `docker system df`
- Clean unused images: `docker system prune -a`

---

**Happy Deploying! 🚀**
