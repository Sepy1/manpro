Contoh POST — notifikasi otorisasi CR eksternal (Mahadata / WhatsApp Cloud)
===============================================================================

### Mode disarankan: tombol URL Setujui / Tolak (tanpa webhook inbound)

Saat **`MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=true`** dan quick reply **false**, Laravel mengisi suffix dinamis tombol:

| Tombol | URL penuh |
|--------|-----------|
| **Setujui** (index 0) | `{APP_URL}/otorisasi/cr/setuju/{token32}` |
| **Tolak** (index 1) | `{APP_URL}/otorisasi/cr/setuju/reject-{token32}` |

Otorisator mengetuk tombol → browser terbuka → keputusan CR tercatat → template konfirmasi WA (opsional).

**Template Meta `change_request_manpro` — tombol Call-to-action → Visit website:**

Kedua tombol (Setujui & Tolak) set URL template = **`{{1}}`** saja (seluruh URL dinamis).

Laravel mengirim URL lengkap per tombol, contoh:

- Setujui: `https://manpro.bkkjateng.co.id/abc123…token32`
- Tolak: `https://manpro.bkkjateng.co.id/reject-abc123…token32`

Route pendek `GET /{token32}` dan `GET /reject-{token32}` (lihat `extern-cr.authorize.short`).

### Variabel `.env` (mode URL)

```dotenv
APP_URL=https://manpro.bkkjateng.co.id

MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME=change_request_manpro
MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_LANGUAGE_CODE=id
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=false
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=true

MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_TEMPLATE_NAME=konfirmasi_otorisasi_manpro
MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_ENABLED=true
```

Webhook inbound **tidak wajib** untuk mode URL.

---

### Mode alternatif: quick reply + webhook

Saat **`MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=true`**, Laravel mengisi payload:

- Tombol index **0** (Setujui): `APPROVE_CR_{token32}`
- Tombol index **1** (Tolak): `REJECT_CR_{token32}`

Ketukan tombol dikirim Mahadata/Meta ke webhook Laravel → **`{APP_URL}/webhook/whatsapp`**.

Template Meta harus memakai **Quick reply** (bukan Visit website).

```dotenv
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=true
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=false
WHATSAPP_WEBHOOK_VERIFY_TOKEN=token_sama_dengan_mahadata
```

---

### Konfirmasi balasan ke otorisator

Setelah otorisator mengetuk Setuju/Tolak (keputusan pertama), Laravel mengirim template **`konfirmasi_otorisasi_manpro`**:

| Placeholder | Isi |
|-------------|-----|
| `{{1}}` | Nama / nomor CR |
| `{{2}}` | `Disetujui` atau `Ditolak` |
| `{{3}}` | Link unduh bundel PDF CR (signed URL) |

Contoh payload URL buttons: **`docs/examples/whatsapp-change-request-manpro-url-buttons.template.json`**

Contoh payload quick reply: **`docs/examples/whatsapp-change-request-manpro-authorization.template.json`**

Contoh konfirmasi: **`docs/examples/whatsapp-konfirmasi-otorisasi-manpro.template.json`**
