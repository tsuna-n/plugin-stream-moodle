# Moodle Live Stream (mod_livestream)

A Moodle activity module that lets teachers broadcast live lessons to students, two ways:

- **OBS / media server** — the teacher streams from OBS Studio to your self-hosted media server (MediaMTX, included as a docker-compose setup). Students watch an embedded HLS player right inside Moodle, with automatic LIVE detection.
- **Zoom** — the plugin creates a Zoom meeting automatically through the Zoom API (Server-to-Server OAuth), using each teacher's own Zoom account — different teachers may hold entirely separate Zoom accounts/organisations, so there is no single site-wide Zoom credential. When the media server is configured, Zoom's *custom live streaming* relays the meeting into the **same embedded player**, so students watch inside Moodle without opening Zoom; otherwise they get a plain *Join* button.

Every stream (OBS or Zoom-relayed) is **recorded to disk**, and once it ends a *Watch recording* button appears automatically.

Beyond watching, the embedded-player modes (OBS and Zoom-relayed) also get:

- A **live badge** on the course navigation, so students browsing elsewhere in the course can see at a glance that a class just went live.
- **Live chat** — a lightweight, plain-text panel next to the player so students can ask questions during class. It is intentionally ephemeral: nothing is kept once the broadcast ends (the recording is the lasting record).
- **Attendance / roll call** — the plugin records which students were actually watching *while the stream was live* (not just whether they opened the page), grouped per broadcast session, with a CSV export for the register.

Requires **Moodle 4.4+**.

## Repository layout

| Path | What it is |
|---|---|
| `livestream/` | The Moodle plugin — install into `mod/livestream` (`public/mod/livestream` on Moodle 5.1+) |
| `media-server/` | Docker-compose for MediaMTX (RTMP in → HLS out), needed for OBS mode |

## 1. Install the plugin

Copy the plugin into your Moodle's activity module directory. On Moodle 5.1
and later the web root moved under `public/`, so the plugin lives in
`public/mod/`; on 4.4–5.0 it is `mod/`.

```bash
# Moodle 5.1+
cp -r livestream /path/to/moodle/public/mod/livestream
# Moodle 4.4–5.0
cp -r livestream /path/to/moodle/mod/livestream
```

Then visit **Site administration → Notifications** to run the installation, or:

```bash
php admin/cli/upgrade.php
```

## 2. Set up the media server (OBS mode)

The `media-server` docker-compose ships MediaMTX **and** a [Caddy](https://caddyserver.com)
reverse proxy that gets you real HTTPS with a free auto-renewing Let's
Encrypt certificate, with no manual certbot steps. Only Caddy (80/443) and
RTMP (1935) are reachable from outside the container network — HLS (8888)
and the playback API (9996) are internal-only, reached exclusively through
Caddy over HTTPS.

You need **two** subdomains, each with an A/AAAA record already pointing at
the server's public IP before you start the stack (Caddy requests a
certificate for each on first boot) — e.g. `live.media.example.com` and
`vod.media.example.com`. They can't be one domain with two paths: MediaMTX's
HLS server issues an absolute-path redirect on the very first request that a
shared-domain path split breaks (see the comment in `Caddyfile` if curious).

```bash
cd media-server
cp .env.example .env
# edit .env: set HLS_DOMAIN, PLAYBACK_DOMAIN to your real subdomains, ACME_EMAIL to a real address
docker compose up -d
```

This exposes:

