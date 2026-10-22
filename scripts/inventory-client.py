"""Aplikasi client inventaris PC (Windows). Jalankan: python client.py"""
import sys, socket, platform, hashlib, psutil, requests
import tkinter as tk
from tkinter import ttk, messagebox
from urllib.parse import urlparse

SERVER_URL = "http://127.0.0.1:8000/api/inventory"   # ganti dengan IP server
OFFICES_URL = SERVER_URL.rstrip("/") + "/offices"
API_KEY = "GANTI_API_KEY_RAHASIA"                        # sama dengan server.py


JABATAN = ["Kepala Divisi", "Kepala Cabang", "Kepala Kantor Kas", "Kepala Bidang",
           "Kepala Sub Bidang", "Staf / AO / Admin", "Teller", "Customer Service"]
STATUS_PERANGKAT = ["Inventaris", "Device Pribadi (BYOD)"]
CHOICES = {"jabatan": JABATAN, "status_perangkat": STATUS_PERANGKAT}

JUNK_VALUES = {"to be filled by o.e.m.", "default string", "none", "not applicable",
               "not specified", "unknown", "system serial number", "system product name",
               "system manufacturer", "base board serial number", "n/a", "o.e.m.", "oem",
               "123456789", "0123456789", "xxxxxxxx"}


def _clean(v):
    """Buang nilai pabrikan yang tidak berguna (placeholder BIOS)."""
    v = (v or "").strip()
    if v.lower() in JUNK_VALUES or not v.strip("0-. "):
        return ""
    return v


def _read_smbios():
    """Baca tabel SMBIOS mentah dari firmware (Windows Vista+, tanpa admin)."""
    try:
        import ctypes
        k32 = ctypes.windll.kernel32
        sig = 0x52534D42  # 'RSMB'
        size = k32.GetSystemFirmwareTable(sig, 0, None, 0)
        if not size:
            return None
        buf = ctypes.create_string_buffer(size)
        if not k32.GetSystemFirmwareTable(sig, 0, buf, size):
            return None
        return buf.raw[:size]
    except Exception:
        return None


def _smb_str(formatted, strs, off):
    if off >= len(formatted):
        return ""
    idx = formatted[off]
    if idx == 0 or idx > len(strs):
        return ""
    return strs[idx - 1].decode("ascii", "ignore").strip()


def _parse_smbios(data):
    """Ambil info System (type 1) dan Baseboard/Mainboard (type 2)."""
    info = {}
    if not data or len(data) < 8:
        return info
    length = int.from_bytes(data[4:8], "little")
    table = data[8:8 + length]
    i = 0
    while i + 4 <= len(table):
        t, l = table[i], table[i + 1]
        if l < 4:
            break
        formatted = table[i:i + l]
        j = i + l
        end = table.find(b"\x00\x00", j)
        if end == -1:
            break
        raw = table[j:end]
        strs = raw.split(b"\x00") if raw else []
        if t in (1, 2):
            key = "sys" if t == 1 else "board"
            if key not in info:
                info[key] = {"manufacturer": _smb_str(formatted, strs, 4),
                             "product": _smb_str(formatted, strs, 5),
                             "serial": _smb_str(formatted, strs, 7)}
        if t == 127:
            break
        i = end + 2
    return info


def get_serial_and_mainboard():
    """Return (serial_number, mainboard_type)."""
    info = _parse_smbios(_read_smbios())
    sysi, board = info.get("sys", {}), info.get("board", {})
    serial = _clean(sysi.get("serial")) or _clean(board.get("serial"))
    bm, bp = _clean(board.get("manufacturer")), _clean(board.get("product"))
    if not (bm or bp):  # cadangan lewat registry
        try:
            import winreg
            k = winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE,
                               r"HARDWARE\DESCRIPTION\System\BIOS")
            bm = _clean(winreg.QueryValueEx(k, "BaseBoardManufacturer")[0])
            bp = _clean(winreg.QueryValueEx(k, "BaseBoardProduct")[0])
        except Exception:
            pass
    mainboard = " ".join(x for x in (bm, bp) if x) or "UNKNOWN"
    return serial or "UNKNOWN", mainboard


def get_cpu_name():
    try:
        import winreg
        k = winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE,
                           r"HARDWARE\DESCRIPTION\System\CentralProcessor\0")
        return winreg.QueryValueEx(k, "ProcessorNameString")[0].strip()
    except Exception:
        return platform.processor() or "Unknown"


