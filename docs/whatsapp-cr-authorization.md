Contoh POST — notifikasi otorisasi CR eksternal (Mahadata / WhatsApp Cloud)
===============================================================================

### Tombol quick reply Setuju / Tolak + webhook

Saat **`MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=true`** (default), Laravel mengisi kedua tombol dengan payload dinamis:

- Tombol index **0** (Setuju): `APPROVE_CR_{token32}`
- Tombol index **1** (Tolak): `REJECT_CR_{token32}`

Ketukan tombol dikirim Mahadata/Meta ke webhook Laravel → **`{APP_URL}/webhook/whatsapp`**.

Template Meta harus memakai **Quick reply** (bukan Visit website / URL).

### Variabel `.env`

```dotenv
APP_URL=https://manpro.bkkjateng.co.id

MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=true
MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=false

WHATSAPP_WEBHOOK_VERIFY_TOKEN=token_sama_dengan_mahadata
WHATSAPP_CR_APPROVE_LABELS=Setuju
WHATSAPP_CR_REJECT_LABELS=Tidak,Tolak
```

### Webhook Laravel

**URL**: `GET|POST` **`https://manpro.bkkjateng.co.id/webhook/whatsapp`**

- **Verifikasi:** `hub.mode=subscribe` + `hub.verify_token` + `hub.challenge` (GET atau POST)
- **Event tombol:** POST JSON `interactive.button_reply` → keputusan CR dicatat

Subscribe event **Inbound Message Received** di Mahadata.

Lihat **`docs/examples/whatsapp-change-request-manpro-authorization.template.json`** untuk contoh payload API.

### Opsional — tombol URL (bukan webhook)

Set `MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS=false` dan `MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS=true` jika template memakai Visit website.
