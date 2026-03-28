# Loshare

**One PHP file.** Send files between your phone and PC on the same Wi‑Fi—especially handy for **iOS**, where dragging files to the desktop is awkward.

<!-- After you create the repo, set its-devamir/loshare below (or remove this line). -->
[![CI](https://github.com/its-devamir/loshare/actions/workflows/ci.yml/badge.svg)](https://github.com/its-devamir/loshare/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)

## Features

- **Upload** from any device browser (drag-and-drop or file picker; works in **Safari on iPhone**).
- **Download** to any device: two buckets—`files/` (you drop things in on the PC) and `uploads/` (from the browser).
- **QR code** + **copy link** so you can open the app on your phone in one step.
- **Progress** and speed while uploading; **delete** from the UI.
- **No install** beyond PHP; no cloud account; data stays on your LAN.

## Quick start

1. Clone or download this repo.
2. In the project folder, run:

   ```bash
   php -S 0.0.0.0:8080
   ```

3. On your PC open [http://127.0.0.1:8080](http://127.0.0.1:8080).
4. On your phone (same Wi‑Fi), open `http://<your-pc-lan-ip>:8080` (the UI shows the exact URL and a QR code).

**Windows:** double-click `start-loshare.bat` or run `.\start-loshare.ps1` if PHP is on your `PATH`.

### Requirements

- **PHP 8.0+** with the `fileinfo` extension (common in default installs).
- Firewall must allow inbound TCP on the port you choose (e.g. `8080`).

### Putting files on the phone from the PC

Copy files into the `files/` folder next to `index.php`, click **Refresh** (or wait), then **Download** on the phone.

## Security

Loshare is built for **trusted home or office LANs**. While it is running, **anyone who can reach your IP and port** can list, upload, and delete files in `files/` and `uploads/`. Do not expose it to the public internet without adding authentication and hardening.

## PHP upload limits

Large uploads may fail until you raise limits in `php.ini` (e.g. `upload_max_filesize`, `post_max_size`). The web UI shows your current PHP limits at the bottom of the “Open on another device” card.

## Troubleshooting

| Issue | What to try |
|--------|-------------|
| Phone cannot connect | Same Wi‑Fi as PC; use LAN IP, not `127.0.0.1`; allow the port in Windows Firewall / router isolation off. |
| Upload fails | Check PHP size limits; try a smaller file first. |
| 404 on `/files/...` | Start the server from the **directory that contains** `index.php` (see command above). |

## Project layout

```
loshare/
├── index.php          # App + API + UI (single entrypoint)
├── files/             # Put files here → download on phone
├── uploads/           # Browser uploads land here
├── start-loshare.bat  # Windows helper
└── start-loshare.ps1
```

## Contributing

Issues and PRs welcome. Keep changes focused; `index.php` is intentionally self-contained.

## License

[MIT](LICENSE)

---

**Optional:** Replace `its-devamir/loshare` in the CI badge URL with your GitHub username and repository name. Add a screenshot and embed it under the title for more stars.
