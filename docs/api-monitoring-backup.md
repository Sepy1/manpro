# API Monitoring Backup

Server backup dapat mengirimkan event melalui endpoint berikut:

```text
POST /api/backup-monitoring/events
Content-Type: application/json
X-Backup-Api-Key: <BACKUP_MONITORING_API_KEY>
```

Atur `BACKUP_MONITORING_API_KEY` pada `.env` server aplikasi. Endpoint akan menolak request tanpa key yang valid dan membatasi hingga 60 request per menit.

Contoh body:

```json
{
  "tanggal": "2026-10-30 21:30:00",
  "nama_db": "db_core",
  "ukuran": 1250.50,
  "status": "SUCCESS",
  "lokasi_file": "/backup/20261030/db_core-20261030.sql.gz",
  "server": "DB-SERVER-01"
}
```

`tanggal` wajib menggunakan format `Y-m-d H:i:s`. Ukuran harus berupa angka nol atau lebih, sementara status diterima sebagai teks hingga 50 karakter.

Contoh response sukses (`201 Created`):

```json
{
  "ok": true,
  "message": "Event backup berhasil diterima.",
  "data": {
    "id": 1,
    "tanggal": "2026-10-30 21:30:00"
  }
}
```

Riwayat event dapat dilihat admin pada menu **Server Dan EndPoint → Monitoring Backup**.