def get_machine_guid():
    """ID unik Windows (tidak berubah walau nama PC / adapter diganti)."""
    try:
        import winreg
        # KEY_WOW64_64KEY: tetap baca registry 64-bit walau exe 32-bit
        k = winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE,
                           r"SOFTWARE\Microsoft\Cryptography",
                           0, winreg.KEY_READ | winreg.KEY_WOW64_64KEY)
        guid = winreg.QueryValueEx(k, "MachineGuid")[0].strip().lower()
        if guid:
            return guid
    except Exception:
        pass
    # cadangan jika registry tidak terbaca
    raw = f"{socket.gethostname()}-{get_cpu_name()}".encode("utf-8", "ignore")
    return "fallback-" + hashlib.sha1(raw).hexdigest()


def get_os_name():
    """Nama OS yang benar untuk Windows 7 / 10 / 11."""
    try:
        v = sys.getwindowsversion()
        if v.major == 10:
            name = "Windows 11" if v.build >= 22000 else "Windows 10"
        elif v.major == 6 and v.minor == 1:
            name = "Windows 7"
        elif v.major == 6 and v.minor == 3:
            name = "Windows 8.1"
        elif v.major == 6 and v.minor == 2:
            name = "Windows 8"
        else:
            name = f"Windows {v.major}.{v.minor}"
        return f"{name} (build {v.build})"
    except Exception:
        return f"{platform.system()} {platform.release()}"


def _describe_av(state):
    """Terjemahkan productState Security Center."""
    enabled = ((state >> 12) & 0xF) == 1
    outdated = ((state >> 4) & 0xF) != 0
    if not enabled:
        return False, "Nonaktif"
    return True, "Aktif, definisi usang" if outdated else "Aktif, definisi terbaru"


def _parse_av(output):
    """Parse baris 'NamaAV|productState' -> (status, daftar nama)."""
    items, seen = [], set()
    any_enabled = False
    for line in output.splitlines():
        line = line.strip()
        if "|" not in line:
            continue
        name, _, st = line.rpartition("|")
        try:
            enabled, desc = _describe_av(int(st))
        except ValueError:
            continue
        name = name.strip()
        if not name or name.lower() in seen:
            continue
        seen.add(name.lower())
        any_enabled = any_enabled or enabled
        items.append(f"{name} [{desc}]")
    if not items:
        return "Tidak terdeteksi", "Tidak ada"
    return ("Aktif" if any_enabled else "Nonaktif"), "; ".join(items)[:250]


def get_antivirus():
    """Return (status, nama). Status: Aktif / Nonaktif / Tidak terdeteksi / Tidak diketahui."""
    import subprocess
    ps = ("Get-WmiObject -Namespace root\\SecurityCenter2 -Class AntiVirusProduct | "
          "ForEach-Object { $_.displayName + '|' + $_.productState }")
    try:
        r = subprocess.run(
            ["powershell", "-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass",
             "-Command", ps],
            stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
            creationflags=0x08000000,  # CREATE_NO_WINDOW: tidak muncul jendela hitam
            timeout=25)
        if r.returncode != 0:
            return "Tidak diketahui", "-"
        return _parse_av(r.stdout.decode("utf-8", "ignore"))
    except Exception:
        return "Tidak diketahui", "-"


VIRTUAL_KEYWORDS = ("vmware", "virtualbox", "vethernet", "hyper-v", "loopback",
                    "bluetooth", "tap-", "tunnel", "vpn", "docker", "wsl", "pseudo",
                    "npcap", "tailscale", "zerotier")


def _usable_ip(ip):
    return bool(ip) and not ip.startswith(("127.", "169.254.", "0."))


def _mac_of(addrs):
    for a in addrs:
        if a.family == psutil.AF_LINK and a.address:
            return a.address.replace("-", ":").upper()
    return None


def _route_ip(target):
    """IP lokal yang dipakai OS untuk menuju target (tanpa mengirim paket)."""
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect((target, 80))
        return s.getsockname()[0]
    except Exception:
        return None
    finally:
        s.close()


