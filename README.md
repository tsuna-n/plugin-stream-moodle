# Moodle Live Stream (mod_livestream)

A Moodle activity module that lets teachers broadcast live lessons to students, two ways:

- **OBS / media server** — the teacher streams from OBS Studio to your self-hosted media server (MediaMTX, included as a docker-compose setup). Students watch an embedded HLS player right inside Moodle, with automatic LIVE detection.
- **Zoom** — the plugin creates a Zoom meeting automatically through the Zoom API (Server-to-Server OAuth). The teacher gets a *Start meeting* button, students get a *Join* button.

Requires **Moodle 4.4+**.

## Repository layout

| Path | What it is |
|---|---|
| `livestream/` | The Moodle plugin — install into `moodle/mod/livestream` |
| `media-server/` | Docker-compose for MediaMTX (RTMP in → HLS out), needed for OBS mode |

## 1. Install the plugin

```bash
cp -r livestream /path/to/moodle/mod/livestream
```

Then visit **Site administration → Notifications** to run the installation, or:

```bash
php admin/cli/upgrade.php
```

## 2. Set up the media server (OBS mode)

On any host with Docker (can be the Moodle server itself):

```bash
cd media-server
docker compose up -d
```

This exposes:

- `rtmp://<server>:1935` — where OBS publishes
- `http://<server>:8888` — where the player reads HLS

**Production notes**

- Put HLS behind HTTPS (students' browsers will block mixed content on an HTTPS Moodle). The simplest option is a reverse proxy (Caddy/nginx) in front of port 8888.
- Open ports 1935 (teachers only, if you can restrict) and 443/8888 (students).
- The stream key is the only thing protecting the publish path — always use HTTPS/RTMPS where possible and keep keys private.

Then in Moodle: **Site administration → Plugins → Activity modules → Live stream**

| Setting | Example |
|---|---|
| RTMP server URL | `rtmp://media.example.com:1935` |
| HLS base URL | `https://media.example.com:8888` |

## 3. Set up Zoom (Zoom mode)

1. Go to [marketplace.zoom.us](https://marketplace.zoom.us) → **Develop → Build App → Server-to-Server OAuth**.
2. Add the scope `meeting:write:meeting` (and `meeting:write:meeting:admin` for account-level apps).
3. Activate the app and copy the **Account ID**, **Client ID**, **Client Secret** into the plugin settings.

Meetings are created under the account of the OAuth app (`users/me`). Waiting room is enabled and join-before-host disabled by default.

## 4. Using it

**Teacher**

1. In a course, *Add an activity* → **Live stream**, pick the type, optionally schedule a start time (it lands in the course calendar).
2. *OBS mode*: open the activity — a teacher-only box shows the **Server URL** and **Stream key**. In OBS: Settings → Stream → Custom, paste both, then *Start Streaming*.
3. *Zoom mode*: the meeting is created on save; open the activity and press **Start meeting**.
4. After class, optionally paste a **Recording URL** into the activity settings so students get a *Watch recording* button.

**Student**

Opens the activity. In OBS mode the page shows *Offline* until the teacher goes live, then the player starts automatically (status is polled every 10 s). In Zoom mode they press *Join Zoom meeting*.

A **Live streams** item also appears in the course navigation (under *More* in the Boost theme) once the course has at least one stream.

## คู่มือย่อ (ภาษาไทย)

1. ติดตั้งปลั๊กอิน: คัดลอกโฟลเดอร์ `livestream` ไปไว้ที่ `moodle/mod/livestream` แล้วเข้า **Site administration → Notifications**
2. โหมด OBS: รัน media server ด้วย `docker compose up -d` ในโฟลเดอร์ `media-server` แล้วตั้งค่า RTMP/HLS URL ในหน้าตั้งค่าปลั๊กอิน
3. ครูสร้างกิจกรรม "ถ่ายทอดสด" ในรายวิชา → เปิดกิจกรรมจะเห็น Server URL และ Stream key → นำไปใส่ใน OBS (Settings → Stream → Custom) → กด Start Streaming
4. นักเรียนเปิดกิจกรรม วิดีโอจะเล่นอัตโนมัติเมื่อครูเริ่มสตรีม
5. โหมด Zoom: ผู้ดูแลระบบใส่ Account ID / Client ID / Client Secret ของแอป Server-to-Server OAuth ในหน้าตั้งค่าปลั๊กอิน — ระบบจะสร้างห้องประชุมให้อัตโนมัติเมื่อครูบันทึกกิจกรรม

## Notes & limitations

- Live status is detected by probing the HLS manifest server-side every 10 seconds — expect a few seconds' delay before the LIVE badge appears.
- End-to-end latency in OBS mode is roughly 3–8 s with the low-latency HLS settings shipped in `mediamtx.yml`.
- The player loads `hls.js` from the jsDelivr CDN by default; point the *hls.js URL* setting at a self-hosted copy for offline networks.
- Restoring a course backup generates a **new stream key** and does **not** recreate Zoom meetings (secrets are never backed up) — a teacher just re-saves the activity.
- Zoom attendance reports are not pulled yet; that is a natural next feature.

## License

GPL v3 or later, same as Moodle.


###สรุปทุกขั้นตอนที่ทำ

1. ตรวจโครงสร้าง plugin — mod/livestream มีไฟล์ครบ (version.php, lib.php, db/install.xml, lang, mod_form.php ฯลฯ)
2. ลองรัน upgrade ครั้งแรก → ไม่เจอ plugin — เพราะตั้งแต่ Moodle 5.1 เป็นต้นมา web root ย้ายไปอยู่ใต้ public/ ทำให้ $CFG->dirroot ชี้ไปที่ /var/www/moodle/public โฟลเดอร์ mod/ ที่ระดับบนสุดจึงไม่ถูกสแกนเลย (สังเกตได้จาก git status ที่ขึ้น ?? mod/ ทั้งโฟลเดอร์ — module มาตรฐานทั้งหมดอยู่ที่ public/mod/)
3. ย้ายไปที่ถูกต้อง
mv mod/livestream public/mod/livestream
3. แล้วลบโฟลเดอร์ mod/ เปล่าทิ้ง
4. รัน database upgrade ใน container moodle-web ด้วย user www-data (กันปัญหาสิทธิ์ไฟล์ cache):
docker exec -u www-data moodle-web php /var/www/moodle/admin/cli/upgrade.php --non-interactive --allow-unstable
4. ต้องใส่ --allow-unstable เพราะ site เป็น 5.3dev — ผลคือ mod_livestream ++ Success พร้อมสร้าง settiบด้วย admin ที่ Site
