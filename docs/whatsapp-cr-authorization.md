Contoh POST — notifikasi otorisasi CR eksternal (Mahadata / WhatsApp Cloud)
===============================================================================

### Template `notif_cr_manpro` — satu tombol URL «Tindak Lanjut»

| Placeholder body | Isi Laravel |
|------------------|-------------|
| `{{1}}` | Nomor CR (contoh `cr1122`) |
| `{{2}}` | Nama / judul Change Request |
| `{{3}}` | Nama pembuat |
| `{{4}}` | Deskripsi perubahan |

Tombol **Visit website** «Tindak Lanjut» di Meta:

```
https://manpro.bkkjateng.co.id/approval/{{1}}
```

Laravel mengisi `{{1}}` tombol dengan **`interaction_token`** (32 karakter).  
URL akhir: `https://manpro.bkkjateng.co.id/approval/{token}`

Halaman web menampilkan detail CR + tombol **Setujui** / **Tolak** + unduh PDF.

### Variabel `.env`

```dotenv
APP_URL=https://manpro.bkkjateng.co.id

MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME=notif_cr_manpro
MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_LANGUAGE_CODE=id
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=false
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=true
MAHADATA_WHATSAPP_CR_AUTH_SINGLE_URL_BUTTON=true

MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_TEMPLATE_NAME=konfirmasi_otorisasi_manpro
MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_ENABLED=true
```

Webhook inbound **tidak wajib**.

Contoh payload API: **`docs/examples/whatsapp-notif-cr-manpro.template.json`**

---

### Template legacy `change_request_manpro`

Lihat **`docs/examples/whatsapp-change-request-manpro-authorization.template.json`** (quick reply) dan **`docs/examples/whatsapp-change-request-manpro-url-buttons.template.json`** (dua tombol URL).

Set `MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME=change_request_manpro` dan sesuaikan body mapping jika masih memakai template lama.

---

### Konfirmasi balasan ke otorisator

Setelah Setujui/Tolak, Laravel mengirim template **`konfirmasi_otorisasi_manpro`**.

Contoh: **`docs/examples/whatsapp-konfirmasi-otorisasi-manpro.template.json`**