def get_active_interface():
    """Return (ip, mac) dari adapter fisik yang aktif (WiFi/Ethernet)."""
    stats = psutil.net_if_stats()
    all_addrs = psutil.net_if_addrs()

    # 1) adapter yang dipakai menuju server, lalu menuju internet
    host = urlparse(SERVER_URL).hostname or "8.8.8.8"
    for target in (host, "8.8.8.8"):
        ip = _route_ip(target)
        if _usable_ip(ip):
            for name, addrs in all_addrs.items():
                if any(a.family == socket.AF_INET and a.address == ip for a in addrs):
                    mac = _mac_of(addrs)
                    if mac:
                        return ip, mac

    # 2) fallback: cari adapter fisik yang statusnya up, utamakan Wi-Fi/Ethernet
    candidates = []
    for name, addrs in all_addrs.items():
        st = stats.get(name)
        if not st or not st.isup or any(k in name.lower() for k in VIRTUAL_KEYWORDS):
            continue
        ipv4 = next((a.address for a in addrs
                     if a.family == socket.AF_INET and _usable_ip(a.address)), None)
        mac = _mac_of(addrs)
        if ipv4 and mac:
            prio = 0 if ("wi-fi" in name.lower() or "wifi" in name.lower()
                         or "wlan" in name.lower() or "ethernet" in name.lower()) else 1
            candidates.append((prio, ipv4, mac))
    if candidates:
        candidates.sort()
        return candidates[0][1], candidates[0][2]
    return "UNKNOWN", "UNKNOWN"


def get_disks():
    detail, total, free = [], 0, 0
    for p in psutil.disk_partitions(all=False):
        if "cdrom" in p.opts or not p.fstype:
            continue
        try:
            u = psutil.disk_usage(p.mountpoint)
        except Exception:
            continue
        total += u.total
        free += u.free
        detail.append(f"{p.device} {u.total/1024**3:.0f}GB (sisa {u.free/1024**3:.0f}GB)")
    return round(total/1024**3, 1), round(free/1024**3, 1), "; ".join(detail)


def collect():
    ip, mac = get_active_interface()
    serial, mainboard = get_serial_and_mainboard()
    av_status, av_name = get_antivirus()
    disk_total, disk_free, disk_detail = get_disks()
    return {
        "hostname": socket.gethostname(),
        "machine_guid": get_machine_guid(),
        "serial_number": serial,
        "mainboard_type": mainboard,
        "antivirus_status": av_status,
        "antivirus_name": av_name,
        "os_info": get_os_name(),
        "cpu_name": get_cpu_name(),
        "cpu_cores": psutil.cpu_count(logical=False),
        "cpu_threads": psutil.cpu_count(logical=True),
        "ram_gb": round(psutil.virtual_memory().total / 1024**3, 1),
        "disk_total_gb": disk_total,
        "disk_free_gb": disk_free,
        "disk_detail": disk_detail,
        "mac_address": mac,
        "ip_address": ip,
    }


def get_offices():
    """Ambil master kantor dan kantor kas aktif dari aplikasi server."""
    response = requests.get(OFFICES_URL, headers={"X-API-Key": API_KEY}, timeout=15)
    response.raise_for_status()
    payload = response.json()
    offices = payload.get("data")
    if not payload.get("ok") or not isinstance(offices, list):
        raise ValueError("Format daftar kantor dari server tidak valid.")
    if not offices:
        raise ValueError("Belum ada kantor aktif pada aplikasi server.")
    return offices


