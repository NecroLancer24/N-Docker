# N-Docker — Stack Database Lokal

Stack Docker Compose untuk MySQL 8.4 + PostgreSQL 17 dengan phpMyAdmin,
lengkap dengan hardening keamanan dan cadangan (backup) otomatis.

## Isi

| Layanan | Image | Port | Keterangan |
|---|---|---|---|
| `mysql` | mysql:8.4 | 127.0.0.1:3306 | DB utama, volume `mysql_data` |
| `postgres` | postgres:17 | 127.0.0.1:5432 | DB kedua, volume `postgres_data` |
| `phpmyadmin-dev` | phpmyadmin:5 | 127.0.0.1:8080 | Profil `dev`, root diizinkan |
| `phpmyadmin-prod` | phpmyadmin:5 | `${PMA_BIND}:8080` | Profil `prod`, root ditolak |
| `mysql-backup` | databack/mysql-backup | — | Dump otomatis |
| `postgres-backup` | prodrigestivill/postgres-backup-local | — | Dump otomatis |

## Mulai cepat

```bash
cp .env.example .env       # lalu isi password yang kuat
docker compose up -d       # MySQL + Postgres + backup saja
```

### Dengan phpMyAdmin

```bash
docker compose --profile dev up -d     # localhost saja, root diizinkan
docker compose --profile prod up -d    # terbuka ke jaringan, root ditolak
```

phpMyAdmin: <http://localhost:8080>

## Hardening keamanan

- Semua port DB terikat ke `127.0.0.1` — tidak bisa diakses dari jaringan.
- `cap_drop: ALL` pada semua kontainer; hanya kemampuan minimum yang diaktifkan kembali.
- `no-new-privileges:true` mencegah eskalasi privilese di dalam kontainer.
- Batas sumber daya: `mem_limit` 1 GB untuk DB, serta `pids_limit`.
- Jaringan `db_internal` (`internal: true`) mengisolasi trafik DB ↔ backup dari internet.
- Profil `prod` menolak login root maupun akun tanpa password di phpMyAdmin.
- Kredensial hanya di `.env` (git-ignored), contoh tersedia di `.env.example`.

## Backup otomatis

Dump harian disimpan di `backups/` (git-ignored):

- `backups/mysql/*.tgz` — pertama langsung berjalan, lalu tiap 24 jam, simpan 14 dump terakhir.
- `backups/postgres/*.sql.gz` — jadwal `@daily`, simpan 7 hari + 2 minggu.

### Memulihkan

MySQL (dari file `.tgz`):

```bash
docker run --rm --network ndocker_db_internal \
  -e DB_SERVER=mysql -e DB_PORT=3306 -e DB_USER=root -e DB_PASS=<root-password> \
  -e "DB_RESTORE_TARGET=/backup/db_backup_<timestamp>.tgz" \
  -v ./backups/mysql:/backup \
  docker.io/databack/mysql-backup:latest restore
```

PostgreSQL:

```bash
gunzip -c backups/postgres/<file>.sql.gz | \
  docker compose exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"
```

## Struktur

```
.
├── docker-compose.yaml           # seluruh stack + hardening
├── .env.example                  # contoh variabel lingkungan
├── phpmyadmin/
│   └── config.user.inc.php       # proteksi root & tanpa-password (profil prod)
└── backups/                      # hasil dump otomatis (git-ignored)
```
