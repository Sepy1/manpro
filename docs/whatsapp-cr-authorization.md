Contoh POST — notifikasi otorisasi CR eksternal (Mahadata / WhatsApp Cloud)
===============================================================================

### Template `konfirmasi_cr_manpro` — dua tombol URL «Tindak Lanjut» + «Lihat CR»

| Placeholder body | Isi Laravel |
|------------------|-------------|
| `{{1}}` | Nomor CR |
| `{{2}}` | Nama / judul Change Request |
| `{{3}}` | Nama pembuat |
| `{{4}}` | Daftar perubahan (`perubahan_diharapkan`, fallback `deskripsi_permintaan`) |

Tombol **Visit website** «Tindak Lanjut» di Meta:

```
https://manpro.bkkjateng.co.id/approval/{{1}}
```

Laravel mengisi `{{1}}` tombol dengan **`interaction_token`** (32 karakter).  
URL akhir: `https://manpro.bkkjateng.co.id/approval/{token}`

Tombol **Visit website** «Lihat CR» di Meta:

```
https://manpro.bkkjateng.co.id/viewcr/{{1}}
```

Laravel mengisi `{{1}}` tombol dengan **nomor CR**.  
URL akhir: `https://manpro.bkkjateng.co.id/viewcr/{nomor}` → halaman pratinjau PDF formulir CR + lampiran PDF.

Halaman web menampilkan detail CR + tombol **Setujui** / **Tolak** + unduh PDF.

### Variabel `.env`

```dotenv
APP_URL=https://manpro.bkkjateng.co.id

MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME=konfirmasi_cr_manpro
MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_LANGUAGE_CODE=id
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=false
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=true
MAHADATA_WHATSAPP_CR_AUTH_SINGLE_URL_BUTTON=false

MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_TEMPLATE_NAME=konfirmasi_otorisasi_manpro
MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_ENABLED=true
```

Webhook inbound **tidak wajib**.

Contoh payload API: **`docs/examples/whatsapp-konfirmasi-cr-manpro.template.json`**

---

### Template legacy `notif_cr_manpro` (satu tombol URL)

Set `MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME=notif_cr_manpro` dan `MAHADATA_WHATSAPP_CR_AUTH_SINGLE_URL_BUTTON=true`.

Contoh payload: **`docs/examples/whatsapp-notif-cr-manpro.template.json`**

---

### Template legacy `change_request_manpro`

Lihat **`docs/examples/whatsapp-change-request-manpro-authorization.template.json`** (quick reply) dan **`docs/examples/whatsapp-change-request-manpro-url-buttons.template.json`** (dua tombol URL).

Set `MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME=change_request_manpro` dan sesuaikan body mapping jika masih memakai template lama.

---

### Konfirmasi balasan ke otorisator

Setelah Setujui/Tolak, Laravel mengirim template **`konfirmasi_otorisasi_manpro`**.

Contoh: **`docs/examples/whatsapp-konfirmasi-otorisasi-manpro.template.json`**
