# API Log Security

n8n dapat mengirimkan event serangan FortiGate ke endpoint berikut:

```text
POST /api/security-logs/events
Content-Type: application/json
X-Security-Log-Api-Key: <SECURITY_LOG_API_KEY>
```

Atur `SECURITY_LOG_API_KEY` pada `.env` server aplikasi. Endpoint menerima maksimal 60 request per menit. Setiap request membuat satu log baru.

Contoh body JSON:

```json
{
  "device": "FGT-EDGE-01",
  "severity": "high",
  "attack": "SQL Injection",
  "attackid": "12345",
  "subtype": "ips",
  "action": "blocked",
  "srcip": "192.0.2.10",
  "srcport": 54321,
  "dstip": "198.51.100.20",
  "dstport": 443,
  "service": "HTTPS",
  "method": "GET",
  "url": "/login",
  "time": "2026-11-01 21:00:00"
}
```

`device`, `severity`, `attack`, `attackid`, `subtype`, `action`, `srcip`, `srcport`, `dstip`, `dstport`, `service`, dan `time` wajib dikirim. Alamat IP dan port divalidasi; port harus berada pada rentang 0–65535. `method` dan `url` opsional dan akan disimpan sebagai `-` jika tidak dikirim.

Contoh response sukses (`201 Created`):

```json
{
  "ok": true,
  "message": "Log security berhasil diterima.",
  "data": {
    "id": 1,
    "time": "2026-11-01 21:00:00"
  }
}
```

Log dapat dicari dan dihapus admin melalui menu **Server Dan EndPoint → Log Security**.
