# Fitur Log Akses User

Fitur Log Akses User menerima dan menyimpan aktivitas autentikasi pengguna dari perangkat seperti FortiGate. Seluruh data yang dikirim perangkat disimpan, sedangkan halaman aplikasi hanya menampilkan informasi utama dan menyediakan detail lengkap pada setiap log.

## Endpoint

```text
POST /api/user-access-logs/events
Content-Type: application/json
X-Security-Log-Api-Key: <SECURITY_LOG_API_KEY>
```

Endpoint menggunakan autentikasi yang sama dengan API Log Security. Atur nilai berikut pada `.env` server aplikasi:

```dotenv
SECURITY_LOG_API_KEY=api-key-rahasia
```

Request tanpa API key, atau dengan API key yang tidak sesuai, akan menerima respons `401 Unauthorized`. Jika API key belum dikonfigurasi pada server, endpoint mengembalikan `503 Service Unavailable`.

Endpoint dibatasi maksimal 60 request per menit. Satu request dapat berisi maksimal 500 event.

## Contoh Payload

Payload utama berbentuk array JSON:

```json
[
  {
    "vendor": "FortiGate",
    "device_name": "FortiGate-200F",
    "device_id": "FG200FT923904242",
    "virtual_domain": "root",
    "event_type": "admin_login_success",
    "event_category": "admin_login",
    "username": "admin",
    "auth_server": null,
    "source_ip": "192.176.1.201",
    "destination_ip": "192.176.1.240",
    "tunnel_ip": null,
    "source_interface": "https(192.176.1.201)",
    "destination_interface": null,
    "method": "https",
    "action": "login",
    "status": "success",
    "original_status": "success",
    "reason": "none",
    "profile": "super_admin",
    "date": "2026-11-03",
    "time": "16:11:15",
    "timezone": "+0700",
    "event_time": "1793697075141498321",
    "log_id": "0100032001",
    "log_description": "Admin login successful",
    "severity": "information",
    "message": "Administrator admin logged in successfully from https(192.176.1.201)",
    "raw_log": "date=2026-11-03 time=16:11:15 devid=\"FG200FT923904242\" devname=\"FortiGate-200F\" user=\"admin\" action=\"login\" status=\"success\""
  }
]
```

Endpoint juga menerima satu object JSON tanpa pembungkus array. Namun, format array disarankan agar beberapa event dapat dikirim dalam satu request.

## Validasi Data

Field wajib:

- `vendor`
- `device_name`
- `device_id`
- `event_type`
- `event_category`
- `username`
- `source_ip`
- `action`
- `status`
- `date`
- `time`
- `event_time`
- `log_id`
- `severity`
- `raw_log`

Ketentuan format:

- `source_ip`, `destination_ip`, dan `tunnel_ip` harus berupa alamat IPv4 atau IPv6 yang valid.
- `date` menggunakan format `Y-m-d`, misalnya `2026-11-03`.
- `time` menggunakan format `H:i:s`, misalnya `16:11:15`.
- Field lain yang tidak termasuk daftar wajib boleh bernilai `null`.
- Maksimal 500 event dapat dikirim dalam satu request.

Validasi dilakukan untuk seluruh batch sebelum data disimpan. Jika satu event tidak valid, seluruh request ditolak dan tidak ada event dari batch tersebut yang disimpan.

## Respons Sukses

Status HTTP: `201 Created`

```json
{
  "ok": true,
  "message": "1 log akses user berhasil diterima.",
  "data": {
    "count": 1,
    "ids": [1]
  }
}
```

`count` menunjukkan jumlah event yang berhasil disimpan dan `ids` berisi ID database untuk setiap event.

## Contoh cURL

```bash
curl -X POST "https://alamat-aplikasi.example/api/user-access-logs/events" \
  -H "Content-Type: application/json" \
  -H "X-Security-Log-Api-Key: api-key-rahasia" \
  --data-binary @user-access-logs.json
```

## Penyimpanan Database

Data disimpan dalam tabel `user_access_logs`. Semua field payload disimpan, termasuk `raw_log`. Field payload `date` dan `time` disimpan sebagai `event_date` dan `event_clock` agar pencarian dan pengurutan tanggal lebih efisien.

Kolom `created_at` dan `updated_at` mencatat waktu data diterima dan diperbarui oleh aplikasi.

## Halaman Aplikasi

Halaman hanya dapat dibuka oleh user dengan role `admin`:

```text
/admin/log-access-user
```

Menu tersedia pada **Server Dan EndPoint → Log Akses User**.

Informasi utama yang ditampilkan:

- Waktu event
- Username dan status
- Nama, ID, dan vendor perangkat
- Source IP dan source interface
- Method, action, dan event type
- Pesan event

Seluruh data tersimpan dapat dilihat melalui tombol **Detail lengkap** pada setiap baris. Halaman juga menyediakan filter kata kunci, status, perangkat, rentang tanggal, serta tindakan untuk menghapus satu log.

## Status Respons

| Status | Keterangan |
| --- | --- |
| `201 Created` | Seluruh event berhasil disimpan. |
| `401 Unauthorized` | API key kosong atau tidak valid. |
| `422 Unprocessable Entity` | Payload atau salah satu event tidak valid. |
| `429 Too Many Requests` | Batas 60 request per menit terlampaui. |
| `503 Service Unavailable` | `SECURITY_LOG_API_KEY` belum dikonfigurasi. |

