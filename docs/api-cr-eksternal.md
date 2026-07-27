# API CR Eksternal

API ini dipakai untuk aplikasi lain yang ingin menampilkan board CR eksternal dan mengubah status CR dengan pola drag-and-drop.

## Autentikasi

Setiap request wajib mengirim header:

```http
X-Extern-Cr-Api-Key: <plaintext-api-key>
```

API key disimpan di tabel database `extern_cr_api_keys` dalam bentuk hash SHA-256.

## Membuat API Key

Gunakan command berikut:

```bash
php artisan extern-cr:api-key:create "nama-integrasi"
```

Output command akan menampilkan:
- `ID`
- `Name`
- `Key` plaintext

Simpan `Key` tersebut sekali saja. Nilai plaintext tidak disimpan lagi di database.

## Endpoint

### 1. Ambil board CR

```http
GET /api/cr-eksternal/dashboard
```

Contoh response ringkas:

```json
{
  "ok": true,
  "board": {
    "title": "CR Eksternal",
    "columns": [
      {
        "key": "open",
        "title": "Backlog",
        "status": "open",
        "items": []
      }
    ],
    "status_options": [
      { "value": "open", "label": "Open" }
    ],
    "meta": {
      "total_items": 12,
      "generated_at": "2026-07-27T10:00:00+07:00"
    }
  }
}
```

### 2. Ambil detail CR

```http
GET /api/cr-eksternal/{externCr}
```

Response berisi:
- identitas CR
- status
- division, application, change reason, vendor pic
- attachment
- histori singkat

### 3. Update status CR

```http
PATCH /api/cr-eksternal/{externCr}/status
```

Body:

```json
{
  "status": "uat",
  "note": "Dipindah ke UAT setelah verifikasi vendor"
}
```

Status yang tersedia:
- `open`
- `vendor_development`
- `uat`
- `go_live`
- `closed`

Contoh response sukses:

```json
{
  "ok": true,
  "message": "Status diperbarui.",
  "item": {
    "id": 1,
    "nomor": "CR-2026-0001",
    "status": {
      "value": "uat",
      "label": "UAT"
    }
  }
}
```

## Status Kode

- `200` sukses
- `401` API key tidak valid / tidak aktif / kedaluwarsa
- `422` validasi gagal
- `503` tabel API key belum tersedia

## Headers Request Contoh

```http
GET /api/cr-eksternal/dashboard HTTP/1.1
Host: example.com
X-Extern-Cr-Api-Key: abc123...
Accept: application/json
```

## Catatan Integrasi

- Endpoint board mengelompokkan CR berdasarkan status, jadi cocok untuk UI model board/Trello.
- Saat status berubah, sistem juga tetap mencatat histori CR.
- Jika key perlu diganti, buat key baru lalu nonaktifkan key lama dengan mengubah `is_active = false` di tabel `extern_cr_api_keys`.
