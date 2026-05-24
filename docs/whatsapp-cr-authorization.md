Contoh POST — notifikasi otorisasi CR eksternal (Mahadata / WhatsApp Cloud)
===============================================================================

### Tombol call-to-action (URL) Setuju / Tolak — selaras template Meta

Urutan variabel template `change_request_manpro`:

| Variabel Meta | Isi dari Laravel |
|---------------|------------------|
| `{{1}}`–`{{4}}` | Body: judul CR, pembuat, deskripsi, link PDF |
| `{{5}}` (tombol **Setujui**, index 0) | `interaction_token` (32 karakter) |
| `{{6}}` (tombol **Tolak**, index 1) | `reject-{interaction_token}` |

Base URL di Meta Business Manager (keduanya boleh sama):

- **Setujui:** `https://manpro.bkkjateng.co.id/otorisasi/cr/setuju/{{5}}`
- **Tolak:** `https://manpro.bkkjateng.co.id/otorisasi/cr/setuju/{{6}}`

Contoh URL lengkap setelah otorisator mengetuk:

- Setujui → `https://manpro.bkkjateng.co.id/otorisasi/cr/setuju/abc123…token32…`
- Tolak → `https://manpro.bkkjateng.co.id/otorisasi/cr/setuju/reject-abc123…token32…`

> **Opsional:** jika Anda ubah base tombol Tolak menjadi `…/otorisasi/cr/tolak/{{6}}`, Laravel cukup mengisi `{{6}}` dengan token saja (route `/otorisasi/cr/tolak/{token}` tetap tersedia).

### Konfigurasi template Meta Business Manager

1. Buat/ubah template `change_request_manpro` (atau nama sesuai `.env`).
2. Body: empat placeholder teks (judul CR, pembuat, deskripsi, link PDF).
3. Tambahkan **dua tombol URL** dengan base URL persis seperti di atas (harus diakhiri `/`).
4. Pastikan `APP_URL` di `.env` sama dengan domain HTTPS yang terdaftar di template.

### Variabel lingkungan (salin dari `docs/dotenv-whatsapp.example`)

Semua nama `ENV` untuk Mahadata + label tombol ada di **`docs/dotenv-whatsapp.example`**.

Lihat **`docs/examples/whatsapp-change-request-manpro-authorization.template.json`** untuk badan HTTP JSON lengkap yang selaras dengan `MahadataWhatsappExternCrAuthorizationNotifier`:

- **`body`** empat placeholder teks sesuai urutan di template Meta: nama/judul CR, pembuat, deskripsi singkat, link unduh gabungan PDF (URL bertanda sementara, route named `extern-cr.signed-pdf`).
- **`button` url indeks `0` dan `1`** — label tombol seperti “Setuju” / “Tolak” ditetapkan di **Meta Business Manager**. Parameter teks = suffix token; Laravel membuka halaman otorisasi web.

Webhook WhatsApp (opsional, kompatibilitas lama)
------------------------------------------------

Jalur **quick reply + webhook** tidak lagi dipakai untuk kiriman baru. Route **`/webhook/whatsapp`** tetap ada bila Anda masih menerima pesan lama dengan payload `APPROVE_CR_` / `REJECT_CR_`.

**URL**: `GET` dan `POST` ke `{APP_URL}/webhook/whatsapp` (satu route).

**Verifikasi (GET atau POST)** — Meta/Mahadata mengirim `hub.mode=subscribe`, `hub.verify_token`, `hub.challenge` (bisa di query string atau body JSON `hub_mode` / `hub_mode`). Laravel membalas **HTTP 200** dengan body = nilai `hub.challenge` (plain text).

Pastikan `WHATSAPP_WEBHOOK_VERIFY_TOKEN` di `.env` **sama persis** dengan Verify token di dashboard WABA/Mahadata.

Mengaktifkan pengiriman otomatis saat CR baru
----------------------------------------------

```dotenv
MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME=change_request_manpro
MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_LANGUAGE_CODE=id
MAHADATA_WHATSAPP_CR_AUTH_NOTIFY_ON_CREATE=true
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=true
MAHADATA_WHATSAPP_CR_AUTH_ACCEPT_PROXY_MESSAGE_IDS=true
EXTERN_CR_SIGNED_PDF_URL_TTL_MINUTES=10080
APP_URL=https://domain-publik-anda.test
```

Penerima adalah pengguna **Manajemen User** yang dicentang penerima otorisasi CR dengan nomor HP valid (`628…`).

### Uji kirim dummy (CLI, tanpa dispatch / tombol)

Untuk memastikan template Meta + endpoint Mahadata “normal” sebelum mencoba tombol Setuju/Tolak dari aplikasi:

```bash
php artisan mahadata:test-cr-auth-template <extern_cr_id> --to=628xxxxxxxxxx
```

- `<extern_cr_id>` harus ada di tabel **extern_crs** (dipakai hanya sebagai sumber placeholder teks: nama CR, pembuat, deskripsi, link PDF bertanda).
- Pengiriman **tidak** membuat baris `whatsapp_cr_authorization_dispatches` dan **tidak** menyertakan komponen tombol URL.
- Eksekusi dianggap **sukses** bila Laravel mendapat **`messages[0].id`** dari penyedia.

### Pesan «Berhasil kirim» tetapi tidak sampai WhatsApp?

1. Periksa **`APP_URL`** — harus HTTPS publik dan cocok dengan base URL tombol di template Meta.
2. Bila **`MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=true`** dan pertama kali gagal, aplikasi mencoba **`fallback` satu kali kirim tanpa tombol**. Jika template Meta **memwajibkan** blok tombol, fallback bisa gagal: matikan tombol dengan env tersebut dan pastikan template hanya pakai placeholder **body**.
3. **Cek pengguna:** otorisator harus `can_authorize_extern_cr = true`, ada **nomor HP** valid `08… / 628…`, **role** termasuk salah satu dari `admin`, `manager`, `officer`, `vendor`, `cabang`.
4. Template harus aktif untuk nomor tersebut, kontak ada di WhatsApp, dan pembatas penyampaian template bisa menunda tampilan di ponsel.