- `rtmp://<any-domain-pointing-here>:1935` — where OBS (or Zoom) publishes (RTMP doesn't go through Caddy, so any hostname/IP that resolves to the server works)
- `https://<HLS_DOMAIN>` — where the player reads HLS
- `https://<PLAYBACK_DOMAIN>` — where finished recordings are served

Recordings are written to `media-server/recordings/<streamkey>/` on the host
(a bind mount) and kept indefinitely; set `recordDeleteAfter` in `mediamtx.yml`
to auto-prune old lessons.

Firewall / cloud security group: open **80/tcp**, **443/tcp** (+443/udp for
HTTP/3, optional), and **1935/tcp**. Nothing else needs to be reachable from
the internet. The stream key is the only thing protecting the publish and
playback paths, which is exactly why everything runs over HTTPS here —
restrict 1935 to known teacher/Zoom-cloud IPs at the firewall too if your
setup allows it.

Then in Moodle: **Site administration → Plugins → Activity modules → Live stream**

| Setting | Example |
|---|---|
| RTMP server URL | `rtmp://media.example.com:1935` |
| HLS base URL | `https://live.media.example.com` |
| Recording playback URL | `https://vod.media.example.com` |

The HLS and playback URLs must be reachable **both** from students' browsers and
from the Moodle server itself — the LIVE check and recording lookup run
server-side. The plugin bypasses Moodle's SSRF port allowlist for these
admin-configured URLs, so this works even if Moodle and the media server sit
on different hosts/ports.

### Running without Caddy (local dev / your own proxy)

For local testing (as in the dev instance driving this doc) you don't need
Caddy at all — run MediaMTX alone and use plain HTTP, or front it with your
own existing reverse proxy instead. Comment out or remove the `caddy` service
from `docker-compose.yml`, publish `8888` and `9996` from the `mediamtx`
service directly (see git history for the previous, Caddy-less compose file),
and point the plugin settings at those ports (or your own proxy) instead of
`HLS_DOMAIN`/`PLAYBACK_DOMAIN`. If you use your own proxy instead of Caddy,
keep HLS and playback on separate hostnames (or otherwise proxied at the
root, not a stripped path prefix) for the same reason noted above.

## 3. Set up Zoom (Zoom mode)

Zoom has no site-wide setting — each **teacher** connects their own Zoom account, since different teachers may hold entirely separate Zoom accounts/organisations. There is nothing for the site administrator to configure here.

Each teacher who wants to use Zoom mode:

1. Goes to [marketplace.zoom.us](https://marketplace.zoom.us) → **Develop → Build App → Server-to-Server OAuth** (on their own Zoom account).
2. Adds the scope `meeting:write:meeting` (and `meeting:write:meeting:admin` for account-level apps). For the embedded relay below, also add the live-streaming write scope (`meeting:update:meeting` / `meeting:write:admin` covers the `/livestream` endpoint).
3. Activates the app, then in any Moodle course goes to **Live streams → Manage my Zoom account** (`mod/livestream/zoomaccount.php`) and pastes in the **Account ID**, **Client ID**, **Client Secret**.

Meetings are created under that teacher's own account (`users/me` of their app). Waiting room is enabled and join-before-host disabled by default. A teacher must connect their Zoom account before they can save a Zoom-type activity; the form shows an error with a link to the page above otherwise.

### Zoom into the embedded player (optional)

When the media server (above) is configured, the plugin points each meeting's
**custom live stream** at your RTMP server, so Zoom relays the meeting to the
same embedded HLS player OBS mode uses — students never leave Moodle.

For this to work the Zoom account must have **Settings → In Meeting (Advanced) →
Allow live streaming of meetings → Custom Live Streaming Service** enabled. If it
is off (or the OAuth app lacks the scope), the activity still saves and falls
back to the plain *Join Zoom* button; the plugin shows a warning explaining why.

Latency of a Zoom-relayed stream is higher than OBS direct (Zoom adds ~20–30 s).

## 4. Using it

**Teacher**

1. In a course, *Add an activity* → **Live stream**, pick the type, optionally schedule a start time (it lands in the course calendar).
2. *OBS mode*: open the activity — a teacher-only box shows the **Server URL** and **Stream key**. In OBS: Settings → Stream → Custom, paste both, then *Start Streaming*.
3. *Zoom mode*: first connect a Zoom account once via **Manage my Zoom account** (course navigation) if you haven't already — the meeting is then created on save, under your own Zoom account. Open the activity and press **Start meeting**. With the media server configured, then in the Zoom client choose **More → Live on Custom Live Streaming Service** to push the meeting into the embedded player; otherwise students just join Zoom directly.
4. After class, a **Watch recording** button appears automatically once the recording is available (needs the *Recording playback URL* setting). You can still paste a manual **Recording URL** on the activity to override it.

**Student**

Opens the activity. When there is an embedded player (OBS mode, or Zoom relayed to the media server) the page shows *Offline* until the teacher goes live, then the player starts automatically (status is polled every 10 s); after class it offers the recording. In plain Zoom mode they press *Join Zoom meeting*.

A **Live streams** item also appears in the course navigation (under *More* in the Boost theme) once the course has at least one stream, and shows a **Live now** badge whenever any stream in the course is actually broadcasting.

## 5. Attendance & live chat

Both features only apply where the plugin controls the viewing experience — OBS mode, or Zoom relayed into the embedded player. Plain Zoom mode already has Zoom's own chat, and there is no way to track attendance once a student leaves Moodle to join Zoom directly (pulling *Zoom's own* attendance report via its Reports API is a possible future addition, see Notes below).

**Live chat** appears automatically next to the player for anyone with the *Participate in the live chat* capability (students and teachers by default; guests are excluded, since posting needs a real identity). Teachers can delete any message. Nothing is stored beyond the current broadcast — the moment a stream goes offline, its chat is cleared.

**Attendance** needs no setup: every activity with an embedded player automatically records a *session* each time it goes live, and — for every logged-in, non-guest viewer who isn't a teacher — the times they were first and last seen watching. Teachers see it via the **Attendance** link on the activity page (visible next to the player), which lists past sessions and, per session, a table of username / full name / first seen / last seen / duration, with a **Download CSV** link for the register.

## คู่มือย่อ (ภาษาไทย)

> 📖 คู่มือการใช้งานฉบับเต็ม (พร้อมตารางตั้งค่าและวิธีแก้ปัญหา): [docs/usage-th.md](docs/usage-th.md)

1. ติดตั้งปลั๊กอิน: คัดลอกโฟลเดอร์ `livestream` ไปไว้ที่ `mod/livestream` (บน Moodle 5.1+ คือ `public/mod/livestream`) แล้วเข้า **Site administration → Notifications**
2. โหมด OBS: รัน media server ด้วย `docker compose up -d` ในโฟลเดอร์ `media-server` แล้วตั้งค่า RTMP/HLS URL ในหน้าตั้งค่าปลั๊กอิน
3. ครูสร้างกิจกรรม "ถ่ายทอดสด" ในรายวิชา → เปิดกิจกรรมจะเห็น Server URL และ Stream key → นำไปใส่ใน OBS (Settings → Stream → Custom) → กด Start Streaming
4. นักเรียนเปิดกิจกรรม วิดีโอจะเล่นอัตโนมัติเมื่อครูเริ่มสตรีม และหลังสอนจบจะมีปุ่ม "ดูย้อนหลัง" ให้อัตโนมัติ (ตั้งค่า "URL สำหรับดูย้อนหลัง" = โดเมนย่อยของ playback เช่น `https://vod.media.example.com` ผ่าน Caddy) ระหว่างเรียนนักเรียนพิมพ์คุยกันได้ในกล่อง "แชทสด" ใต้เครื่องเล่น (แชทจะถูกล้างทิ้งหลังจบคาบ)
5. โหมด Zoom: **ครูแต่ละคน** (ไม่ใช่ผู้ดูแลระบบ) ไปที่ "จัดการบัญชี Zoom ของฉัน" ในเมนูนำทางของรายวิชา แล้วใส่ Account ID / Client ID / Client Secret ของแอป Server-to-Server OAuth ของตัวเอง เพราะครูแต่ละคนอาจมีบัญชี Zoom คนละบัญชีกัน — ระบบจะสร้างห้องประชุมให้อัตโนมัติเมื่อครูบันทึกกิจกรรม หากตั้งค่า media server ไว้ด้วย Zoom จะส่งภาพเข้าเครื่องเล่นที่ฝังในหน้ากิจกรรม (นักเรียนไม่ต้องเปิด Zoom) โดยครูเลือก **More → Live on Custom Live Streaming Service** ในโปรแกรม Zoom เพื่อออกอากาศ
6. ครูดูรายชื่อนักเรียนที่เข้าฟัง (เช็กชื่อ) ได้ที่ลิงก์ "Attendance" ในหน้ากิจกรรม ระบบนับเฉพาะช่วงที่นักเรียนดูตอนถ่ายทอดสดจริงเท่านั้น แยกตามรอบการสอน และดาวน์โหลดเป็น CSV ได้

## Notes & limitations

- Live status is detected by probing the HLS manifest server-side every 10 seconds — expect a few seconds' delay before the LIVE badge appears.
- End-to-end latency in OBS mode is roughly 3–8 s with the low-latency HLS settings shipped in `mediamtx.yml`.
- Recordings are fragmented-MP4 written by MediaMTX under `media-server/recordings/`; the *Watch recording* link streams the latest session from the playback server as a normal MP4. Files are owned by the container user (root) on the bind mount.
- The player loads `hls.js` from the jsDelivr CDN by default; point the *hls.js URL* setting at a self-hosted copy for offline networks.
- Restoring a course backup generates a **new stream key** and does **not** recreate Zoom meetings (secrets are never backed up) — a teacher just re-saves the activity.
- Attendance and chat are tracked by the plugin's own player, so they only work for OBS mode and Zoom-relayed-to-embedded-player — not plain Zoom (join-through-Zoom) activities. Pulling attendance from *Zoom's own* Reports API for that case is a natural next feature.
- A broadcast session that nobody's browser notices go offline (every viewer closes their tab first) is closed by a safety-net scheduled task within 5 minutes, rather than instantly.
- Chat messages are plain text only (no links/formatting) by design, and are never kept past the end of the broadcast they belong to.

## License

GPL v3 or later, same as Moodle.
