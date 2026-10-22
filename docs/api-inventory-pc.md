# API Inventaris PC

Endpoint untuk menerima data dari `client.py`:

- Method: `POST`
- URL: `/api/inventory`
- Header: `X-API-Key: <INVENTORY_API_KEY>`
- Content-Type: `application/json`

Endpoint master kantor yang dipanggil saat aplikasi client dibuka:

- Method: `GET`
- URL: `/api/inventory/offices`
- Header: `X-API-Key: <INVENTORY_API_KEY>`

Hanya kantor dan kantor kas aktif yang dikirim. Payload inventaris menggunakan
`kantor_id` dan `kas_kantor_id`; server menyimpan kode kantor/kas sebagai snapshot.

Atur kunci rahasia server di `.env`:

```dotenv
INVENTORY_API_KEY=kunci-rahasia-yang-panjang-dan-acak
```

Nilai yang sama harus dipasang pada konstanta `API_KEY` di `client.py`. Ubah juga
`SERVER_URL` menjadi URL aplikasi ini, misalnya:

```python
SERVER_URL = "https://aplikasi.example.com/api/inventory"
API_KEY = "kunci-rahasia-yang-panjang-dan-acak"
```

Jalankan migrasi setelah deployment:

```shell
php artisan migrate
php artisan config:clear
```

Data akan dibuat atau diperbarui berdasarkan `machine_guid`, sehingga satu PC hanya
memiliki satu record. Respons sukses selalu menggunakan status HTTP 200.
