# Hetzner VPS — Complete Server Guide

Katogo Project · ubuntu-8gb-fsn1-1 · Provisioned: 2026-06-12 · Stack installed: 2026-06-12

---

## Table of Contents
1. [Server Identity & Specs](#1-server-identity--specs)
2. [Access Methods](#2-access-methods)
3. [SSH Setup (How It Was Done)](#3-ssh-setup-how-it-was-done)
4. [Storage Layout](#4-storage-layout)
5. [Network Configuration](#5-network-configuration)
6. [Current State — What Is Installed](#6-current-state--what-is-installed)
7. [Remaining Action Items Before Going Live](#7-remaining-action-items-before-going-live)
8. [How to Deploy the Katogo Laravel App Here](#8-how-to-deploy-the-katogo-laravel-app-here)
9. [Security Hardening Guide](#9-security-hardening-guide)
10. [Hetzner Cloud API](#10-hetzner-cloud-api)
11. [Useful Server Commands](#11-useful-server-commands)
12. [Credentials Reference](#12-credentials-reference)

---

## 1. Server Identity & Specs

| Property | Value |
|----------|-------|
| **Server name** | ubuntu-8gb-fsn1-1 |
| **Instance ID** | 140016749 |
| **Provider** | Hetzner Cloud |
| **Data centre** | fsn1-dc14 — Falkenstein, Germany (EU Central) |
| **OS** | Ubuntu 26.04 LTS "Resolute Raccoon" |
| **Kernel** | Linux 7.0.0-15-generic (64-bit x86) |
| **vCPUs** | 2 × AMD EPYC-Milan (2 threads per core) |
| **RAM** | 8 GB (7.6 GB usable) |
| **Primary disk** | 80 GB SSD (`/dev/sda`) → 75 GB root partition + 256 MB EFI |
| **Attached volume** | 20 GB (`/dev/sdb`) → mounted at `/mnt/HC_Volume_105999006` |
| **Total usable storage** | ~95 GB |
| **IPv4** | `91.98.42.156` |
| **IPv6** | `2a01:4f8:c015:bd5::1/64` |
| **IPv6 gateway** | `fe80::1` |
| **Hostname** | `ubuntu-8gb-fsn1-1` |
| **Timezone** | Africa/Kampala (EAT, UTC+3) |
| **Package mirror** | Hetzner internal mirror (very fast, in-datacenter) |
| **Provisioned** | 2026-06-12 |

---

## 2. Access Methods

### SSH — Primary Access (Key-Based, Recommended)

```bash
# Short alias (configured in ~/.ssh/config)
ssh hetzner-katogo

# Direct (equivalent)
ssh -i ~/.ssh/hetzner_katogo root@91.98.42.156
```

### SSH — Password Fallback (if key is lost)

```bash
ssh root@91.98.42.156
# Password: Katogo@2026!Hetz
```

> Password authentication is currently still enabled. See [Security Hardening](#9-security-hardening-guide) to disable it once key login is fully confirmed working.

### SFTP (File Transfer)

```bash
# Interactive SFTP session
sftp hetzner-katogo

# Upload a file
scp /local/file.txt hetzner-katogo:/root/

# Download a file
scp hetzner-katogo:/root/file.txt /local/path/

# Upload entire folder
scp -r /local/folder/ hetzner-katogo:/var/www/
```

### Hetzner Console (Emergency / Rescue)

If SSH is completely locked out:
- Go to: https://console.hetzner.com/projects/14940267
- Click the server → **Console** tab
- This gives browser-based terminal access regardless of SSH state

---

## 3. SSH Setup (How It Was Done)

This section documents exactly what was done so it can be reproduced on any new server.

### What happened

1. Hetzner sent a temporary root password: `wk3FhpWJuLLAKVE3jAKr`
2. Ubuntu forces a password change on first SSH login — the new password is: `Katogo@2026!Hetz`
3. A dedicated SSH key pair was generated locally (no passphrase — needed for automated scripts):

```bash
ssh-keygen -t ed25519 -C "katogo-hetzner" -f ~/.ssh/hetzner_katogo -N ""
```

4. The public key was installed on the server:

```bash
# Public key installed at /root/.ssh/authorized_keys
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAINT+nNpPilfqcp213/uQnGrfxtnOnCcMufwuzDOTbnAi katogo-hetzner
```

5. The older personal key (`~/.ssh/id_ed25519.pub`) was also added as a backup (requires passphrase):

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJUv605ESJ2SgUrZb0/1zHMTnABg4p0gOMeaO/C6pl0o mubs0x@gmail.com
```

6. `~/.ssh/config` entry was created on the local Mac:

```
Host hetzner-katogo
  HostName 91.98.42.156
  User root
  Port 22
  IdentityFile ~/.ssh/hetzner_katogo
  IdentitiesOnly yes
  ServerAliveInterval 60
  ServerAliveCountMax 3
  StrictHostKeyChecking accept-new
```

7. The same key (`hetzner_katogo`) was added to Hetzner Console → Security → SSH keys → **"katogo-hetzner"** as the default key. All future Hetzner servers created in this project will automatically get this key.

### SSH key files (local Mac)

| File | Purpose |
|------|---------|
| `~/.ssh/hetzner_katogo` | Private key — keep safe, never share |
| `~/.ssh/hetzner_katogo.pub` | Public key — safe to copy anywhere |

### Server's authorized_keys

Located at `/root/.ssh/authorized_keys` on the VPS. Contains two entries:
1. `hetzner_katogo` — primary, no passphrase, used by scripts
2. `id_ed25519` (mubs0x@gmail.com) — personal backup, has passphrase

---

## 4. Storage Layout

### Disks

```
NAME    SIZE   MOUNT POINT              USE
sda     76.3G  root disk
├─sda1  76G    /                        OS + all files (71 GB free)
├─sda14 1M     (BIOS boot partition)
└─sda15 256M   /boot/efi               EFI boot

sdb     20G    /mnt/HC_Volume_105999006  Hetzner Volume (attached, empty)
```

### The Hetzner Attached Volume (`/dev/sdb`)

This is a **separate, detachable 20 GB block storage volume** provided by Hetzner. It:
- Persists independently of the server — if the server is deleted, the volume survives
- Can be detached and re-attached to any other Hetzner server in the same datacenter
- Is currently **empty** (just `lost+found` from ext4 formatting)
- Is auto-mounted at `/mnt/HC_Volume_105999006`

**Recommended use:** Store the database data directory or uploaded user files here, so they survive server rebuilds.

### Recommended Directory Plan (once app is deployed)

```
/var/www/katogo/           ← Laravel app root
/var/www/katogo/public/    ← Nginx document root
/var/log/katogo/           ← Application logs
/mnt/HC_Volume_105999006/  ← Persistent volume
    mysql/                 ← MySQL data directory (move here)
    uploads/               ← Any local user uploads
    backups/               ← Database dumps
```

### Disk Usage Right Now

```
/          1.4 GB used / 75 GB total   (2% — almost empty)
/mnt/...   24 KB used / 20 GB total    (volume is empty)
```

---

## 5. Network Configuration

### IP Addresses

| Type | Address | Notes |
|------|---------|-------|
| IPv4 | `91.98.42.156` | Public, static (DHCP assigned but stable) |
| IPv6 | `2a01:4f8:c015:bd5::1` | Public, static |
| Loopback | `127.0.0.1` | Internal only |

### DNS Servers (IPv6)

```
2a01:4ff:ff00::add:2
2a01:4ff:ff00::add:1
```
These are Hetzner's own DNS resolvers. They are fast and reliable.

### Firewall Status

UFW is **active**. Only the following ports are open to the public internet:

| Port | Protocol | Service | Accessible From |
|------|----------|---------|----------------|
| 22 | TCP | SSH (OpenSSH) | Public internet |
| 80 | TCP | HTTP (Nginx) | Public internet |
| 443 | TCP | HTTPS (Nginx) | Public internet |
| 4000 | TCP+UDP | NoMachine remote desktop | Public internet |
| 5353 | UDP | mDNS (NoMachine dependency) | Public internet |
| 53 | TCP/UDP | systemd-resolved | Localhost only |
| 3306 | TCP | MySQL | Localhost only |
| 6379 | TCP | Redis | Localhost only |

> **Note on port 4000/5353:** These are opened for NoMachine (NX remote desktop), which provides graphical access to the server. If you don't need graphical access, run `ufw delete allow 4000` and `ufw delete allow 5353` to close them.

### Bandwidth

Hetzner provides **20 TB/month** of outbound traffic (included). Inbound is unlimited. Traffic within Hetzner's network (e.g., to the Storage Share) is **free and unmetered**.

---

## 6. Current State — What Is Installed

Full LEMP stack deployed and running as of 2026-06-12.

### Running Services

| Service | Version | Status | Notes |
| ------- | ------- | ------ | ----- |
| `nginx` | 1.28.3 | active | Port 80/443, katogo vhost configured |
| `php8.5-fpm` | 8.5.4 | active | Socket: `/run/php/php8.5-fpm.sock` |
| `mysql` | 8.4.9 | active | DB `katogo_3` + user `katogo` ready |
| `redis-server` | — | active | `localhost:6379`, no auth (local only) |
| `supervisor` | — | active | 2× queue workers configured |
| `fail2ban` | 1.1.0 | active | SSH jail enabled |
| `ufw` | — | active | Ports 22, 80, 443 open |
| `ssh` | OpenSSH | active | Key-only auth, password disabled |
| `cron` | — | active | Laravel scheduler registered |
| `chrony` | — | active | NTP time sync |
| `unattended-upgrades` | — | active | Auto security patches |
| `qemu-guest-agent` | — | active | Hetzner hypervisor integration |

### PHP 8.5 Extensions Loaded

`bcmath` · `curl` · `gd` · `intl` · `json` · `mbstring` · `mysqlnd` · `pdo_mysql` · `redis` · `xml` · `xmlreader` · `xmlwriter` · `zip` · `opcache`

### PHP-FPM Tuning (for 8 GB RAM)

| Setting | Value |
| ------- | ----- |
| `pm.max_children` | 20 |
| `pm.start_servers` | 5 |
| `memory_limit` | 512M |
| `upload_max_filesize` | 200M |
| `post_max_size` | 200M |
| `max_execution_time` | 300s |

### Other Tools

| Tool | Version | Location |
| ---- | ------- | -------- |
| Composer | 2.10.1 | `/usr/local/bin/composer` |
| Certbot | 4.0.0 | Ready — run when DNS is pointed here |
| git | system | `/usr/bin/git` |
| htop | system | `/usr/bin/htop` |
| rsync | system | `/usr/bin/rsync` |

---

## 7. Remaining Action Items Before Going Live

Infrastructure is fully set up. Only application-level steps remain.

### Done ✓

- [x] UFW firewall active (ports 22, 80, 443 only)
- [x] SSH password auth disabled — key-only login enforced
- [x] Timezone set to Africa/Kampala (EAT, UTC+3)
- [x] 2 GB swap created and persisted in `/etc/fstab`
- [x] fail2ban protecting SSH (bans after 5 failed attempts)
- [x] Nginx 1.28.3 — vhost configured for `/var/www/katogo`
- [x] PHP 8.5.4-FPM — all extensions loaded, tuned for 8 GB RAM
- [x] MySQL 8.4.9 — database `katogo_3` + user `katogo` ready
- [x] Redis — running on `localhost:6379`
- [x] Composer 2.10.1 — installed at `/usr/local/bin/composer`
- [x] Supervisor — queue worker config in place (2 workers, auto-restart)
- [x] Certbot 4.0.0 — installed, waiting for DNS
- [x] Laravel scheduler cron registered (`* * * * *`)
- [x] Directory structure created with correct `www-data` ownership

### Still To Do

- [x] **DNS pointed** — `munoapp.store` + `www.munoapp.store` → `91.98.42.156`
- [x] **SSL issued** — Let's Encrypt certificate, expires 2026-09-10, auto-renews
- [ ] **Configure `.env`** — fill in production values for DB, JWT, Pesapal, Flutterwave, Hetzner Storage keys
- [ ] **Run migrations** — `php artisan migrate --force` after `.env` is configured
- [ ] **Move MySQL data to volume** (optional but recommended) — move `/var/lib/mysql` → `/mnt/HC_Volume_105999006/mysql` so DB survives server rebuilds

---

## 8. How to Deploy the Katogo Laravel App Here

This is a step-by-step guide for when you're ready to deploy.

### Step 1 — System Setup

```bash
ssh hetzner-katogo

# Update system
apt update && apt upgrade -y

# Set timezone
timedatectl set-timezone Africa/Kampala

# Create 2 GB swap
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Enable UFW
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# Basic tools
apt install -y git curl unzip software-properties-common
```

### Step 2 — Install PHP 8.2 + Extensions

```bash
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-cli php8.2-mbstring php8.2-xml \
  php8.2-curl php8.2-zip php8.2-gd php8.2-mysql php8.2-redis \
  php8.2-bcmath php8.2-intl php8.2-tokenizer

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### Step 3 — Install MySQL

```bash
apt install -y mysql-server
mysql_secure_installation

# Create database
mysql -u root -p -e "
  CREATE DATABASE katogo_3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'katogo'@'localhost' IDENTIFIED BY 'StrongPass!2026';
  GRANT ALL PRIVILEGES ON katogo_3.* TO 'katogo'@'localhost';
  FLUSH PRIVILEGES;
"
```

### Step 4 — Install Nginx

```bash
apt install -y nginx

# Create Nginx config
cat > /etc/nginx/sites-available/katogo << 'EOF'
server {
    listen 80;
    server_name movies.mruodel.com;  # update when DNS is pointed here
    root /var/www/katogo/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    client_max_body_size 100M;
}
EOF

ln -s /etc/nginx/sites-available/katogo /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### Step 5 — Deploy Laravel

```bash
# Clone repo
git clone https://github.com/YOUR_REPO/katogo.git /var/www/katogo
cd /var/www/katogo

# Install dependencies
composer install --no-dev --optimize-autoloader

# Configure environment
cp .env.example .env
# Edit .env — fill in DB, JWT secret, Pesapal, Flutterwave, Hetzner keys

# Generate key
php artisan key:generate

# Set permissions
chown -R www-data:www-data /var/www/katogo
chmod -R 755 /var/www/katogo/storage /var/www/katogo/bootstrap/cache

# Run migrations
php artisan migrate --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 6 — SSL Certificate

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d movies.mruodel.com
```

### Step 7 — Queue Worker (Supervisor)

```bash
apt install -y supervisor

cat > /etc/supervisor/conf.d/katogo-worker.conf << 'EOF'
[program:katogo-worker]
command=php /var/www/katogo/artisan queue:work --sleep=3 --tries=3
directory=/var/www/katogo
autostart=true
autorestart=true
stderr_logfile=/var/log/katogo/worker.err.log
stdout_logfile=/var/log/katogo/worker.out.log
user=www-data
numprocs=2
EOF

supervisorctl reread
supervisorctl update
supervisorctl start katogo-worker:*
```

### Step 8 — Laravel Scheduler (Cron)

```bash
crontab -e
# Add this line:
* * * * * cd /var/www/katogo && php artisan schedule:run >> /dev/null 2>&1
```

---

## 9. Security Hardening Guide

Do these in order immediately after first login.

### 9.1 SSH Password Authentication — ALREADY DISABLED ✓

Password authentication has been disabled. Only SSH key login works.

Current config in `/etc/ssh/sshd_config.d/50-cloud-init.conf`:

```text
PasswordAuthentication no
```

To verify: `ssh -o PubkeyAuthentication=no root@91.98.42.156` — this will be rejected.

To re-enable if needed (e.g., key is lost):

```bash
# Use Hetzner Console (browser terminal) at console.hetzner.com/projects/14940267
sed -i 's/^PasswordAuthentication no/PasswordAuthentication yes/' /etc/ssh/sshd_config.d/50-cloud-init.conf
systemctl restart ssh
```

### 9.2 UFW Firewall — ACTIVE ✓

UFW is active with these rules:

```text
22/tcp    ALLOW   Anywhere   # SSH
80/tcp    ALLOW   Anywhere   # HTTP
443/tcp   ALLOW   Anywhere   # HTTPS
4000/tcp  ALLOW   Anywhere   # NoMachine remote desktop
4000/udp  ALLOW   Anywhere   # NoMachine remote desktop
5353/udp  ALLOW   Anywhere   # mDNS (NoMachine)
```

To check: `ufw status verbose`

### 9.3 fail2ban — ACTIVE ✓

fail2ban is running and protecting SSH (bans after 5 failed attempts in 10 minutes, 10-minute ban).

```bash
fail2ban-client status sshd    # see current bans
fail2ban-client set sshd unbanip <IP>   # manually unban an IP
```

### 9.4 Automatic Security Updates — ACTIVE ✓

`unattended-upgrades.service` runs automatically. No action needed.

### 9.5 Current SSH Configuration

| Setting | Value | Status |
|---------|-------|--------|
| `PermitRootLogin` | `yes` | OK — only root user exists |
| `PasswordAuthentication` | `no` | Secure ✓ |
| `KbdInteractiveAuthentication` | `no` | Secure ✓ |
| `X11Forwarding` | `yes` | Low risk without NoMachine |
| `UsePAM` | `yes` | Required for NoMachine |

---

## 10. Hetzner Cloud API

You have a Read+Write API token. This lets you manage everything about this server programmatically — resize it, create snapshots, attach volumes, manage firewalls, etc.

### Token

```
Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp
```

### API Base URL

```
https://api.hetzner.cloud/v1
```

### Authentication

All requests need this header:
```
Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp
```

### Common API Operations

#### List all servers
```bash
curl -H "Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp" \
  https://api.hetzner.cloud/v1/servers
```

#### Get server details (ID: 140016749)
```bash
curl -H "Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp" \
  https://api.hetzner.cloud/v1/servers/140016749
```

#### Get server metrics (CPU, disk, network)
```bash
curl -H "Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp" \
  "https://api.hetzner.cloud/v1/servers/140016749/metrics?type=cpu&start=2026-06-12T00:00:00Z&end=2026-06-12T23:59:59Z"
```

#### Create a server snapshot (before major changes)
```bash
curl -X POST \
  -H "Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp" \
  -H "Content-Type: application/json" \
  -d '{"description": "pre-deployment snapshot", "type": "snapshot"}' \
  https://api.hetzner.cloud/v1/servers/140016749/actions/create_image
```

#### Power on/off
```bash
# Power off
curl -X POST \
  -H "Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp" \
  https://api.hetzner.cloud/v1/servers/140016749/actions/poweroff

# Power on
curl -X POST \
  -H "Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp" \
  https://api.hetzner.cloud/v1/servers/140016749/actions/poweron
```

#### Rebuild (reinstall OS — DESTRUCTIVE)
```bash
curl -X POST \
  -H "Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp" \
  -H "Content-Type: application/json" \
  -d '{"image": "ubuntu-26.04"}' \
  https://api.hetzner.cloud/v1/servers/140016749/actions/rebuild
```

#### Add a Firewall via API
```bash
# Create firewall
curl -X POST \
  -H "Authorization: Bearer Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "katogo-firewall",
    "rules": [
      {"direction":"in","protocol":"tcp","port":"22","source_ips":["0.0.0.0/0","::/0"]},
      {"direction":"in","protocol":"tcp","port":"80","source_ips":["0.0.0.0/0","::/0"]},
      {"direction":"in","protocol":"tcp","port":"443","source_ips":["0.0.0.0/0","::/0"]}
    ]
  }' \
  https://api.hetzner.cloud/v1/firewalls
```

### Laravel Integration (HetznerApiService)

Add to `.env`:
```env
HETZNER_API_TOKEN=Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp
HETZNER_SERVER_ID=140016749
```

Use for monitoring server health from the admin panel:
```php
$response = Http::withToken(env('HETZNER_API_TOKEN'))
    ->get('https://api.hetzner.cloud/v1/servers/' . env('HETZNER_SERVER_ID'));
$server = $response->json('server');
$status = $server['status'];  // "running", "off", "starting" etc.
```

---

## 11. Useful Server Commands

### System

```bash
# Check server resources
htop                          # interactive (install: apt install htop)
free -h                       # RAM usage
df -h                         # disk usage
iostat -x 1                   # disk I/O
netstat -tlnp                 # open ports

# System logs
journalctl -f                 # live system log
journalctl -u nginx -f        # nginx logs
tail -f /var/log/syslog       # syslog

# Processes
ps aux | grep php             # PHP processes
systemctl status nginx        # nginx status
```

### File Transfer

```bash
# Upload from Mac to server
scp file.txt hetzner-katogo:/var/www/katogo/

# Download from server to Mac
scp hetzner-katogo:/var/log/nginx/error.log ~/Desktop/

# Sync folder (like rsync)
rsync -avz --progress ./katogo/ hetzner-katogo:/var/www/katogo/
```

### Quick Deployment (update code)

```bash
ssh hetzner-katogo '
  cd /var/www/katogo &&
  git pull origin main &&
  composer install --no-dev --optimize-autoloader --no-interaction &&
  php artisan migrate --force &&
  php artisan config:cache &&
  php artisan route:cache &&
  php artisan view:cache &&
  supervisorctl restart katogo-worker:*
'
```

### MySQL

```bash
mysql -u katogo -p katogo_3    # connect to DB
mysqldump -u katogo -p katogo_3 > /mnt/HC_Volume_105999006/backups/$(date +%Y%m%d).sql
```

---

## 12. Credentials Reference

All credentials below are stored in [.server-credentials](.server-credentials) (gitignored). This section is for reference — do not duplicate changes; edit `.server-credentials` as the single source of truth.

### VPS Access

| Key | Value |
|-----|-------|
| IP | `91.98.42.156` |
| User | `root` |
| Password | `Katogo@2026!Hetz` |
| SSH Alias | `ssh hetzner-katogo` |
| SSH Key | `~/.ssh/hetzner_katogo` |
| Console | https://console.hetzner.com/projects/14940267 |

### API Token

| Key | Value |
|-----|-------|
| Token | `Yr5yyZVS4YRhEKrkI0Ll9qgN2Cfg7XqCWalVSGGKHj55AHsunWoaElc7PtYY1xgp` |
| Scope | Read + Write |
| API URL | `https://api.hetzner.cloud/v1` |
| Server ID | `140016749` |
| Project ID | `14940267` |

### Storage Share (Nextcloud)

| Key | Value |
|-----|-------|
| Host | `https://nx100800.your-storageshare.de` |
| User | `mubahood360` |
| Password | `256Anjane...` |
| WebDAV | `https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/` |
| Guide | [HETZNER_STORAGE_GUIDE.md](HETZNER_STORAGE_GUIDE.md) |

---

## Conflict Avoidance Notes

This server is separate from the existing production server (`movies.mruodel.com` / `209.74.87.69`). To avoid conflicts:

| Item | Existing Server | New Hetzner VPS |
|------|----------------|-----------------|
| IP | `209.74.87.69` | `91.98.42.156` |
| SSH alias | _(none configured)_ | `hetzner-katogo` |
| SSH key | Password only | `~/.ssh/hetzner_katogo` |
| OS | AlmaLinux 9 (Webuzo) | Ubuntu 26.04 LTS |
| Purpose | Current production | Future / staging |
| DB | katogo_3 (MySQL) | Not yet installed |

**Do not point DNS to `91.98.42.156` until deployment is complete and tested.**

---

*This document covers the full state of the server as of 2026-06-12.*
*Update this document when the server configuration changes significantly.*