class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("Inventaris PC")
        self.resizable(False, False)
        self.info = collect()
        try:
            self.offices = get_offices()
        except (requests.RequestException, ValueError) as e:
            messagebox.showerror("Gagal Memuat Kantor",
                                 "Daftar kantor tidak dapat diambil dari server:\n" + str(e))
            self.after(0, self.destroy)
            return

        self.office_by_label = {
            f'{office["kode_kantor"]} - {office["nama_kantor"]}': office
            for office in self.offices
        }
        self.cash_office_by_label = {}

        frm = ttk.Frame(self, padding=15)
        frm.grid()

        ttk.Label(frm, text="Data User", font=("Segoe UI", 11, "bold")).grid(
            row=0, column=0, columnspan=2, sticky="w", pady=(0, 6))
        self.vars = {}
        for i, (key, label) in enumerate([("kantor", "Kantor"),
                                          ("kantor_kas", "Kantor Kas"),
                                          ("nama_user", "Nama User"),
                                          ("jabatan", "Jabatan"),
                                          ("status_perangkat", "Status Perangkat")], start=1):
            ttk.Label(frm, text=label + " :").grid(row=i, column=0, sticky="w", pady=3)
            self.vars[key] = tk.StringVar()
            if key == "kantor":
                w = ttk.Combobox(frm, textvariable=self.vars[key],
                                 values=list(self.office_by_label), state="readonly", width=35)
                w.bind("<<ComboboxSelected>>", self.on_office_selected)
            elif key == "kantor_kas":
                w = ttk.Combobox(frm, textvariable=self.vars[key], values=[],
                                 state="disabled", width=35)
                self.cash_office_widget = w
            elif key in CHOICES:
                w = ttk.Combobox(frm, textvariable=self.vars[key], values=CHOICES[key],
                                 state="readonly", width=35)
            else:
                w = ttk.Entry(frm, textvariable=self.vars[key], width=38)
            w.grid(row=i, column=1, pady=3, sticky="ew")

        ttk.Label(frm, text="Informasi Perangkat", font=("Segoe UI", 11, "bold")).grid(
            row=6, column=0, columnspan=2, sticky="w", pady=(12, 6))
        rows = [("Nama Komputer", self.info["hostname"]),
                ("OS", self.info["os_info"][:60]),
                ("Serial Number", self.info["serial_number"]),
                ("Mainboard", self.info["mainboard_type"]),
                ("CPU", f'{self.info["cpu_name"]}\n({self.info["cpu_cores"]} core / {self.info["cpu_threads"]} thread)'),
                ("RAM", f'{self.info["ram_gb"]} GB'),
                ("Harddisk", f'Total {self.info["disk_total_gb"]} GB, sisa {self.info["disk_free_gb"]} GB\n' + self.info["disk_detail"].replace("; ", "\n")),
                ("MAC Aktif", self.info["mac_address"]),
                ("IP Address", self.info["ip_address"]),
                ("Antivirus", self.info["antivirus_status"] + "\n" + self.info["antivirus_name"].replace("; ", "\n"))]
        for i, (k, v) in enumerate(rows, start=7):
            ttk.Label(frm, text=k + " :").grid(row=i, column=0, sticky="nw", pady=2)
            ttk.Label(frm, text=v, wraplength=300, justify="left").grid(row=i, column=1, sticky="w", pady=2)

        self.btn = ttk.Button(frm, text="Submit", command=self.submit)
        self.btn.grid(row=22, column=0, columnspan=2, pady=(14, 0), ipadx=20)

    def on_office_selected(self, _event=None):
        office = self.office_by_label.get(self.vars["kantor"].get())
        self.cash_office_by_label = {"Tidak ada / Kantor induk": None}
        if office:
            for cash_office in office.get("kantor_kas", []):
                name = cash_office.get("nama_kas") or "Tanpa nama"
                label = f'{cash_office["kode_kas"]} - {name}'
                self.cash_office_by_label[label] = cash_office
        self.cash_office_widget.config(values=list(self.cash_office_by_label), state="readonly")
        self.vars["kantor_kas"].set("Tidak ada / Kantor induk")

    def submit(self):
        data = dict(self.info)
        for k in ("kantor", "nama_user", "jabatan", "status_perangkat"):
            if not self.vars[k].get().strip():
                messagebox.showwarning("Peringatan", f"{k.replace('_', ' ').title()} wajib diisi.")
                return
        office = self.office_by_label[self.vars["kantor"].get()]
        cash_office = self.cash_office_by_label.get(self.vars["kantor_kas"].get())
        data["kantor_id"] = office["id"]
        data["kas_kantor_id"] = cash_office["id"] if cash_office else None
        data["nama_user"] = self.vars["nama_user"].get().strip()
        data["jabatan"] = self.vars["jabatan"].get().strip()
        data["status_perangkat"] = self.vars["status_perangkat"].get().strip()
        self.btn.config(state="disabled")
        self.update_idletasks()
        try:
            r = requests.post(SERVER_URL, json=data, headers={"X-API-Key": API_KEY}, timeout=15)
            if r.status_code == 200:
                messagebox.showinfo("Berhasil", "Data berhasil dikirim ke server.")
                self.destroy()
                return
            messagebox.showerror("Gagal", f"Server menolak ({r.status_code}):\n{r.text}")
        except requests.RequestException as e:
            messagebox.showerror("Gagal", f"Tidak dapat terhubung ke server:\n{e}")
        self.btn.config(state="normal")


if __name__ == "__main__":
    App().mainloop()
