Contoh POST — notifikasi otorisasi CR eksternal (Mahadata / WhatsApp Cloud)
===============================================================================

### Payload tombol “Setuju” / “Tidak” (disarankan)

Saat **`MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=true`**, Laravel mengisi **kedua tombol** dengan parameter **`type: payload`** (`payload` pertama = **`APPROVE_CR_<token>`** = setuju, kedua = **`REJECT_CR_<token>`** = tolak — token unik per penerima CR). Meta mengirim nilai tersebut kembali di **`interactive.button_reply.id`** ketika webhook diterima. Format lama **`APR_<token>`** / **`REJ_<token>`** masih diterima webhook untuk kompatibilitas mundur.

- Tekst tombol bisa tetap seperti di template Anda (“Setuju” / “Tidak”).
- **Template Anda di WhatsApp Manager harus menyediakan variabel/quick reply dengan payload dinamis** (sesuai aturan Meta untuk template Anda). Tanpa dukungan tersebut, kiriman API bisa gagal; hubungi Mahadata/Meta atau matikan tombol sampai template kompatibel.
- Jika payload tidak digunakan, aplikasi boleh memakai **fallback** mencocokkan judul tombol dengan `WHATSAPP_CR_APPROVE_LABELS` / `WHATSAPP_CR_REJECT_LABELS` (kurang aman untuk banyak CR sekaligus).

### Tanpa WhatsApp App Secret

Jika **`WHATSAPP_APP_SECRET`** Anda kosongkan, webhook **tetap diterima** (tanpa `X-Hub-Signature-256`). Gunakan jaringan/URL yang Anda percayai atau isi Secret bila telah tersedia.

### Variabel lingkungan (salin dari `docs/dotenv-whatsapp.example`)

Semua nama `ENV` untuk Mahadata + webhook + label tombol ada di **`docs/dotenv-whatsapp.example`**.

Lihat **`docs/examples/whatsapp-change-request-manpro-authorization.template.json`** untuk badan HTTP JSON lengkap yang selaras dengan `MahadataWhatsappExternCrAuthorizationNotifier`:

- **`body`** empat placeholder teks sesuai urutan di template Meta: nama/judul CR, pembuat, deskripsi singkat, link unduh gabungan PDF (URL bertanda sementara, route named `extern-cr.signed-pdf`).
- **`button` quick_reply indeks `0` dan `1`** — label tombol seperti “Setuju” / “Tidak” ditetapkan di **Meta Business Manager**. Event ketukan dikirim ke webhook Laravel Anda.

Webhook Laravel (implementasi aplikasi ini)
--------------------------------------------

**URL**: `GET` dan `POST` ke `{APP_URL}/webhook/whatsapp` (pastikan `APP_URL` HTTPS dan dapat dijangkau dari internet).

**Verifikasi awal Meta (GET)**

- Meta memanggil `GET /webhook/whatsapp?hub.mode=subscribe&hub.challenge=...&hub.verify_token=...`.
- Nilai `hub.verify_token` harus sama persis dengan `WHATSAPP_WEBHOOK_VERIFY_TOKEN` di `.env`.

**Event masuk (POST)**

- Laravel memverifikasi header **`X-Hub-Signature-256`** dengan **`WHATSAPP_APP_SECRET`** (App Secret aplikasi WhatsApp Cloud di Meta, bukan Bearer Mahadata).
- Untuk **`interactive` / `button_reply`**, server mencocokkan `context.id` dengan **`wam_id`** yang disimpan saat kirim template (tabel `whatsapp_cr_authorization_dispatches`). Tanpa itu, digunakan fallback CR terbaru ke nomor yang sama yang belum punya keputusan (kurang ideal bila beberapa CR paralel).

**Development**

- `WHATSAPP_WEBHOOK_SKIP_SIGNATURE_VALIDATE=true` hanya boleh di lingkungan lokal tidak publik — di production tetap **`false`** + `WHATSAPP_APP_SECRET` wajib terisi.

Pemetaan tombol ke keputusan
----------------------------

Sesuaikan `.env`:

- `WHATSAPP_CR_APPROVE_LABELS` — default `Setuju`
- `WHATSAPP_CR_REJECT_LABELS` — default `Tidak,Tolak`

Judul tombol dicek **tanpa peka besar/kecil**.

Jika WhatsApp mengembalikan error pengiriman terkait komponen tombol, set **`MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=false`** sampai struktur template cocok dengan body saja atau tombol Anda.

Mengaktifkan pengiriman otomatis saat CR baru
----------------------------------------------

```dotenv
MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME=change_request_manpro
MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_LANGUAGE_CODE=id
MAHADATA_WHATSAPP_CR_AUTH_NOTIFY_ON_CREATE=true
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=true
MAHADATA_WHATSAPP_CR_AUTH_ACCEPT_PROXY_MESSAGE_IDS=true
EXTERN_CR_SIGNED_PDF_URL_TTL_MINUTES=10080
```

Penerima adalah pengguna **Manajemen User** yang dicentang penerima otorisasi CR dengan nomor HP valid (`628…`).

### Uji kirim dummy (CLI, tanpa dispatch / tombol)

Untuk memastikan template Meta + endpoint Mahadata “normal” sebelum mencoba tombol Setuju/Tidak dari aplikasi:

```bash
php artisan mahadata:test-cr-auth-template <extern_cr_id> --to=628xxxxxxxxxx
```

- `<extern_cr_id>` harus ada di tabel **extern_crs** (dipakai hanya sebagai sumber placeholder teks: nama CR, pembuat, deskripsi, link PDF bertanda).
- Pengiriman **tidak** membuat baris `whatsapp_cr_authorization_dispatches` dan **tidak** menyertakan komponen quick reply.
- Eksekusi dianggap **sukses** bila Laravel mendapat **`messages[0].id`** dari penyedia (**`wamid.`** seperti Cloud API atau **`msg_…`** kalau penyedia Mahadata/busa). Secara bawaan **`MAHADATA_WHATSAPP_CR_AUTH_ACCEPT_PROXY_MESSAGE_IDS=true`** agar respons `msg_…` tetap dihitung sukses — set **`false`** hanya jika Anda menolak id non‑`wamid.`.

### Pesan «Berhasil kirim» tetapi tidak sampai WhatsApp?

1. Laravel (bawaan) menghitung **`msg_…`** di **`messages[0].id`** sebagai **sukses kirim HTTP** ketika **`MAHADATA_WHATSAPP_CR_AUTH_ACCEPT_PROXY_MESSAGE_IDS=true`** (nilai **default konfig**). Lepaskan (`false`) bila Anda hanya menerima **`wamid.`** sah Cloud API untuk audit ketat **`context.id`**.
2. Bila **`MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=true`** dan pertama kali gagal / id bukan `wamid.`, aplikasi mencoba **`fallback` satu kali kirim tanpa tombol quick reply**. Jika template Meta **memwajibkan** blok tombol, fallback bisa gagal: matikan tombol dengan env tersebut dan pastikan template hanya pakai placeholder **body**.
3. **Cek pengguna CEJ:** otorisator harus `can_authorize_extern_cr = true`, ada **nomor HP** valid `08… / 628…`, **role** termasuk salah satu dari `admin`, `manager`, `officer`, `vendor`, `cabang`; otherwise tidak ikut dikirimi.
4. Template harus aktif untuk nomor tersebut, kontak ada di WhatsApp, dan pembatas penyampaian template (`quality rating` / blokir user) bisa menunda tampilan di ponsel.
