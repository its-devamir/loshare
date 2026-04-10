# Loshare

**One PHP file** to move files between your **phone and PC** on the same **local network**—great for **iOS**, where getting files off the device is painful. Nothing is uploaded to the cloud.

[![CI](https://github.com/its-devamir/loshare/actions/workflows/ci.yml/badge.svg)](https://github.com/its-devamir/loshare/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)

## What you need

- **PHP 8.0 or newer** on the computer that will **run** Loshare ([Windows downloads](https://windows.php.net/download/), [php.net](https://www.php.net/downloads.php) for macOS/Linux, or install via your package manager).
- The **`fileinfo` extension** enabled (it usually is by default).
- A **modern browser** on each device (Safari on iPhone is fine).

You do **not** need Node, Composer, or a database.

## Download this project

- **Git:** `git clone https://github.com/its-devamir/loshare.git` then `cd loshare`
- **ZIP:** use GitHub’s green **Code → Download ZIP**, extract the folder

## Run Loshare (any port)

The built-in PHP server must listen on **`0.0.0.0`** so other devices on the network can connect. The **port number is your choice** as long as nothing else is using it (examples: `3000`, `8080`, `9000`).

End the command with **`index.php`** so PHP uses it as the **router**; otherwise paths like `/pc/…` and `/phone/…` may 404.

```bash
cd /path/to/loshare
php -S 0.0.0.0:3000 index.php
```

Then:

- On **this PC:** open `http://127.0.0.1:3000/` (or `http://localhost:3000/`).
- On **phone/tablet:** open `http://<THIS-PC-LAN-IP>:3000/` — same port, replace the IP (the Loshare page lists likely URLs and a QR code).

### Windows helpers (recommended)

`start-loshare.bat` and `start-loshare.ps1` start PHP with **large upload limits** (see below) and open the browser.

- Default port **8080:** double-click `start-loshare.bat` or run `.\start-loshare.ps1`
- Another port: `start-loshare.bat 3000` or `.\start-loshare.ps1 3000`

## Folder layout

Everything lives under **`uploads/`**:

| Path | Role |
|------|------|
| **`uploads/pc/`** | You copy files here on the PC → they appear as **PC → phone** in the app |
| **`uploads/phone/`** | Files uploaded from the browser (e.g. phone) |

There is no separate top-level `files/` folder anymore.

## Network: Wi‑Fi, router, and hotspot

Devices only need to be on the **same IP network** (they must be able to ping each other’s LAN addresses). That includes:

- **Same Wi‑Fi** as usual, or
- **Phone mobile hotspot:** turn on hotspot on the phone, connect the **PC** to that hotspot, run Loshare on the **PC**, then open the **PC’s** hotspot IP from the phone (often something like `192.168.43.x` on Android—use the addresses Loshare suggests).

They do **not** have to use the same physical router; a hotspot **is** enough as long as both sides are on that hotspot’s LAN.

Corporate or guest Wi‑Fi sometimes blocks **client isolation** (devices can’t talk to each other). If the phone can’t load the page, try another network or a hotspot.

## Find this PC’s IPv4 address

You need the computer’s **IPv4 address on the network you’re using**, not the router admin page.

### Windows

1. Open **Command Prompt** or **PowerShell**
2. Run: `ipconfig`
3. Under your active **Wi‑Fi** or **Ethernet adapter**, find **IPv4 Address** (e.g. `192.168.1.42`)

**Default gateway** is usually your router (e.g. `192.168.1.1`). For Loshare you use the **PC’s** IPv4, not the gateway—unless you’re following some special setup.

### macOS

- **System Settings → Network** → select Wi‑Fi/Ethernet → IP address  
- Or Terminal: `ipconfig getifaddr en0` (interface name may vary)

### Linux

```bash
hostname -I
# or
ip -4 addr show scope global
```

The Loshare UI tries to list likely URLs automatically; if it’s empty, use the steps above.

## Firewall

Allow **inbound TCP** on the port you chose (e.g. 3000) for **PHP** or **php.exe** in Windows Defender Firewall the first time you run it, or add a rule manually.

## PHP upload size (“no limit”)

PHP applies **`upload_max_filesize`** and **`post_max_size`** *before* your script runs, so Loshare cannot remove the cap from inside `index.php`. Practical approach:

1. **Use the included scripts** — they pass very large values (8G-class) via `-d`, which is enough for almost all local transfers.
2. **Or** run manually, for example:

   ```bash
   php -d upload_max_filesize=8192M -d post_max_size=8192M -d max_file_uploads=200 -d max_execution_time=0 -S 0.0.0.0:3000 index.php
   ```

3. **Or** edit `php.ini` (location from `php --ini`) and raise those directives, then run `php -S` as usual.

The real ceiling is **free disk space** and **RAM**; PHP still has to buffer multipart data.

## Security

Loshare is for **trusted networks** (home, lab, your hotspot). While it is running, **anyone who can reach your IP and port** can list, upload, and delete files in `uploads/pc` and `uploads/phone`. Do not expose it to the public internet without authentication and hardening.

## Troubleshooting

| Problem | What to check |
|--------|------------------|
| Phone won’t connect | Same network; correct **PC** IPv4 and **port**; firewall; no guest isolation |
| Upload fails / “too large” | Use `start-loshare` or the long `php -d ...` command above |
| 404 on `/pc/...` or `/phone/...` | Start PHP from the folder that contains **`index.php`** |
| Empty IP list in the app | Run `ipconfig` / `hostname -I` manually; some hosts disable `shell_exec` |

## Development

```bash
php -l index.php
```

## License

[MIT](LICENSE)
