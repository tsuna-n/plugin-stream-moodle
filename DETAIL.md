# DETAIL.md — คำอธิบายโค้ดทั้งโปรเจกต์ mod_livestream

เอกสารนี้อธิบาย **ทุกไฟล์** ในโปรเจกต์นี้ว่าทำหน้าที่อะไร และ **โค้ดทำงานอย่างไร** อย่างละเอียด
เขียนขึ้นจากการอ่านซอร์สโค้ดจริงทั้งหมด (ไม่ใช่การเดา) เพื่อให้คนที่มาอ่านทีหลังเข้าใจภาพรวมและ
รายละเอียดของระบบได้โดยไม่ต้องไล่โค้ดเองใหม่ทั้งหมด

> ดู `CLAUDE.md` คู่กันด้วย — เป็นสเปคของฟีเจอร์ที่ **กำลังจะสร้างต่อ** (realtime push ผ่าน SSE)
> ส่วน DETAIL.md ฉบับนี้อธิบาย **โค้ดที่มีอยู่จริงตอนนี้** ในสาขา `fix-detail-artitecture`

---

## 1. ภาพรวมโปรเจกต์คืออะไร

`mod_livestream` คือ **Activity module ปลั๊กอินของ Moodle** (LMS) ที่ให้ครูถ่ายทอดสดบทเรียนให้
นักเรียนดูได้ 2 วิธี:

1. **OBS / self-hosted media server** — ครูสตรีมจากโปรแกรม OBS Studio ด้วยโปรโตคอล RTMP ไปยัง
   media server ที่ปลั๊กอินเตรียม docker-compose ให้ (MediaMTX) แล้ว media server แปลงเป็น HLS
   ให้เบราว์เซอร์นักเรียนเล่นผ่าน `<video>` element ที่ฝังอยู่ในหน้ากิจกรรมโดยตรง
2. **Zoom** — ปลั๊กอินสร้างห้องประชุม Zoom ให้อัตโนมัติผ่าน Zoom REST API (Server-to-Server OAuth)
   ภายใต้บัญชี Zoom ส่วนตัวของครูแต่ละคน (ไม่ใช่บัญชีกลางของเว็บไซต์ เพราะครูแต่ละคนอาจมีองค์กร
   Zoom แยกกัน) ถ้ามีการตั้งค่า media server ไว้ด้วย ปลั๊กอินจะสั่งให้ Zoom "custom live stream"
   ยิงภาพเข้า RTMP เดียวกับโหมด OBS ทำให้นักเรียนดูผ่านเครื่องเล่นที่ฝังในหน้า Moodle ได้เลยโดยไม่
   ต้องออกไปเปิด Zoom (ถ้าไม่ได้ตั้งค่า media server นักเรียนจะได้แค่ปุ่ม "Join Zoom meeting")

ทุกสตรีม (ทั้ง OBS และ Zoom ที่ relay เข้า media server) จะถูก **อัดวิดีโอเก็บไว้อัตโนมัติ** และมี
ปุ่ม "ดูย้อนหลัง" ปรากฏขึ้นเองหลังจบคาบเรียน

ฟีเจอร์เสริมของโหมดที่มีเครื่องเล่นฝัง (OBS / Zoom-relayed):
- **ป้าย "กำลังถ่ายทอดสด" (Live now badge)** บนเมนูนำทางของรายวิชา
- **แชทสด** แบบข้อความล้วน อยู่ได้แค่ช่วงถ่ายทอดสด (ลบทิ้งทันทีที่จบคาบ)
- **เช็กชื่อ/attendance** บันทึกว่านักเรียนคนไหนดูอยู่จริงช่วงไหนของการถ่ายทอดสด พร้อมส่งออก CSV

รองรับ **Moodle 4.4 ขึ้นไป** (`$plugin->requires = 2024042200`)

### สถาปัตยกรรมระดับสูง

```
┌─────────────────────────┐        RTMP :1935        ┌──────────────────────┐
│  OBS Studio  หรือ  Zoom  │ ────────────────────────▶│      MediaMTX        │
│  (ครูสตรีมจากที่นี่)      │                            │  (media-server/)     │
└─────────────────────────┘                            │  RTMP→HLS + อัดวิดีโอ │
                                                          └──────────┬───────────┘
                                                                     │ HLS :8888 (ผ่าน Caddy TLS)
                                                                     │ Playback :9996 (ผ่าน Caddy TLS)
                                                                     ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                              Moodle (PHP)                                  │
│  livestream/view.php → render เครื่องเล่น + poll สถานะทุก 10s (server-side  │
│  curl ไปเช็ค HLS manifest ที่ MediaMTX) → ปรับ DB attendance/session ไปด้วย │
│  livestream/attendance.php → รายงานเช็กชื่อ                                │
│  livestream/zoomaccount.php → ครูผูกบัญชี Zoom ส่วนตัว                     │
│  classes/local/zoom_client.php → เรียก Zoom REST API สร้าง/แก้/ลบ meeting   │
└───────────────────────────────────────────────────────────────────────────┘
```

โปรเจกต์แบ่งเป็น 2 ส่วนหลักตามโฟลเดอร์บนสุด:

| โฟลเดอร์ | คืออะไร |
|---|---|
| `livestream/` | ตัวปลั๊กอิน Moodle เอง — เอาไปวางที่ `mod/livestream` (หรือ `public/mod/livestream` บน Moodle 5.1+) |
| `media-server/` | docker-compose ของ MediaMTX (RTMP→HLS) + Caddy (reverse proxy ทำ HTTPS อัตโนมัติ) |
| `docs/`, `README.md` | คู่มือการติดตั้ง/ใช้งาน |
| `CLAUDE.md` | สเปคของฟีเจอร์ "full streaming" (realtime push ผ่าน SSE) ที่ **กำลังจะสร้างต่อ** — ดูหัวข้อ 9 |

### สถานะการพัฒนาปัจจุบัน (สำคัญ)

ตอนนี้ระบบทั้งหมด (player/chat/course badge) เป็น **polling ล้วน 100%** — ยังไม่มี realtime push
(SSE) ใช้งานจริงในเบราว์เซอร์เลย ถึงแม้ `CLAUDE.md` จะบรรยายแผนงานทั้งหมดของฟีเจอร์นั้นไว้แล้วก็ตาม
จากการตรวจ `git status` พบว่ามีแค่ **Phase 0 (Groundwork)** ของแผนงานนั้นที่ทำเสร็จและยังไม่ได้
commit:

- `livestream/settings.php` เพิ่มช่อง `realtimeurl` / `realtimesecret` (M)
- `livestream/lang/en/livestream.php`, `lang/th/livestream.php` เพิ่ม string ที่เกี่ยวข้อง (M)
- `livestream/classes/local/realtime.php` (ใหม่, ยัง untracked) — helper เซ็น/ตรวจ token และ publish
  event แบบ best-effort ไปยัง gateway (ที่ยังไม่มีอยู่จริง)
- `livestream/tests/local/realtime_test.php` (ใหม่, ยัง untracked) — unit test ของ helper ด้านบน

**ยังไม่มี**: โฟลเดอร์ `realtime/` (ตัว Node gateway เอง), endpoint `streamevent.php` /
`presence.php` / `resolve_course.php`, external class `get_realtime_token`, การแก้ไข AMD JS
(`player.js`/`chat.js`/`navbadge.js`) ให้เปิด `EventSource`, และการเพิ่ม service `realtime` เข้า
`media-server/docker-compose.yml`/`Caddyfile` เอกสารฉบับนี้จึงอธิบาย **โค้ดที่ทำงานได้จริงตอนนี้**
เป็นหลัก (polling ทุกที่) และจะเล่าแผนงาน realtime แยกไว้ในหัวข้อ 9 เพื่อไม่ให้สับสนกับของจริง

---

## 2. ผังโครงสร้างไฟล์ทั้งหมด

```
livestream/
├── amd/
│   ├── src/                     # ซอร์สจริงของ JavaScript (ES5, AMD module)
│   │   ├── chat.js
│   │   ├── navbadge.js
│   │   └── player.js
│   └── build/                   # ไฟล์ .min.js + .map ที่ compile แล้ว (Moodle ใช้ตัวนี้จริง)
├── backup/moodle2/               # ตรรกะ backup/restore
├── classes/
│   ├── event/course_module_viewed.php
│   ├── external/                 # Web service (AJAX) ทุกตัว
│   ├── form/zoom_account_form.php
│   ├── local/                    # business logic (attendance, zoom, mediamtx, realtime)
│   ├── privacy/provider.php
│   └── task/close_stale_sessions.php
├── db/                            # access.php, install.xml, services.php, tasks.php, upgrade.php
├── lang/en/, lang/th/            # ข้อความในระบบ (ต้องมีคู่กันเสมอ)
├── pix/monologo.svg
├── templates/view.mustache        # Mustache template หน้ากิจกรรม
├── tests/local/realtime_test.php
├── attendance.php                 # หน้ารายงานเช็กชื่อ
├── index.php                      # รายการกิจกรรมถ่ายทอดสดในรายวิชา
├── lib.php                        # callback มาตรฐานของ Moodle module (add/update/delete instance ฯลฯ)
├── mod_form.php                   # ฟอร์มสร้าง/แก้ไขกิจกรรม
├── settings.php                   # การตั้งค่าระดับเว็บไซต์
├── styles.css
├── version.php
├── view.php                       # หน้าแสดงผลกิจกรรมหลัก
└── zoomaccount.php                # หน้าครูผูกบัญชี Zoom ส่วนตัว

media-server/
├── docker-compose.yml             # mediamtx + caddy
├── Caddyfile                      # reverse proxy ทำ HTTPS อัตโนมัติ (2 โดเมนย่อย)
├── mediamtx.yml                   # ตั้งค่า MediaMTX (RTMP ingest, HLS output, playback, record)
├── .env.example
└── recordings/                    # ไฟล์วิดีโอที่อัดไว้ (bind mount)
```

---

## 3. ไฟล์รากของปลั๊กอิน (`livestream/*.php` ระดับบนสุด)

### 3.1 `version.php`
ประกาศเวอร์ชันปลั๊กอินให้ Moodle รู้จัก: `$plugin->component`, `$plugin->version` (เลขวันที่ใช้
เทียบว่าต้อง upgrade หรือยัง), `$plugin->requires` (Moodle ขั้นต่ำ), `$plugin->maturity`,
`$plugin->release` ปัจจุบันคือ `2026071701` / release `1.3.0` — **ทุกครั้งที่แก้ DB schema,
services, capabilities หรือ lang ต้องปรับเลขนี้ขึ้น** ไม่งั้น Moodle จะไม่รันการเปลี่ยนแปลงใหม่

### 3.2 `lib.php`
ไฟล์ callback มาตรฐานที่ Moodle เรียกเองตามชื่อฟังก์ชัน (module lifecycle hooks) มีฟังก์ชันหลักๆ:

- **`livestream_supports($feature)`** — บอก Moodle ว่าโมดูลนี้รองรับฟีเจอร์ core อะไรบ้าง เช่น
  `FEATURE_MOD_INTRO` (มีช่องคำอธิบาย), `FEATURE_COMPLETION_TRACKS_VIEWS` (นับความสำเร็จจากการ
  เปิดดู), `FEATURE_MOD_PURPOSE` คืน `MOD_PURPOSE_COMMUNICATION` (จัดหมวดเป็นเครื่องมือสื่อสาร)
- **`livestream_add_instance($data, $mform)`** — เรียกตอนครูกด "บันทึก" สร้างกิจกรรมใหม่ ทำ 3 อย่าง:
  1. สุ่ม `streamkey` ยาว 32 ตัวอักษร hex (`bin2hex(random_bytes(16))`) — คีย์ลับที่ใช้เป็น RTMP
     path/HLS path เดียวกับที่ mediamtx ใช้แยกแต่ละสตรีม
  2. ถ้าเลือกโหมด Zoom เรียก `livestream_create_zoom_meeting()` เพื่อสร้าง meeting จริงบน Zoom
  3. insert record ลงตาราง `livestream` แล้วเรียก `livestream_update_calendar()` เพื่อสร้าง event
     ปฏิทินถ้ามี `starttime`
- **`livestream_update_instance($data, $mform)`** — ตอนแก้ไขกิจกรรม โดยคง `streamkey` เดิมไว้เสมอ
  (ไม่สุ่มใหม่) แล้วดูว่ามีการเปลี่ยนโหมดสตรีมหรือไม่:
  - ยังเป็น Zoom เหมือนเดิมและมี `zoommeetingid` อยู่แล้ว → sync ข้อมูล (ชื่อ/เวลา) ไป Zoom
  - เพิ่งเปลี่ยนมาเป็น Zoom หรือยังไม่เคยมี meeting → สร้างใหม่
  - เปลี่ยนออกจาก Zoom ไปโหมดอื่น → ลบ meeting เดิมทิ้งจาก Zoom (best effort) แล้วเคลียร์ field
    Zoom ทั้งหมดในเรคคอร์ด
- **`livestream_delete_instance($id)`** — ลบกิจกรรม: ลบ meeting Zoom ที่ผูกอยู่ (ถ้ามี), ลบ
  `livestream_attendance` ที่อ้างถึง session ของกิจกรรมนี้ทั้งหมด, ลบ `livestream_session`,
  `livestream_chat`, event ปฏิทิน แล้วค่อยลบเรคคอร์ดหลัก
- **`livestream_create_zoom_meeting($data)`** — สร้าง `zoom_client` ผูกกับบัญชี Zoom ของ
  `$USER` ปัจจุบัน (ครูที่กำลังกดบันทึก) เรียก `create_meeting()` แล้วเก็บผลลัพธ์ (meeting id,
  join_url, start_url, owner id) ไว้ในข้อมูลฟอร์ม ถ้า Zoom ตอบ error จะ **ไม่ทำให้บันทึกฟอร์ม
  ล้มเหลว** — แค่เคลียร์ field Zoom เป็นค่าว่างและแจ้งเตือนด้วย `\core\notification::error()` (จุด
  สำคัญ: การบันทึกกิจกรรมต้องไม่มีวันเสียหายเพราะ Zoom ล่ม) จากนั้นเรียก
  `livestream_configure_zoom_livestream()` ต่อเพื่อผูก custom live stream
- **`livestream_sync_zoom_meeting($data)`** — PATCH หัวข้อ/เวลาของ meeting เดิมไป Zoom (best
  effort เช่นกัน แต่ error จะเป็นแค่ warning ไม่ error) แล้วเรียก
  `livestream_configure_zoom_livestream()` ซ้ำเผื่อ media server เพิ่งถูกตั้งค่าใหม่
- **`livestream_configure_zoom_livestream($data)`** — ถ้ามีการตั้งค่า `rtmpserver` +
  `hlsbaseurl` ระดับเว็บไซต์ จะสั่ง Zoom ให้ตั้ง custom live stream (`set_livestream()`) ชี้ไปที่
  `<rtmpserver>/<streamkey>` เดียวกับที่ OBS ใช้ — ทำให้ Zoom "เป็น OBS หนึ่งตัว" ในสายตา MediaMTX
  ถ้าไม่ได้ตั้ง media server จะ no-op เงียบๆ (graceful fallback)
- **`livestream_delete_zoom_meeting($meetingid, $ownerid)`** — ลบ meeting บน Zoom แบบ best
  effort เมื่อลบกิจกรรม (error แค่ log ด้วย `debugging()` ไม่ throw ต่อ เพราะกิจกรรมจะถูกลบอยู่ดี)
- **`livestream_update_calendar($data)`** — สร้าง/แก้ไข/ลบ `calendar_event` ของ Moodle core
  ให้ตรงกับ `starttime` ของกิจกรรม (ลบถ้าไม่มี `starttime` แล้ว)
- **`livestream_extend_navigation_course($navigation, $course, $context)`** — เพิ่มลิงก์ 2 อัน
  ในเมนูนำทางของรายวิชา: "จัดการบัญชี Zoom ของฉัน" (สำหรับผู้มีสิทธิ์ `addinstance` เพื่อให้ครูผูก
  บัญชี Zoom ได้ตั้งแต่ก่อนสร้างกิจกรรมแรก) และ **"Stream"** (ถ้ามีสิทธิ์ `view` และรายวิชานี้มี
  กิจกรรมถ่ายทอดสดอย่างน้อย 1 ตัว) — ลิงก์ไปหน้ารายการสตรีมทั้งหมดของรายวิชา (`index.php`) ให้
  นักเรียนคลิกเลือกเข้าดูสตรีมของครูเองได้ พร้อมสั่งโหลด AMD module `mod_livestream/navbadge` เพื่อแปะป้าย
  "กำลังถ่ายทอดสด" บนลิงก์นั้นแบบ client-side (กันไม่ให้หน้ารายวิชาต้องรอ round-trip ไป media server
  ก่อน render)
- **`livestream_get_coursemodule_info($coursemodule)`** — ให้ข้อมูลชื่อ/คำอธิบายกิจกรรมสำหรับแสดง
  บนหน้ารายวิชา (course page) รองรับ option "show description"

### 3.3 `view.php`
หน้าแสดงผลกิจกรรมหลัก (สิ่งที่ครู/นักเรียนเห็นเมื่อคลิกเข้ากิจกรรม) ลำดับการทำงาน:

1. รับ `id` (course module id) → โหลด `$course`, `$cm`, เรคคอร์ด `livestream`
2. `require_login()` + `require_capability('mod/livestream:view', ...)`
3. บันทึก event `course_module_viewed` และ mark completion (สำหรับ activity completion tracking)
4. คำนวณ flag สำคัญ:
   - `$isobs` / `$iszoom` จาก `streamtype`
   - `$hasmedia` = มีการตั้งค่า `rtmpserver` + `hlsbaseurl` ระดับเว็บไซต์ครบหรือไม่
   - `$zoomembed` = เป็น Zoom **และ** มี media server **และ** มี `zoommeetingid` แล้ว → true ถ้า
     ครบทั้ง 3 เงื่อนไข (Zoom จะถูก relay เข้าเครื่องเล่นฝัง)
   - `$showplayer` = `$isobs || $zoomembed` — ตัดสินว่าจะ render เครื่องเล่น HLS หรือไม่
5. ถ้า `$showplayer`:
   - คำนวณ `$data['hlsurl']` = `<hlsbaseurl>/<streamkey>/index.m3u8`
   - สั่งโหลด AMD `mod_livestream/player` พร้อม config `{cmid, hlsurl, hlsjsurl}`
   - ถ้า `$canmanage` (มีสิทธิ์ `managestream`) แนบลิงก์ไปหน้า attendance
   - ถ้ามีสิทธิ์ `mod/livestream:chat` โหลด AMD `mod_livestream/chat` ด้วย (เฉพาะโหมดที่มีเครื่องเล่น
     ฝัง — Zoom ธรรมดามีแชทของ Zoom เองอยู่แล้วจึงไม่ต้องซ้ำ)
6. ถ้าเป็น OBS และ `$canmanage` → แนบ `rtmpserver` + `streamkey` ลงไปให้ template แสดง (กล่องตั้งค่า
   OBS สำหรับครู)
7. ถ้าเป็น Zoom → แนบ URL/รหัสผ่าน/สถานะ meeting ทั้งหมดให้ template ตัดสินใจแสดงผลตามสิทธิ์
8. `render_from_template('mod_livestream/view', $data)` — วาด HTML ตาม `templates/view.mustache`

**หมายเหตุสำคัญ**: view.php **ไม่ได้** ทำ liveness check เอง — งานนั้นเกิดฝั่งเบราว์เซอร์ผ่านการ poll
`mod_livestream_get_stream_status` (ดูหัวข้อ 4.1) หน้านี้แค่ตัดสินใจว่าจะ "แสดงเครื่องเล่นหรือไม่"
จาก config เท่านั้น ไม่ได้เช็คว่าไลฟ์อยู่จริงหรือเปล่า

### 3.4 `mod_form.php`
ฟอร์มสร้าง/แก้ไขกิจกรรม (extends `moodleform_mod` มาตรฐานของ Moodle) ประกอบด้วย:

- ชื่อกิจกรรม, คำอธิบาย (intro มาตรฐาน)
- เลือก `streamtype` (OBS หรือ Zoom) พร้อมลิงก์ "จัดการบัญชี Zoom ของฉัน" ที่จะโชว์เฉพาะตอนเลือก
  Zoom (`hideIf('zoomaccountlink', 'streamtype', 'neq', LIVESTREAM_TYPE_ZOOM)`)
- `starttime` (date_time_selector, optional) — ใช้ผูกกับปฏิทิน
- `duration` (นาที/วินาที)
- `zoompasscode` (โชว์เฉพาะโหมด Zoom)
- `recordingurl` (ลิงก์วิดีโอย้อนหลังแบบ manual override)
- **`validation()`**: ถ้าเลือก Zoom แต่ครูยังไม่เคยผูกบัญชี Zoom (`zoom_account::exists()`) → error
  พร้อมลิงก์ไปหน้าผูกบัญชี ถ้าเลือก OBS แต่แอดมินยังไม่ตั้ง `rtmpserver`/`hlsbaseurl` ระดับเว็บไซต์
  → error เช่นกัน — เป็นการบังคับให้ค่า config พร้อมใช้งานจริงก่อนให้บันทึกกิจกรรมได้

### 3.5 `index.php`
หน้ารายการกิจกรรมถ่ายทอดสดทั้งหมดในรายวิชา (`mod/livestream/index.php?id=<courseid>`) — ปลายทางของ
เมนูนำทาง **"Stream"** (ดู 3.2) ใช้ `get_all_instances_in_course()` มาตรฐาน (คืนเฉพาะกิจกรรมที่ผู้ใช้
คนนั้นมีสิทธิ์เห็น) แล้วแสดงตาราง **ชื่อ / ประเภท / สถานะ / เวลาเริ่ม** พร้อมลิงก์เข้าแต่ละกิจกรรม
(หน้า `view.php` เดียวกับที่ครูเห็น) ให้นักเรียนเลือกเข้าดูเอง ถ้าไม่มีกิจกรรมเลยจะแสดง `notice()`
พาย้อนกลับไปหน้ารายวิชา

คอลัมน์ **"สถานะ" (Status)** จะปรากฏ **เฉพาะเมื่อแอดมินตั้งค่า media server แล้ว** (`rtmpserver` +
`hlsbaseurl` ครบ — ไม่งั้นไม่มี HLS manifest ให้ probe และทุกแถวจะดูเหมือนกันหมด) แต่ละแถวที่เป็นสตรีม
แบบมีเครื่องเล่นฝัง (OBS หรือ Zoom ที่มี `zoommeetingid` แล้ว) จะ probe HLS ผ่าน
`mediamtx_client::is_live()` แบบ server-side ตอน render แล้วแสดงป้าย **🔴 LIVE** (แดง กระพริบ ใช้คลาส
`badge badge-danger bg-danger mod_livestream-live` เดียวกับ player) หรือ **Offline** (เทา) ต่อสตรีม —
ส่วนสตรีม Zoom ที่ยังไม่ relay เข้า media server จะแสดง `-` เพราะไม่มีอะไรให้ probe

> จุดสำคัญ: การ probe ในหน้านี้ **ไม่** เรียก `attendance::touch()` (ต่างจาก `get_stream_status`) —
> การเปิดดู "รายการ" ไม่ถือเป็นการเข้าเรียน จึงไม่สร้าง `livestream_session`/`livestream_attendance`
> การ probe เป็นแบบ 1 ครั้งต่อสตรีมต่อการโหลดหน้า (ไม่ใช่ poll loop) จึงเบากว่าฝั่ง player มาก

### 3.6 `settings.php`
หน้าตั้งค่าระดับเว็บไซต์ (Site administration → Plugins → Activity modules → Live stream)
แบ่งเป็น 3 กลุ่ม (`admin_setting_heading`):

1. **OBS / media server**:
   - `rtmpserver` (PARAM_TEXT) — ปลายทาง RTMP ที่ครูใส่ใน OBS
   - `hlsbaseurl` (PARAM_URL) — base URL ของ HLS ที่เครื่องเล่นอ่าน
   - `hlsjsurl` (PARAM_URL, default = jsDelivr CDN) — URL ของไลบรารี hls.js
   - `playbackbaseurl` (PARAM_URL) — base URL ของ playback server (MediaMTX port 9996) สำหรับดู
     ย้อนหลังอัตโนมัติ
   - ทุกตัวมีค่า default เป็น **string ว่าง** โดยตั้งใจ (ตามคอมเมนต์ในไฟล์) เพราะ `mod_form.php`
     ใช้ค่าว่างเป็นสัญญาณ "ยังไม่ได้ตั้งค่า" เพื่อบล็อกไม่ให้ครูสร้างกิจกรรม OBS ที่ชี้ไปที่ที่ไม่มี
     จริง
2. **Zoom**: มีแค่หัวข้ออธิบายว่า Zoom ไม่มีการตั้งค่าระดับเว็บไซต์ (ให้ไปที่ `zoomaccount.php`
   แทน) — ไม่มี setting field จริงในกลุ่มนี้
3. **Realtime gateway (ส่วนที่กำลังพัฒนา, ยังไม่ commit)**:
   - `realtimeurl` (PARAM_URL, default ว่าง) — สวิตช์เปิด/ปิดฟีเจอร์ push แบบ SSE ทั้งระบบ ค่าว่าง =
     ปิด = ทุกอย่างทำงานแบบ polling เหมือนเดิม
   - `realtimesecret` (`admin_setting_configpasswordunmask`) — secret ที่ใช้เซ็น HMAC token และ
     ยืนยันตัวตนการเรียก server-to-server ระหว่าง Moodle กับ gateway

### 3.7 `attendance.php`
รายงานเช็กชื่อ สำหรับครู/ผู้มีสิทธิ์ `managestream` เท่านั้น มี 2 โหมดในไฟล์เดียว ควบคุมด้วย
พารามิเตอร์ `sessionid`:

- **ไม่มี `sessionid`** (`GET ?id=<cmid>`): แสดงรายการ **session** ทั้งหมดของกิจกรรมนี้ (แต่ละแถวคือ
  1 รอบถ่ายทอดสด — จาก `livestream_session`) พร้อมจำนวนผู้เข้าฟังต่อ session (subquery COUNT บน
  `livestream_attendance`) เรียงจากใหม่ไปเก่า
- **มี `sessionid`**: แสดงรายชื่อผู้เข้าฟังของ session นั้นแบบละเอียด (username, ชื่อเต็ม, first
  seen, last seen) พร้อมปุ่มดาวน์โหลด CSV
- **`format=csv`**: ถ้าส่งพารามิเตอร์นี้มาพร้อม `sessionid` จะ stream ไฟล์ CSV ตรงออกไปทาง
  `php://output` แล้ว `exit` ทันที (ไม่ render หน้าเว็บ) โดยมีคอลัมน์ username, fullname,
  first_seen, last_seen, duration_seconds (คำนวณจาก `lastseen - firstseen`)

### 3.8 `zoomaccount.php`
หน้าให้ **แต่ละผู้ใช้ที่ล็อกอิน** (ไม่ใช่แอดมิน) จัดการ credential Zoom Server-to-Server OAuth
ของตัวเอง (`context_user::instance($USER->id)` — เป็น context ระดับผู้ใช้ ไม่ใช่รายวิชา):

- ถ้า `?delete=1` — ต้อง `require_sesskey()` ก่อนเสมอ แล้วโชว์หน้า confirm (`$OUTPUT->confirm`)
  ก่อนลบจริงเมื่อ `?confirm=1` เพิ่มเข้ามา (ป้องกัน CSRF/การลบโดยไม่ตั้งใจ)
- ถ้าไม่ลบ: โหลดฟอร์ม `zoom_account_form`, prefill ด้วยข้อมูลเดิมถ้ามี, บันทึกด้วย
  `zoom_account::save()` เมื่อ submit สำเร็จ
- แสดงลิงก์ "ลบบัญชี Zoom ของฉัน" ถ้ามีบัญชีอยู่แล้ว

---

## 4. `classes/external/` — Web service (AJAX endpoints)

ทุกคลาสในนี้ตามรูปแบบมาตรฐานของ Moodle External API: extends `external_api`, มี
`execute_parameters()` / `execute()` / `execute_returns()` และเรียก `self::validate_context()` +
`require_capability()` ก่อนทำงานเสมอ — ถูก **register** ไว้ที่ `db/services.php` (ดูหัวข้อ 6.3)

### 4.1 `get_stream_status.php` — `mod_livestream_get_stream_status`
หัวใจของระบบ polling ฝั่งเครื่องเล่น (`player.js` เรียกทุก 10 วินาที) รับ `cmid` แล้ว:

1. ตรวจสิทธิ์ `mod/livestream:view`
2. เรียก `mediamtx_client::is_live($hlsbaseurl, $streamkey)` — ยิง HTTP GET ไปเช็ค HLS manifest
   จริงที่ media server (ดูหัวข้อ 5.2) ทั้งโหมด OBS และ Zoom-relay ใช้ path เดียวกันจึงเช็คแบบ
   เดียวกันได้
3. **ถ้าผู้เรียกไม่ใช่ guest และไม่ใช่ผู้มีสิทธิ์ managestream** (คือเป็นนักเรียน/ผู้ชมทั่วไป) จะเรียก
   `attendance::touch($livestreamid, $userid, $live)` — การ poll นี้เลย **ทำหน้าที่คู่**: ทั้งเช็ค
   สถานะไลฟ์ และเป็น heartbeat "ยังดูอยู่" สำหรับระบบเช็กชื่อไปพร้อมกัน (ครูเปิดดูเองไม่นับ
   attendance)
4. ถ้าไม่ไลฟ์: หา URL วิดีโอย้อนหลัง — ถ้ามี `recordingurl` ที่ครูกรอกเองใน mod_form จะใช้อันนั้น
   ก่อนเสมอ ไม่งั้นค่อย auto-discover จาก playback server ผ่าน
   `mediamtx_client::latest_recording_url()`
5. คืนค่า `{live: bool, recordingurl: string}`

### 4.2 `get_chat_messages.php` — `mod_livestream_get_chat_messages`
ให้ `chat.js` poll ทุก 3 วินาที รับ `cmid` + `afterid` (id ข้อความล่าสุดที่ฝั่ง client มีอยู่แล้ว —
เป็น cursor แบบ delta fetch ไม่ดึงซ้ำ) query `livestream_chat` JOIN `user` เอาแค่ id มากกว่า
`afterid` เรียงตามเวลา จำกัดสูงสุด `MAX_MESSAGES = 200` แถวต่อครั้ง (กันกรณี client ตกหล่นไปนาน)
ต้องมีสิทธิ์ `mod/livestream:chat`

### 4.3 `send_chat_message.php` — `mod_livestream_send_chat_message`
บันทึกข้อความแชทใหม่ ตรวจสิทธิ์ `chat`, `trim()` ข้อความ, ถ้าว่างเปล่า throw
`invalid_parameter_exception`, ถ้ายาวเกิน `MAX_LENGTH = 500` ตัวอักษร (ใช้ `core_text::substr`
รองรับ multibyte) จะตัดท้ายทิ้ง แล้ว insert ลง `livestream_chat` คืน `id` ที่สร้างใหม่กลับไป

### 4.4 `delete_chat_message.php` — `mod_livestream_delete_chat_message`
ลบข้อความแชท (moderation) ต้องมีสิทธิ์ `managestream` (ครูเท่านั้น) จงใจ `delete_records()` โดย
เงื่อนไข `id` **และ** `livestreamid` พร้อมกัน — กันไม่ให้ครูของกิจกรรม A เอา `messageid` ที่รู้จัก
มาลบข้อความในกิจกรรม B ได้ (แม้ id จะ valid แต่ไม่ตรงกิจกรรมของ cmid ที่ส่งมา ก็จะลบไม่โดน)

### 4.5 `get_course_live_status.php` — `mod_livestream_get_course_live_status`
ให้ `navbadge.js` poll ทุก 20 วินาที รับ `courseid` แล้ว **วนเช็คทุกกิจกรรมถ่ายทอดสดในรายวิชานั้น**
ที่เป็นแบบ embeddable (OBS หรือ Zoom ที่มี `zoommeetingid` แล้ว) เรียก `mediamtx_client::is_live()`
ทีละอัน เจอตัวแรกที่ไลฟ์ก็ break ทันที คืน `{live: bool, cmid: int}` (cmid ของกิจกรรมที่ไลฟ์ ถ้ามี)
ถ้าไม่มีการตั้งค่า media server เลยจะคืน `live: false` ทันทีโดยไม่ยิง request ใดๆ

---

## 5. `classes/local/` — Business logic helper

### 5.1 `attendance.php`
คลาสจัดการ **session** (1 รอบถ่ายทอดสด) และ **attendance** (ใครดูช่วงไหน) เมธอดหลักคือ
`touch(int $livestreamid, ?int $userid, bool $live)` ซึ่งถูกเรียกจากทุกครั้งที่มีการ probe สถานะ
ไลฟ์ (ทั้งจาก `get_stream_status.php` ตอนมีคนดู และจาก `close_stale_sessions` task):

- หา session ที่ยังเปิดอยู่ (`endtime = 0`) ของกิจกรรมนี้
- **ถ้าไลฟ์**: ถ้ายังไม่มี session เปิดอยู่ → สร้างใหม่ (`starttime = now`) แล้วถ้ามี `$userid` จริง
  → เรียก `record_attendance()` เพื่อ upsert แถวการเข้าดู
- **ถ้าไม่ไลฟ์**: ถ้ามี session เปิดอยู่ → ปิดมัน (`endtime = now`) แล้ว **ลบข้อความแชททั้งหมดของ
  กิจกรรมนี้ทันที** (แชทผูกอายุกับ session ที่มันเกิดขึ้น ไม่ตั้งใจให้อยู่รอดข้ามคาบ)
- `record_attendance()`: ถ้ามีแถวของ user คนนี้ใน session นี้อยู่แล้ว → อัปเดตแค่ `lastseen` ถ้ายังไม่
  มี → insert แถวใหม่ (`firstseen = lastseen = now`) มี try/catch ครอบ `insert_record` ไว้เผื่อ
  request คู่ขนาน 2 อันมาแข่งกัน insert แถวแรกพร้อมกัน (race condition) — unique index
  `sessionid_userid` ใน DB จะบล็อกตัวที่แพ้ ซึ่งโค้ดจะจับ `dml_exception` แล้วแค่ log debug เฉยๆ
  เพราะอีก request ที่ชนะไปแล้วก็ครอบคลุม poll รอบนี้ให้แล้ว

### 5.2 `mediamtx_client.php`
Thin HTTP client คุยกับ media server (MediaMTX) 2 เมธอด static:

- **`is_live($hlsbaseurl, $streamkey)`**: ยิง `GET <hlsbaseurl>/<streamkey>/index.m3u8` ด้วย
  `\curl(['ignoresecurity' => true])` (ข้าม SSRF allowlist ของ Moodle เพราะ media server มักอยู่
  บนพอร์ตที่ไม่ใช่ 80/443) ตั้งใจใช้ **GET ไม่ใช่ HEAD** พร้อมเปิด cookie engine
  (`CURLOPT_COOKIEFILE => ''`) เพราะ MediaMTX มีการ redirect แบบเช็ค cookie ก่อนตอบ manifest จริง
  ซึ่ง HEAD request จะไม่ผ่านขั้นตอนนี้ (จะดูเหมือนออฟไลน์ตลอด) คืน `true` ถ้า HTTP code อยู่ช่วง
  2xx
- **`latest_recording_url($playbackbase, $streamkey)`**: เรียก `GET <playbackbase>/list?path=<key>`
  ของ playback API (พอร์ต 9996) ได้ list ของ segment วิดีโอที่อัดไว้ (เรียงเก่า→ใหม่) หยิบตัว
  สุดท้าย (`end($segments)`) แล้วประกอบ URL เล่นย้อนหลังแบบ `GET <playbackbase>/get?path=...&
  start=...&duration=...&format=mp4` คืนค่าว่างถ้าไม่พบข้อมูล/เชื่อมต่อไม่ได้

### 5.3 `realtime.php` *(ใหม่ — ส่วนหนึ่งของ Phase 0 ที่ยังไม่ commit)*
Helper สำหรับฟีเจอร์ realtime push ที่ยังไม่ได้ใช้งานจริง (ดูหัวข้อ 9) แต่โค้ดตัวมันเองพร้อมใช้แล้ว:

- **`enabled()`**: คืน `true` ถ้า `realtimeurl` ไม่ว่าง — เป็นสวิตช์กลางที่ทุกส่วนของระบบ (ในอนาคต)
  ต้องเช็คก่อนตัดสินใจว่าจะ push หรือ poll
- **`token($uid, $room, $streamkey, $courseid, $cmid, $canmoderate)`**: สร้าง signed token ให้
  เบราว์เซอร์ใช้เปิด SSE connection โดย payload คือ
  `{uid, room, sk, cid, cm, mod, exp: now+60}` แล้วส่งให้ `sign()` เซ็น
- **`sign($payload, $secret)`**: `b64 = base64url(json_encode($payload))` แล้ว
  `sig = hash_hmac('sha256', $b64, $secret)` คืน `"$b64.$sig"` — แยกออกมาจาก `token()` ตั้งใจให้
  เทสได้โดยไม่ต้องพึ่ง `get_config()`
- **`base64url_encode($data)`**: base64 มาตรฐานแล้วแทน `+`→`-`, `/`→`_`, ตัด `=` ท้ายออก (ให้ปลอดภัย
  ใส่ใน query string ตรงๆ ได้โดยไม่ต้อง urlencode ซ้ำ)
- **`publish($room, $event, $data)`**: POST ไปที่ `<realtimeurl>/publish` พร้อม header
  `X-Livestream-Secret` เป็นแบบ **best-effort** — ครอบด้วย try/catch (`\Throwable`) ทั้งก้อน timeout
  สั้น (3 วิ) ไม่ throw ออกไปข้างนอกเด็ดขาด ถ้า gateway ล่มจะแค่ log debug แล้วปล่อยผ่าน (หลักการ
  ตาม `CLAUDE.md`: ห้ามให้ realtime gateway ที่ล่มไปทำให้ฟีเจอร์หลักอย่างแชท/สถานะพัง)

โค้ดตัวนี้ **ยังไม่มีใครเรียกใช้จริง** ในเบราว์เซอร์หรือใน external service อื่นเลย ณ ตอนนี้ — มีแค่
unit test (`tests/local/realtime_test.php`) ที่ยืนยันว่าการเซ็นลายเซ็นถูกต้องตรงกับที่ฝั่ง Node
gateway (ที่ยังไม่ถูกสร้าง) คาดหวังไว้

### 5.4 `zoom_account.php`
CRUD ง่ายๆ สำหรับตาราง `livestream_zoom_account` (เก็บ `accountid`, `clientid`, `clientsecret` ของ
Zoom Server-to-Server OAuth แยกตาม `userid`): `get()`, `exists()`, `save()` (upsert), `delete()`
เก็บ credential เป็น **รายบุคคล** เพราะครูแต่ละคนอาจมีองค์กร Zoom แยกกันคนละบัญชี ไม่ใช่ config
ระดับเว็บไซต์แบบ media server

### 5.5 `zoom_client.php`
Client เรียก Zoom REST API v2 แบบ Server-to-Server OAuth (`account_credentials` grant):

- Constructor รับ `$userid` แล้วโหลด credential ของ user นั้นผ่าน `zoom_account::get()` — ถ้าไม่มีจะ
  throw `moodle_exception('errorzoomnotconfigured', ...)` ทันที
- **`get_token()`**: ขอ access token จาก `https://zoom.us/oauth/token` ด้วย Basic Auth
  (`base64(clientid:clientsecret)`) + `account_id` แล้ว **cache ไว้ในตัวแปร instance** (`$this->token`)
  ตลอดอายุ request เดียว (ไม่ persist ข้าม request)
- **`create_meeting($topic, $starttime, $durationminutes, $passcode)`**: `POST /users/me/meetings`
  — `type = 2` (scheduled) ถ้ามี `starttime`, ไม่งั้น `type = 1` ตั้ง `join_before_host = false`,
  `waiting_room = true`, `mute_upon_entry = true` เป็นค่า default ด้านความปลอดภัย
- **`update_meeting()`** — `PATCH /meetings/<id>` อัปเดต topic/duration/เวลา
- **`delete_meeting()`** — `DELETE /meetings/<id>`
- **`set_livestream($meetingid, $streamurl, $streamkey, $pageurl)`** — `PATCH
  /meetings/<id>/livestream` ตั้งค่า custom live stream ของ meeting ให้ยิง RTMP ไปที่ media server
  ของเรา (ต้องเปิดสิทธิ์ "Custom Live Streaming Service" ในบัญชี Zoom และ OAuth app ต้องมี scope
  ที่เกี่ยวข้อง ไม่งั้น Zoom จะตอบ error)
- **`set_livestream_status($meetingid, $start)`** — สั่ง start/stop custom live stream (ใช้ได้แค่
  ตอน meeting กำลังดำเนินอยู่จริง — ปัจจุบันยังไม่มีจุดไหนในโค้ด plugin เรียกเมธอดนี้ ครูสั่ง
  start/stop จากแอป Zoom เองตามที่บอกใน UI)
- **`request($method, $path, $body)`**: เมธอด private กลางที่ทุกเมธอดข้างต้นเรียกใช้ ใส่ Bearer
  token + Content-Type header, แปลง method (`POST`/`DELETE`/อื่นๆ ผ่าน `CURLOPT_CUSTOMREQUEST`),
  ถ้า HTTP code ≥ 400 จะแปลง response JSON เป็นข้อความ error แล้ว throw
  `moodle_exception('errorzoomapi', ...)`

---

## 6. `db/` — schema, capability, service, task, upgrade

### 6.1 `access.php`
ประกาศ capability 4 ตัว:

| Capability | Context | ใครได้ default | ใช้ทำอะไร |
|---|---|---|---|
| `mod/livestream:addinstance` | Course | editingteacher, manager | สร้างกิจกรรมใหม่ (clone สิทธิ์จาก `moodle/course:manageactivities`) |
| `mod/livestream:view` | Module | guest, student, teacher, editingteacher, manager | ดูกิจกรรม/สถานะไลฟ์/badge |
| `mod/livestream:chat` | Module | student, teacher, editingteacher, manager (ไม่รวม guest) | อ่าน+ส่งแชทสด |
| `mod/livestream:managestream` | Module | teacher, editingteacher, manager | เห็น stream key/Zoom start URL, ลบข้อความแชท, ดูหน้าเช็กชื่อ |

### 6.2 `install.xml`
นิยามตาราง DB 4 ตาราง (XMLDB format ที่ Moodle ใช้ตอนติดตั้งใหม่):

- **`livestream`**: เรคคอร์ดหลักของกิจกรรม — `course`, `name`, `intro`, `streamtype` (0=OBS,
  1=Zoom), `streamkey` (secret path), ฟิลด์ Zoom ทั้งชุด (`zoommeetingid`, `zoomjoinurl`,
  `zoomstarturl`, `zoompasscode`, `zoomownerid` FK ไป `user`), `starttime`, `duration`,
  `recordingurl`, timestamps
- **`livestream_zoom_account`**: credential Zoom ต่อผู้ใช้ 1 แถว/user (`userid` เป็น
  foreign-unique key)
- **`livestream_session`**: 1 แถว/รอบถ่ายทอดสด — `livestreamid`, `starttime`, `endtime` (0 =
  ยังไลฟ์อยู่) มี index `(livestreamid, endtime)` ไว้ query session ที่เปิดอยู่เร็วๆ
- **`livestream_attendance`**: `sessionid`, `userid`, `firstseen`, `lastseen` — unique index คู่
  `(sessionid, userid)` บังคับว่า 1 คนมีได้แค่ 1 แถวต่อ session (คือที่มาของ logic upsert ใน
  `attendance::record_attendance()`)
- **`livestream_chat`**: `livestreamid`, `userid`, `message` (สูงสุด 500 ตัวอักษร),
  `timecreated` — index `(livestreamid, id)` รองรับการ query "ข้อความที่ id มากกว่า X" ให้เร็ว

### 6.3 `services.php`
ลงทะเบียน external function ทั้ง 5 ตัวจากหัวข้อ 4 ให้ Moodle รู้จักเป็น AJAX web service
(`'ajax' => true`) พร้อม capability ที่ต้องใช้ — ไฟล์นี้จะถูกอ่านใหม่ก็ต่อเมื่อมีการ bump
`version.php` เท่านั้น

### 6.4 `tasks.php`
ลงทะเบียน scheduled task `close_stale_sessions` ให้รันทุก **5 นาที** (`minute => '*/5'`)

### 6.5 `upgrade.php`
ฟังก์ชัน `xmldb_livestream_upgrade($oldversion)` ไล่ตามลำดับเวลา (แต่ละ block กันด้วย
`if ($oldversion < ...)` + `upgrade_mod_savepoint()`) สะท้อนประวัติวิวัฒนาการของ schema:

1. `2026071501` — สร้างตาราง `livestream_session` + `livestream_attendance` (ระบบเช็กชื่อ)
2. `2026071600` — สร้างตาราง `livestream_chat` (ระบบแชทสด)
3. `2026071700` — ย้าย Zoom credential จาก config ระดับเว็บไซต์ (`zoomaccountid` ฯลฯ ใน
   `config_plugins`) มาเป็นตาราง `livestream_zoom_account` ต่อผู้ใช้ พร้อมเพิ่มฟิลด์
   `zoomownerid` ในตาราง `livestream` แล้ว `unset_config()` ล้างค่าเก่าทิ้งไม่ให้ secret ค้าง
4. `2026071701` — เพิ่ม index ให้ `zoomownerid` (คู่กับ FK ที่เพิ่งเพิ่ม)

ทุก block ใช้ `if (!$dbman->table_exists/field_exists/index_exists(...))` guard ไว้ก่อนสร้างเสมอ
(idempotent — รันซ้ำได้ปลอดภัย)

---

## 7. `classes/` ส่วนที่เหลือ (event, form, privacy, task)

### 7.1 `classes/event/course_module_viewed.php`
extends `\core\event\course_module_viewed` มาตรฐาน (event log ของ Moodle) แค่ประกาศ
`objecttable = 'livestream'`, `crud = 'r'` (read), `edulevel = LEVEL_PARTICIPATING` และ
`get_objectid_mapping()` สำหรับตอน restore backup ให้ mapping id เก่า→ใหม่ถูกต้อง

### 7.2 `classes/form/zoom_account_form.php`
ฟอร์ม `moodleform` ธรรมดา 3 ช่อง: `accountid`, `clientid` (text, required), `clientsecret`
(`passwordunmask` — พิมพ์แล้วซ่อนแต่กดโชว์ได้) ใช้โดย `zoomaccount.php`

### 7.3 `classes/privacy/provider.php`
Privacy API ของ Moodle (GDPR compliance) ประกาศว่าปลั๊กอินนี้เก็บข้อมูลส่วนบุคคล 2 ประเภท:

1. **`livestream_attendance`** — เวลาที่นักเรียนถูกพบว่าดูถ่ายทอดสด (ผูกกับ context ระดับโมดูล)
2. **`livestream_zoom_account`** — credential Zoom ส่วนตัว (ผูกกับ **context ระดับผู้ใช้**
   `context_user` ไม่ใช่ module เพราะไม่ได้ผูกกับกิจกรรมใดกิจกรรมหนึ่ง)

และมี external location link ไปยัง Zoom เอง (อธิบายว่าการเข้าประชุม Zoom ทำให้ผู้เข้าร่วมส่งข้อมูล
ให้ Zoom โดยตรงตามนโยบายของ Zoom เอง ไม่ใช่ Moodle เก็บ)

Method ที่ implement ครบตาม interface (`get_metadata`, `get_contexts_for_userid`,
`get_users_in_context`, `export_user_data`, `delete_data_for_all_users_in_context`,
`delete_data_for_user`, `delete_data_for_users`) — ทุกตัว query ผ่าน join
`livestream_attendance → livestream_session → livestream (→ course_modules → context)` เพื่อหา
ขอบเขต context ที่ถูกต้อง

**ข้อสำคัญที่ระบุไว้ในคอมเมนต์**: `livestream_chat` **ไม่ถูกนับเป็นข้อมูลส่วนบุคคลที่ต้อง
export/delete** เพราะมันเป็นข้อมูลชั่วคราวโดยออกแบบ (ถูกลบทิ้งเองภายในไม่กี่นาทีหลังจบคาบเรียนอยู่
แล้ว จาก `attendance::touch()` และ `close_stale_sessions`) — ให้เหตุผลเทียบกับ mod_chat เดิมของ
Moodle core ที่เคยมี logic คล้ายกัน

### 7.4 `classes/task/close_stale_sessions.php`
Scheduled task ที่เป็น **safety net** ไม่ใช่กลไกหลัก — กลไกหลักที่ปิด session คือทุกครั้งที่มีคน
poll `get_stream_status` แล้วเจอว่าออฟไลน์ (`attendance::touch()` จะปิด session ให้เอง) แต่ถ้า
**ผู้ชมทุกคนปิดแท็บพร้อมกันก่อน poll รอบสุดท้าย** จะไม่มีใครไป trigger การปิด session เลย —
session (และแชท) จะค้างเปิดตลอดไปถ้าไม่มี task นี้

การทำงาน: query ทุก session ที่ยังเปิดอยู่ (`endtime = 0`) join กับ `livestream` เพื่อได้
`streamkey` แล้ว **probe ใหม่เองโดยตรง** (`mediamtx_client::is_live()`) ถ้าพบว่าไม่ไลฟ์จริงแล้ว →
ปิด session (`endtime = now`) + ลบแชทของกิจกรรมนั้น รันทุก 5 นาที (จาก `db/tasks.php`) — no-op
ทันทีถ้ายังไม่ได้ตั้งค่า `hlsbaseurl`

---

## 8. Frontend: `amd/src/*.js` (โหลดผ่าน `$PAGE->requires->js_call_amd()`)

ทั้ง 3 โมดูลเป็น AMD module (Moodle ใช้ RequireJS) เขียนด้วย ES5 + Promise แพทเทิร์นเดียวกันหมด:
`fetchXxx()` เรียก `core/ajax` → `.then()/.catch()` → อัปเดต DOM → `setTimeout(poll, INTERVAL)`
วนซ้ำตลอดไปตราบใดที่หน้ายังเปิดอยู่ (**ไม่มีการหยุด poll loop เอง** แม้ error ก็แค่ตั้ง state เป็น
false แล้ว poll ต่อ)

**สำคัญ**: ไฟล์ที่เบราว์เซอร์โหลดจริงคือ `amd/build/*.min.js` ไม่ใช่ `amd/src/*.js` — แก้ src
อย่างเดียวจะไม่มีผลอะไรกับเว็บจริง ต้อง build (`grunt amd` หรือ terser) ใหม่ทุกครั้งตาม
`CLAUDE.md` §5.4

### 8.1 `player.js` (poll ทุก 10,000 ms)
- `loadHlsJs(url)`: โหลด `<script>` ของ hls.js แบบ dynamic ครั้งเดียว (เช็ค `window.Hls` ก่อนกันโหลด
  ซ้ำ) คืน Promise
- `fetchStatus(cmid)`: เรียก `mod_livestream_get_stream_status` ผ่าน `core/ajax` — error ใดๆ จับแล้ว
  แปลงเป็น `{live: false, recordingurl: ''}` (ไม่ปล่อยให้ loop พังเพราะ network error)
- `attachPlayer(video, hlsurl, hlsjsurl, onFatal)`: เช็ค native HLS support ก่อน (Safari/iOS รองรับ
  ตรงๆ ผ่าน `video.canPlayType(...)`) ถ้าไม่รองรับค่อยโหลด hls.js มาสร้าง `new Hls({liveSyncDurationCount:
  3})` แล้ว `loadSource` + `attachMedia` เมื่อ manifest parse เสร็จจะสั่ง `video.play()` (จับ
  autoplay-blocked error เงียบๆ ปล่อยให้ผู้ใช้กด play เอง) ผูก event `Hls.Events.ERROR` — ถ้า
  `data.fatal` จะ `hls.destroy()` แล้วเรียก `onFatal` callback
- `init(config)`: หา DOM element ตาม id (`livestream-status-<cmid>`, `livestream-video-<cmid>`)
  ผ่าน `data-region` attribute ต่างๆ ในเทมเพลต แล้วนิยาม:
  - `setLive(live)`: เปลี่ยน badge text/class (LIVE สีแดงมี pulse animation / Offline สีเทา) และ
    โชว์/ซ่อน `player-wrap` กับ `offline-message`
  - `setRecording(url)`: โชว์/ซ่อนปุ่มดูย้อนหลัง
  - `onStreamDied()`: callback จาก fatal client-side error → set state เป็น offline แต่ **ไม่ตั้ง
    timer ใหม่** เพราะ `poll()` loop หลักที่รันอยู่แล้วจะจับสถานะ offline ในรอบถัดไปเองอยู่แล้ว (กัน
    ไม่ให้เกิด timer ซ้อนกัน 2 สาย)
  - `poll()`: เรียก `fetchStatus` — ถ้า live และยังไม่เคย `playing` มาก่อน จะเซ็ต `playing = true`,
    เรียก `setLive(true)`, `attachPlayer()` (ถ้า live อยู่แล้วและ `playing` เป็น true อยู่ก่อน จะไม่
    ทำอะไรซ้ำ นอกจากยืนยันสถานะ — นี่คือจุดที่การ poll ทำหน้าที่ attendance heartbeat ไปในตัวที่
    ฝั่ง server) ถ้า offline จะ set `playing = false`, `setLive(false)`, โชว์ลิงก์ย้อนหลังถ้ามี —
    **เซิร์ฟเวอร์เป็นความจริงสูงสุด (authoritative)**: แม้ตัวเล่นวิดีโอฝั่ง client จะยังไม่ error
    เอง แต่ถ้า server บอกว่า offline ก็เชื่อ server ก่อน จบด้วย `setTimeout(poll, POLL_INTERVAL)`
    เสมอไม่ว่าผลจะเป็นอย่างไร (แม้ error ก็ยังตั้ง timer ต่อ)

### 8.2 `chat.js` (poll ทุก 3,000 ms)
- `fetchMessages(cmid, afterid)`: เรียก `mod_livestream_get_chat_messages`
- `init(config)`: หา DOM ผ่าน `[data-region="chat"][data-cmid="<cmid>"]`
  - `appendMessage(msg)`: สร้าง DOM node ด้วย `document.createElement` + `textContent` **ล้วนๆ ไม่มี
    การใช้ `innerHTML` เลย** — ป้องกัน XSS จากข้อความแชทโดยสมบูรณ์แม้ผู้ส่งจะพยายามใส่ HTML/script
    เข้ามาก็จะกลายเป็นข้อความธรรมดา ถ้า `config.canmoderate` จะแปะปุ่มลบ (×) ที่เรียก
    `mod_livestream_delete_chat_message` แล้วลบ DOM node ทันทีเมื่อสำเร็จ
  - `poll()`: ดึงข้อความใหม่ตั้งแต่ `lastId`, append ทีละอัน, อัปเดต `lastId` ตามข้อความล่าสุด,
    เลื่อน scroll ลงล่างสุดทุกครั้งที่มีข้อความใหม่ (`list.scrollTop = list.scrollHeight`)
  - ผูก `submit` event ของฟอร์ม: ปิดใช้งาน input ชั่วคราวระหว่างส่ง, เรียก
    `mod_livestream_send_chat_message`, พอสำเร็จจะ **เรียก `fetchMessages` ทันทีอีกรอบ** (ไม่รอ
    poll ตามรอบปกติ) เพื่อให้คนพิมพ์เห็นข้อความตัวเองโผล่ทันที ไม่ต้องรอ 3 วินาที ถ้าส่งไม่สำเร็จจะ
    คืนค่าข้อความกลับเข้า input ให้พิมพ์ใหม่/ลองใหม่

### 8.3 `navbadge.js` (poll ทุก 20,000 ms)
- `fetchStatus(courseid)`: เรียก `mod_livestream_get_course_live_status`
- `init(config)`: หา link `li[data-key="livestreams"] a.nav-link` ที่ `lib.php` สร้างไว้แล้ว (ทำงาน
  ทุกหน้าของรายวิชา ไม่ใช่แค่หน้ากิจกรรม) สร้าง `<span>` badge สีแดงต่อท้ายลิงก์ (ซ่อนไว้ก่อน) แล้ว
  poll โชว์/ซ่อน badge ตาม `status.live` — ไม่ได้ใช้ `status.cmid` ที่ตอบกลับมาทำอะไรเพิ่มเติมใน
  โค้ดปัจจุบัน (แค่โชว์ badge เฉยๆ ไม่ได้ลิงก์ตรงไปกิจกรรมที่ไลฟ์)

---

## 9. `templates/`, `lang/`, `styles.css`

### 9.1 `templates/view.mustache`
เทมเพลตเดียวที่ `view.php` ใช้ วาดผลตาม flag ที่ควบคุมด้วย Mustache section (`{{#flag}}...{{/flag}}`)
เรียงจากบนลงล่าง:

1. Intro (คำอธิบายกิจกรรม) + เวลาเริ่มถ้ามี
2. `{{#showplayer}}` — badge สถานะ (`data-region="status-badge"`), กล่องวิดีโอ (`data-region=
   "player-wrap"`, เริ่มซ่อนไว้), ข้อความ "ยังไม่เริ่ม" (`data-region="offline-message"`), ปุ่มดู
   ย้อนหลัง (`data-region="recording"`, ซ่อนไว้ก่อน), ลิงก์ attendance ถ้า `canmanage`, panel แชท
   ถ้า `canchat` (มีฟอร์ม input + ปุ่มส่ง) — **ทุก region เหล่านี้คือจุดที่ JS ไปจับ (`querySelector`)
   มาควบคุม** ต้อง sync ชื่อ `data-region` ให้ตรงกับที่ `player.js`/`chat.js` คาดหวังเป๊ะๆ
3. `{{#isobs}}{{#canmanage}}` — กล่อง "OBS setup" โชว์ RTMP server + stream key ให้ครูคัดลอกไปใส่
   OBS
4. `{{#iszoom}}` — แยกเป็นหลายกรณีซ้อนกัน: มี meeting แล้วหรือยัง (`zoomconfigured`), ครูเห็น
   controls (start meeting, join, meeting id/passcode) ต่างจากนักเรียนที่เห็นแค่ปุ่ม join (เมื่อ
   `zoomjoinonly` เป็น true คือไม่มี embed)
5. `{{^showplayer}}{{#recordingurl}}` — กรณีไม่มีเครื่องเล่นฝัง (Zoom ธรรมดา) แต่มี recording url
   manual ให้แสดงปุ่มดูย้อนหลังแยกต่างหาก

### 9.2 `lang/en/livestream.php` และ `lang/th/livestream.php`
ข้อความในระบบทั้งหมด — **สองไฟล์นี้ต้องมี key ตรงกันเป๊ะเสมอ** (มาตรฐาน Moodle ที่ CLAUDE.md ก็เน้น
ย้ำ) ครอบคลุมตั้งแต่ชื่อ capability, ข้อความ error ของ Zoom API, help text ของฟอร์ม, ไปจนถึงข้อความ
settings ของฟีเจอร์ realtime ที่ยังไม่ commit (บ่งชี้ว่าทั้งสองภาษาถูกอัปเดตพร้อมกันตามธรรมเนียมนี้
ตั้งแต่ยังไม่ได้ merge เข้า production)

### 9.3 `styles.css`
CSS เล็กๆ 4 กลุ่ม: animation กระพริบ (`mod_livestream-pulse`) ให้ badge LIVE, บังคับสัดส่วน 16:9
ของกล่องวิดีโอแบบ fallback ด้วย `padding-top: 56.25%` (เผื่อธีมไม่มี Bootstrap 5 `.ratio` helper),
และ `user-select: all` บน stream key (ให้ครูคลิกครั้งเดียวเลือกทั้งก้อนคัดลอกง่าย)

---

## 10. `backup/moodle2/` — Backup & Restore

Moodle ต้องมีคู่ backup/restore step สำหรับทุก activity module ถ้าต้องการให้ course backup/restore
ทำงานได้ (`FEATURE_BACKUP_MOODLE2` ที่ประกาศใน `lib.php`)

### 10.1 `backup_livestream_activity_task.class.php`
นิยาม step เดียว: `backup_livestream_activity_structure_step` และมี
`encode_content_links()` แปลง URL เต็ม (`.../mod/livestream/view.php?id=123`) ในเนื้อหาข้อความ
อื่นๆ (เช่นลิงก์ในหน้า label อื่นที่อ้างมาที่นี่) ให้เป็น placeholder ที่ restore แล้วแปลงกลับเป็น
id ใหม่ได้ถูกต้อง

### 10.2 `backup_livestream_stepslib.php`
กำหนดว่า field ไหนถูก backup บ้าง: `name`, `intro`, `introformat`, `streamtype`, `starttime`,
`duration`, `recordingurl`, timestamps — **จงใจไม่รวม** `streamkey`, `zoommeetingid`,
`zoomjoinurl`, `zoomstarturl`, `zoompasscode`, `zoomownerid` เพราะเป็น **secret ที่ผูกกับ
site/meeting เดิม** เอาไป restore ที่อื่นแล้วใช้ต่อไม่ได้และไม่ปลอดภัย

### 10.3 `restore_livestream_activity_task.class.php`
ประกาศ decode content (`intro`) และ decode rule (แปลง placeholder `LIVESTREAMINDEX`/
`LIVESTREAMVIEWBYID` กลับเป็น URL จริงด้วย course/course_module id ใหม่หลัง restore) และ log rule
สำหรับ event เก่า

### 10.4 `restore_livestream_stepslib.php`
`process_livestream($data)`: insert เรคคอร์ดใหม่โดย **สุ่ม `streamkey` ใหม่เสมอ** (ไม่เอาค่าเก่ามา
ใช้ต่อ) และล้าง field Zoom ทั้งหมดเป็นค่าว่าง — ครูต้องเปิดกิจกรรมมาบันทึกซ้ำเองเพื่อสร้าง meeting
Zoom ใหม่ (`after_execute()` restore ไฟล์แนบของ intro ตามมาตรฐาน)

---

## 11. `tests/local/realtime_test.php`

Unit test เดียวในโปรเจกต์ (PHPUnit, `advanced_testcase`) ทดสอบ `classes/local/realtime.php` 3 เคส:

1. **`test_sign_matches_known_vector()`**: ทดสอบด้วย payload คงที่ (ไม่พึ่ง `time()`/`get_config()`)
   คำนวณ expected JSON / base64url / HMAC เองแบบ independent แล้วเทียบกับผลจาก `realtime::sign()`
   — จุดประสงค์ตามคอมเมนต์คือกัน **ไม่ให้การเซ็น token ฝั่ง PHP กับฝั่ง Node gateway (ที่ยังไม่ถูก
   สร้าง) เพี้ยนไปจากกันแบบเงียบๆ** เพราะถ้าสองฝั่งเซ็นไม่ตรงกัน จะกลายเป็น token ทุกใบถูก reject
   หมดตอน production ซึ่งจะดีบักยากมาก ทดสอบด้วยว่า output ไม่มี `=`, `+`, `/` (ปลอดภัยใส่ query
   string ตรงๆ)
2. **`test_token_round_trip()`**: เทส `token()` จริงที่ผูกกับ `get_config()` (ตั้ง
   `realtimesecret` ผ่าน `set_config()` แล้ว `resetAfterTest()`) ถอดรหัส payload กลับมาตรวจว่าค่า
   ทุกฟิลด์ (`uid`, `room`, `sk`, `cid`, `cm`, `mod`, `exp`) ตรงกับที่ส่งเข้าไป และ `exp` อยู่ในช่วง
   `now+60` ที่คาดไว้
3. **`test_enabled_reflects_realtimeurl()`**: ยืนยันว่า `enabled()` สะท้อนค่า `realtimeurl` ตรงๆ —
   comment เน้นว่านี่คือ **สวิตช์เดียว** ที่ทุกโมดูล AMD (ในอนาคต) จะใช้ตัดสินใจ fallback ไป polling

---

## 12. `media-server/` — โครงสร้างพื้นฐานฝั่ง media

ส่วนนี้ **ไม่ใช่โค้ด Moodle** แต่เป็น infrastructure (docker) ที่ปลั๊กอินต้องพึ่งพาเพื่อให้โหมด OBS/
Zoom-relay ทำงานได้จริง

### 12.1 `docker-compose.yml`
กำหนด 2 service บน network เดียวกันชื่อ `media`:

- **`mediamtx`** (image `bluenviron/mediamtx:latest`) — เปิดพอร์ต **1935 (RTMP) เท่านั้น** ออกสู่
  โฮสต์ตรงๆ (เพราะ Caddy proxy raw RTMP ไม่ได้) ส่วน HLS (8888) และ playback (9996) **ไม่ publish
  ออกนอก** เข้าถึงได้เฉพาะผ่าน internal network โดย Caddy เท่านั้น mount `mediamtx.yml` (config)
  และ `./recordings` (ที่เก็บไฟล์อัดวิดีโอ แบบ bind mount ถาวร)
- **`caddy`** (image `caddy:2-alpine`) — เปิดพอร์ต 80 (ACME challenge + redirect HTTPS), 443
  tcp/udp (HTTPS + HTTP/3) รับ env var `HLS_DOMAIN`, `PLAYBACK_DOMAIN`, `ACME_EMAIL` (บังคับต้องตั้ง
  ค่าใน `.env` ไม่งั้น compose จะ error ด้วย syntax `${VAR:?message}`) mount `Caddyfile` + volume
  เก็บ certificate (`caddy_data`, `caddy_config`) ให้ต่ออายุ certificate ได้เองอัตโนมัติแม้ restart
  container

### 12.2 `Caddyfile`
กำหนด reverse proxy 2 โดเมนย่อยแยกกัน (**ต้องแยกโดเมน ห้ามรวมเป็น domain เดียวแยก path**) เหตุผล
ตามคอมเมนต์ในไฟล์: MediaMTX ตอบ HLS request แรกด้วย absolute-path redirect (cookie-check hop) ที่
ไม่รู้จัก prefix ใดๆ ที่มันถูก mount ไว้ใต้ ถ้า proxy แบบ path-based (`/hls/*`) prefix จะถูกตัดออกไป
ตอน redirect ทำให้ URL เพี้ยนแล้ว 404 การ proxy ที่ root ของแต่ละ subdomain แยกกันหลบปัญหานี้ได้
สนิท — `{$HLS_DOMAIN}` proxy ไป `mediamtx:8888`, `{$PLAYBACK_DOMAIN}` proxy ไป `mediamtx:9996`
ทั้งคู่เปิด `encode gzip`

### 12.3 `mediamtx.yml`
Config หลักของ MediaMTX:

- `rtmp: yes`, `rtmpAddress: :1935` — รับ RTMP ขาเข้า
- `hls: yes`, `hlsAddress: :8888`, `hlsAlwaysRemux: yes`, `hlsVariant: lowLatency`,
  `hlsSegmentCount: 7`, `hlsSegmentDuration: 1s`, `hlsPartDuration: 200ms` — ตั้งค่า Low-Latency
  HLS ให้ latency ต่ำที่สุดเท่าที่ HLS ทำได้ (README ระบุ end-to-end ~3–8 วินาทีสำหรับโหมด OBS)
  `hlsAllowOrigins: ['*']` เปิด CORS ให้ Moodle (คนละ origin) ดึง manifest/segment ได้
- `playback: yes`, `playbackAddress: :9996` — เปิด API `list`/`get` สำหรับดึงวิดีโอย้อนหลัง
- `rtsp: no`, `webrtc: no`, `srt: no` — ปิดโปรโตคอลอื่นที่ไม่ได้ใช้ ลด attack surface
- `pathDefaults`: ทุก path ที่เข้ามาจะถูกอัดอัตโนมัติ (`record: yes`) เก็บที่
  `/recordings/%path/%Y-%m-%d_%H-%M-%S-%f`, format `fmp4`, `recordDeleteAfter: 0s` (เก็บตลอดไป
  จนกว่าจะตั้งค่าใหม่)
- `paths: { all_others: }` — ยอมรับทุก path (stream key ของปลั๊กอินคือ path ที่สุ่มมา จึงต้องรับได้
  ทุกชื่อ ไม่ fix ไว้ล่วงหน้า)

### 12.4 `.env.example`
Template ให้ copy เป็น `.env` (ถูก gitignore) กรอก `HLS_DOMAIN`, `PLAYBACK_DOMAIN` (ต้องเป็นคนละชื่อ
โดเมนที่มี DNS ชี้มาที่เซิร์ฟเวอร์แล้วก่อนรัน เพราะ Caddy จะขอ certificate ทันทีตอน boot) และ
`ACME_EMAIL` (อีเมลรับแจ้งเตือนจาก Let's Encrypt)

---

## 13. Flow การทำงานสำคัญ แบบ end-to-end

### 13.1 ครูสร้างกิจกรรมโหมด OBS
1. `mod_form.php::validation()` เช็คก่อนว่า `rtmpserver`+`hlsbaseurl` ตั้งไว้แล้วหรือยัง
2. `lib.php::livestream_add_instance()` สุ่ม `streamkey`, insert ลง DB, สร้าง calendar event
3. เปิดกิจกรรม → `view.php` render กล่อง "OBS setup" (RTMP server + stream key) ให้ครูคัดลอกไปใส่
   OBS (Settings → Stream → Custom)
4. ครูกด Start Streaming ใน OBS → MediaMTX เริ่มรับ RTMP ที่ path `<streamkey>`
5. `player.js` (poll ทุก 10s) เรียก `get_stream_status` → server เช็ค HLS manifest เจอ → ตอบ
   `live: true` → เบราว์เซอร์ attach hls.js เข้า `<video>` → เล่นสด

### 13.2 ครูสร้างกิจกรรมโหมด Zoom (พร้อม media server)
1. ครูต้องผูกบัญชี Zoom ของตัวเองที่ `zoomaccount.php` มาก่อนอย่างน้อย 1 ครั้ง
2. `livestream_add_instance()` → `livestream_create_zoom_meeting()` → `zoom_client::create_meeting()`
   สร้าง meeting บน Zoom ของครูคนนั้น → `livestream_configure_zoom_livestream()` →
   `zoom_client::set_livestream()` ผูก custom live stream ให้ยิง RTMP ไปที่
   `<rtmpserver>/<streamkey>` เดียวกับ OBS
3. ครูเปิดกิจกรรม กด "Start meeting (host)" → ในแอป Zoom เลือก "More → Live on Custom Live
   Streaming Service" → Zoom เริ่มยิง RTMP เข้า MediaMTX เหมือน OBS ทุกประการ
4. ที่เหลือเหมือน flow OBS ทุกอย่าง (player.js เช็คสถานะแบบเดียวกัน เพราะ mediamtx มองไม่เห็นความ
   ต่างระหว่าง OBS กับ Zoom-relay เลย — ทั้งคู่เป็นแค่ RTMP publisher ธรรมดา)

### 13.3 การถ่ายทอดสด → attendance → จบคาบ
1. ทุก poll ของ `get_stream_status` (10 วินาที) ที่มาจากนักเรียน (ไม่ใช่ guest/ครู) จะเรียก
   `attendance::touch()` — รอบแรกที่เจอ `live=true` จะเปิด `livestream_session` ใหม่ + สร้างแถว
   `livestream_attendance` (`firstseen=lastseen=now`)
2. poll รอบถัดๆ ไปขณะยังไลฟ์อยู่ จะแค่อัปเดต `lastseen` ของแถวเดิม (upsert)
3. เมื่อครูหยุดสตรีม → MediaMTX manifest หายไป → poll รอบถัดไปเจอ `live=false` →
   `attendance::touch()` ปิด session (`endtime=now`) + ลบ `livestream_chat` ของกิจกรรมนั้นทั้งหมด
4. ถ้าทุกคนปิดแท็บไปก่อนที่จะมี poll รอบสุดท้ายมาเจอ offline → `close_stale_sessions` (ทุก 5 นาที)
   จะ probe เองแล้วปิด session ค้างให้แทน
5. ครูเข้าดูที่ `attendance.php` เห็นรายชื่อ + first/last seen ต่อ session ดาวน์โหลด CSV ได้

### 13.4 ดูย้อนหลัง
1. MediaMTX อัดทุกสตรีมลงดิสก์อัตโนมัติ (`pathDefaults.record: yes` ใน `mediamtx.yml`)
2. เมื่อ `get_stream_status` เจอ `live=false` และไม่มี `recordingurl` แบบ manual → เรียก
   `mediamtx_client::latest_recording_url()` ไปถาม playback API (port 9996 ผ่าน Caddy) หา segment
   ล่าสุด → ประกอบ URL เล่นเป็น mp4 ทั้งก้อน
3. `player.js` โชว์ปุ่ม "ดูย้อนหลัง" ให้กด

---

## 14. สรุปตาราง Database ทั้งหมด

| ตาราง | Key สำคัญ | ใช้ทำอะไร | อายุข้อมูล |
|---|---|---|---|
| `livestream` | `streamkey` (secret), `zoomownerid` (FK user) | เรคคอร์ดกิจกรรม 1 แถว/กิจกรรม | ถาวร (จนลบกิจกรรม) |
| `livestream_zoom_account` | `userid` (unique FK) | credential Zoom ต่อครู 1 คน | ถาวรจนครูลบเอง |
| `livestream_session` | `livestreamid`, `endtime=0`=เปิดอยู่ | 1 แถว/รอบถ่ายทอดสด | ถาวร (ประวัติ) |
| `livestream_attendance` | unique `(sessionid, userid)` | first/last seen ต่อคนต่อ session | ถาวร (ประวัติ) |
| `livestream_chat` | `livestreamid`, `id` | ข้อความแชทสด | **ชั่วคราว** — ลบทิ้งทันทีที่ session ปิด |

---

## 15. สรุปสิทธิ์ (Capabilities) ตามบทบาท

| บทบาท | view | chat | managestream | addinstance |
|---|---|---|---|---|
| Guest | ✅ | ❌ | ❌ | ❌ |
| Student | ✅ | ✅ | ❌ | ❌ |
| Teacher (non-editing) | ✅ | ✅ | ✅ | ❌ |
| Editing teacher | ✅ | ✅ | ✅ | ✅ |
| Manager | ✅ | ✅ | ✅ | ✅ |

---

## 16. แผนงานในอนาคต — Realtime push (SSE) ตาม `CLAUDE.md`

หัวข้อนี้สรุปสั้นๆ ว่า `CLAUDE.md` วางแผนอะไรไว้ต่อจากนี้ (ไม่ใช่สิ่งที่ทำงานอยู่ตอนนี้ —
ดูหัวข้อ "สถานะการพัฒนาปัจจุบัน" ด้านบนอีกครั้ง):

- เพิ่ม Node service เล็กๆ ("realtime gateway") ข้าง MediaMTX ทำหน้าที่ terminate การเชื่อมต่อ
  Server-Sent Events (SSE) จำนวนมากแบบ event-loop (ไม่ใช้ PHP worker ต่อ connection เด็ดขาด เพราะ
  Moodle เป็นแบบ 1 process/1 request — ถ้าเปิด SSE ค้างใน PHP จะกิน worker pool ทั้งเว็บไซต์)
- Moodle ยังคง authoritative ด้าน business logic ทั้งหมด (auth, DB, attendance) gateway แค่รับ
  publish event จาก Moodle แล้ว fan-out ไปยัง browser ที่ subscribe ห้อง (room) เดียวกันอยู่
  (`cm-<cmid>` สำหรับ status+chat, `course-<courseid>` สำหรับ badge)
  - gateway เองก็ probe HLS manifest ของแต่ละ stream **1 ครั้งต่อ stream** (ไม่ใช่ 1 ครั้งต่อผู้ชม
    เหมือนที่ polling ทำอยู่ตอนนี้) แล้วรายงาน transition กลับมาที่ Moodle ผ่าน endpoint ลับ
    (`streamevent.php`, `presence.php`, `resolve_course.php` — ป้องกันด้วย header
    `X-Livestream-Secret` และ `NO_MOODLE_COOKIES`)
- ทุกโมดูล AMD (player/chat/navbadge) จะ **คงโค้ด polling เดิมไว้เป็น fallback เสมอ** — ถ้า
  `realtimeurl` ว่าง หรือเปิด `EventSource` ไม่สำเร็จ/หลุดแล้วต่อกลับไม่ได้ จะสลับไปใช้ polling
  อัตโนมัติ
- `classes/local/realtime.php` (ที่มีอยู่แล้วตอนนี้) จะเป็นแกนกลางของทั้งฝั่ง sign token
  (`get_realtime_token` web service ที่ยังไม่ถูกสร้าง) และฝั่ง publish event เข้า gateway

ใครจะทำงานต่อจากจุดนี้ควรอ่าน `CLAUDE.md` เต็มไฟล์ (มีสเปคของ contract ทุกจุดแบบละเอียดมาก — token
format, HTTP contract, guardrails 12 ข้อที่ห้ามทำ) ก่อนเขียนโค้ดต่อ

---

## 17. คู่มือการใช้งานปลั๊กอิน (User Manual)

หัวข้อนี้คือ **คู่มือการใช้งานจริง** แบบครบในตัว แยกตามบทบาท (แอดมิน / ครู / นักเรียน) เน้น "ต้องกด
อะไรตรงไหน" ไม่ใช่การอธิบายโค้ด (โค้ดอยู่หัวข้อ 1–16 ด้านบน) ฉบับย่อภาษาไทยอีกชุดอยู่ที่
[`docs/usage-th.md`](docs/usage-th.md) และคู่มือติดตั้ง production อยู่ที่ [`README.md`](README.md)

### 17.1 ใครทำอะไร (ภาพรวม 30 วินาที)

| บทบาท | ทำอะไร | เห็นอะไร |
|---|---|---|
| **แอดมิน** | รัน media server + กรอกค่า RTMP/HLS/Playback ในหน้าตั้งค่าปลั๊กอิน (ครั้งเดียว) | หน้า Site administration |
| **ครู (OBS)** | สร้างกิจกรรม → เอา Server URL + Stream key ไปใส่ OBS → Start Streaming | กล่องตั้งค่า OBS + เครื่องเล่น + ลิงก์เช็กชื่อ |
| **ครู (Zoom)** | ผูกบัญชี Zoom ครั้งแรก → สร้างกิจกรรม → เปิดประชุม → "Live on Custom Live Streaming Service" | ปุ่มเริ่มประชุม + เครื่องเล่น |
| **นักเรียน** | คลิกกิจกรรม **หรือ** เมนู **"Stream"** → เลือกสตรีมที่ LIVE → ดู | เครื่องเล่นฝังในหน้า + แชทสด |

3 โหมดที่ปลั๊กอินรองรับ: **OBS**, **Zoom (relay เข้าเครื่องเล่นฝัง)**, และ **ดูย้อนหลัง** (อัดอัตโนมัติทุกสตรีม)

### 17.2 แอดมิน — ตั้งค่าครั้งเดียว

1. **รัน media server** — `cd media-server && cp .env.example .env` แก้ค่าโดเมน/อีเมล แล้ว
   `docker compose up -d` (production มี Caddy ทำ HTTPS ให้อัตโนมัติ) รายละเอียดเต็มดู `README.md`
   และ `docs/usage-th.md` ส่วนที่ 1
2. **กรอกค่าในปลั๊กอิน** — *Site administration → Plugins → Activity modules → Live stream*

   | Setting | ตัวอย่าง (production) | จำเป็นสำหรับ |
   |---|---|---|
   | RTMP server URL | `rtmp://media.example.com:1935` | OBS + Zoom (ห้ามมี path/key ต่อท้าย) |
   | HLS base URL | `https://live.media.example.com` | OBS + Zoom (นักเรียนดูผ่าน URL นี้ — production ต้อง HTTPS) |
   | Recording playback URL | `https://vod.media.example.com` | ดูย้อนหลัง (เว้นว่าง = ไม่โชว์ปุ่มอัตโนมัติ) |
   | hls.js URL | *(ค่าเริ่มต้น CDN)* | ทุกโหมด |
   | Realtime gateway URL | *(เว้นว่าง)* | ยังไม่ใช้ — เว้นว่างไว้ = polling ปกติ (ดูหัวข้อ 16) |

   > Zoom **ไม่มี**การตั้งค่าระดับเว็บไซต์ — ครูแต่ละคนผูกบัญชี Zoom ของตัวเอง (ดู 17.3)

### 17.3 ครู — เชื่อมบัญชี Zoom (ทำครั้งแรกครั้งเดียว, เฉพาะโหมด Zoom)

โหมด OBS **ข้ามหัวข้อนี้ได้เลย** เพราะครูแต่ละคนอาจมีบัญชี/องค์กร Zoom แยกกัน ปลั๊กอินจึงไม่มีบัญชี Zoom กลาง

| ขั้น | ทำอะไร |
|---|---|
| 1 | [marketplace.zoom.us](https://marketplace.zoom.us) → **Develop → Build App → Server-to-Server OAuth** |
| 2 | ใส่ Scope: `meeting:write:meeting` (+ `:admin` ถ้า account-level) และ scope live streaming (`meeting:update:livestream` หรือ `meeting:write:admin`) แล้ว **Activate** |
| 3 | ในรายวิชาใดก็ได้ → เมนูนำทาง → **"จัดการบัญชี Zoom ของฉัน"** → วาง Account ID / Client ID / Client Secret → บันทึก |
| 4 | ในบัญชี Zoom: **Settings → In Meeting (Advanced) → Allow live streaming → ✅ Custom Live Streaming Service** |

> ถ้าข้ามขั้น 4 หรือ scope ไม่ครบ กิจกรรมยังบันทึกได้ แต่จะ fallback เป็นปุ่ม "Join Zoom" ธรรมดา
> ถ้ายังไม่เคยผูกบัญชีเลย ฟอร์มจะไม่ให้บันทึกกิจกรรมแบบ Zoom (พร้อมลิงก์ไปหน้าผูกบัญชี)

### 17.4 ครู — สอนแบบ OBS

| ขั้น | ทำอะไร | เบื้องหลัง |
|---|---|---|
| 1 | ในรายวิชา *เพิ่มกิจกรรม* → **Live stream** → **Stream type = OBS / media server** → บันทึก | ปลั๊กอินสุ่ม `streamkey` ให้ |
| 2 | เปิดกิจกรรม → เห็นกล่องครูที่มี **Server URL** และ **Stream key** | เฉพาะผู้มีสิทธิ์ `managestream` |
| 3 | ใน OBS: **Settings → Stream → Custom** วาง Server URL + Stream key | — |
| 4 | กด **Start Streaming** | mediamtx เริ่มรับ RTMP → แปลงเป็น HLS |
| 5 | เครื่องเล่นในหน้า Moodle เด้งเป็น 🔴 LIVE เองภายในไม่กี่วินาที | player poll ทุก 10 วิ เจอ live |

### 17.5 ครู — สอนแบบ Zoom (ฝังในหน้า Moodle)

ต้องผูกบัญชี Zoom (17.3) และตั้ง media server (17.2) ก่อน

| ขั้น | ครูทำ | เบื้องหลัง |
|---|---|---|
| 1 | *เพิ่มกิจกรรม* → **Live stream** → **Stream type = Zoom meeting** → บันทึก | ปลั๊กอินสร้างห้อง Zoom + ตั้ง custom live stream ชี้ RTMP+streamkey อัตโนมัติ |
| 2 | เปิดกิจกรรม → **Start meeting (host)** | เปิด Zoom ในฐานะโฮสต์ |
| 3 | ในแอป Zoom: **More (…) → Live on Custom Live Streaming Service** | Zoom เริ่มยิงภาพเข้า media server เหมือน OBS |
| 4 | สอน | เครื่องเล่นในหน้า Moodle ขึ้น 🔴 LIVE เอง (นักเรียนไม่ต้องเปิด Zoom) |
| 5 | ปิด live / ปิดประชุม | คลิปถูกอัด → ปุ่มดูย้อนหลังโผล่ |

> ⚠️ Zoom ยิงภาพจาก **cloud ของ Zoom** → `RTMP server URL` ต้องเข้าถึงได้จากอินเทอร์เน็ต (IP วง LAN
> เช่น `10.0.150.190` ใช้กับ Zoom ไม่ได้ ต่างจาก OBS ที่รันในวงเดียวกันได้) และ latency สูงกว่า OBS (~20–30 วิ)

### 17.6 นักเรียน — เข้าดูสตรีม

นักเรียนเข้าดูได้ **2 ทาง**:

- **ทางตรง** — คลิกกิจกรรม Live stream บนหน้ารายวิชาได้เลย
- **ผ่านเมนู "Stream"** — คลิกเมนู **"Stream"** ในเมนูนำทางของรายวิชา → เข้าหน้ารายการสตรีมทั้งหมด →
  ดูคอลัมน์ **สถานะ** ว่าอันไหน **🔴 LIVE** → คลิกชื่อสตรีมนั้นเพื่อเข้าดู (ดูรายละเอียดเมนูนี้ที่ 17.10)

เมื่ออยู่ในหน้าสตรีมแล้ว:
- ก่อนครูเริ่ม จะเห็น **Offline** — หน้าจะ **เล่นวิดีโอเองอัตโนมัติ** ทันทีที่ครูเริ่มถ่ายทอด (ระบบ
  ตรวจสถานะทุก 10 วินาที) ไม่ต้อง refresh
- **ไม่ต้องเปิด Zoom / ไม่ต้องมีบัญชี Zoom** (ยกเว้นกรณีที่ระบบ fallback เป็นปุ่ม Join Zoom ธรรมดา)
- ถ้าครูเปิดแชท จะมีช่องแชทสดข้างเครื่องเล่น (ดู 17.7)
- นักเรียน **ไม่เห็น** Server URL/Stream key และลิงก์เช็กชื่อ (เป็นของครูเท่านั้น)

### 17.7 แชทสด (Live chat)

- ปรากฏเฉพาะโหมดที่มีเครื่องเล่นฝัง (OBS / Zoom-relay) และเฉพาะผู้มีสิทธิ์ `chat` (นักเรียนขึ้นไป —
  guest แชทไม่ได้) — Zoom ธรรมดามีแชทของ Zoom เองอยู่แล้วจึงไม่มีช่องนี้
- ข้อความ **ชั่วคราว** — ถูกลบทิ้งทั้งหมดทันทีที่คาบถ่ายทอดสดจบ (ไม่เก็บข้ามคาบ)
- ครู (สิทธิ์ `managestream`) ลบข้อความรายอันได้

### 17.8 ครู — เช็กชื่อ (Attendance) + ดาวน์โหลด CSV

- ระบบบันทึกอัตโนมัติว่านักเรียนคนไหน "ดูอยู่จริง" ช่วงไหนของการถ่ายทอด (first seen / last seen) ต่อ
  รอบการถ่ายทอด (session) — ไม่นับ guest และไม่นับตัวครูเอง
- ครูเปิดกิจกรรม → คลิกลิงก์ **Attendance** (โผล่เฉพาะผู้มีสิทธิ์ `managestream`) → เห็นรายชื่อต่อ
  session → กด **ดาวน์โหลด CSV** ได้
- ถ้าทุกคนปิดแท็บก่อนระบบเห็นว่า offline → task เบื้องหลัง `close_stale_sessions` (ทุก 5 นาที) ปิด session ค้างให้เอง

### 17.9 ดูย้อนหลัง (Recording)

- ทุกสตรีม (OBS หรือ Zoom) ถูก **อัดอัตโนมัติ**
- เมื่อคาบจบและแอดมินตั้ง *Recording playback URL* ไว้ → ปุ่ม **ดูย้อนหลัง** ปรากฏเองในหน้าสตรีม
- ครูจะวาง **Recording URL** เองในหน้าตั้งค่ากิจกรรมเพื่อ override (เช่นชี้ไป YouTube) ก็ได้

### 17.10 เมนูนำทาง "Stream" + หน้ารายการสตรีม (ฟีเจอร์แชร์หน้าสตรีมให้นักเรียน)

จุดประสงค์: ให้นักเรียนเข้าถึง "หน้า STREAM ของครู" ได้ง่ายจากเมนูนำทางของรายวิชา โดยไม่ต้องไล่หากิจกรรมในหน้ารายวิชาเอง

- เมนู **"Stream"** จะโผล่ในเมนูนำทางของรายวิชา (secondary navigation / เมนู "More" ในธีม Boost)
  **อัตโนมัติ** เมื่อ (ก) ผู้ใช้มีสิทธิ์ `view` — ครอบคลุมนักเรียน และ (ข) รายวิชานั้นมีกิจกรรม
  Live stream อย่างน้อย 1 ตัว
- คลิกแล้วเข้า **หน้ารายการสตรีม** (`index.php`) แสดงตาราง **ชื่อ / ประเภท / สถานะ / เวลาเริ่ม**:
  - คอลัมน์ **สถานะ** โชว์ป้าย **🔴 LIVE** (แดง กระพริบ) หรือ **Offline** ต่อสตรีม เพื่อให้นักเรียนรู้
    ว่าอันไหนกำลังถ่ายทอด แล้วเลือกคลิกเข้าดูเอง — คอลัมน์นี้แสดงเฉพาะเมื่อแอดมินตั้ง media server แล้ว
  - คลิกชื่อสตรีม = เข้าหน้า `view.php` (เครื่องเล่นเดียวกับที่ครูเห็น) → นี่คือการ "แชร์หน้าสตรีมของครูให้นักเรียนดู"
- การเปิดดู "รายการ" **ไม่นับเป็นการเข้าเรียน** (ไม่กระทบ attendance) — จะถูกนับก็ต่อเมื่อเข้าไปดูสตรีมจริงในหน้า `view.php`

### 17.11 แก้ปัญหาที่พบบ่อย (Quick troubleshooting)

| อาการ | สาเหตุ | วิธีแก้ |
|---|---|---|
| player ขึ้น Offline ตลอด ทั้งที่สตรีมอยู่ | HLS base URL ผิด / Moodle เข้าถึง media server ไม่ได้ | ตรวจว่า Moodle server เรียก `<hlsbaseurl>/<key>/index.m3u8` ได้ |
| หน้ารายการ "Stream" ไม่มีคอลัมน์สถานะ | ยังไม่ได้ตั้ง `rtmpserver`/`hlsbaseurl` | กรอกค่า media server ในหน้าตั้งค่าปลั๊กอิน (17.2) |
| ไม่เห็นเมนู "Stream" ในรายวิชา | รายวิชานั้นยังไม่มีกิจกรรม Live stream หรือผู้ใช้ไม่มีสิทธิ์ `view` | สร้างกิจกรรมอย่างน้อย 1 ตัว / ตรวจสิทธิ์ |
| นักเรียนเห็นจอดำ (Moodle เป็น HTTPS) | mixed content — HLS/playback เป็น HTTP | ใช้ Caddy ตั้ง HLS/Playback เป็นโดเมนย่อย HTTPS 2 ตัว |
| Zoom mode เด้งเป็นปุ่ม Join แทนที่จะฝัง | ยังไม่ตั้ง RTMP/HLS หรือ Zoom ไม่มีสิทธิ์ live streaming | ตั้ง media server + เปิด Custom Live Streaming + เพิ่ม scope |
| กด "Live on Custom Live Streaming Service" แล้วภาพไม่เข้า | RTMP URL เป็น IP วง LAN — Zoom cloud ยิงไม่ถึง | ทำให้ RTMP เข้าถึงได้จากอินเทอร์เน็ต |
| ไม่มีปุ่มดูย้อนหลัง | ยังไม่ตั้ง Recording playback URL หรือคลิปยังไม่ finalize | ตั้ง playback URL / รอจนสตรีมจบสนิท |

### 17.12 เอกสารที่เกี่ยวข้อง

- [`docs/usage-th.md`](docs/usage-th.md) — คู่มือใช้งานฉบับย่อ + ค่าตัวอย่างของเครื่อง dev
- [`README.md`](README.md) — คู่มือติดตั้ง + production notes
- หัวข้อ 1–16 ด้านบน — คำอธิบายโค้ด/สถาปัตยกรรมของทุกไฟล์

---

## 18. คู่มือตั้งค่า OBS → Moodle แบบ step by step

หัวข้อนี้สอนวิธี **สตรีมจาก OBS ให้ขึ้นในหน้ากิจกรรม Moodle** ตั้งแต่ศูนย์ อ้างอิง flow จริงของ
โหมด OBS (ดูโค้ดที่เกี่ยวข้องได้ที่ §3.3 `view.php`, §4.1 `get_stream_status`, §13.1 flow)

**ภาพรวม 1 บรรทัด**
```
OBS ── RTMP :1935 ──▶ media server (MediaMTX) ── HLS :8888 ──▶ เครื่องเล่นในหน้ากิจกรรม Moodle
```
ครูสตรีมจาก OBS → ปลั๊กอินตรวจเจอว่าไลฟ์ (poll `get_stream_status` ทุก 10 วิ) → เครื่องเล่นในหน้า
Moodle เด้งเป็น 🔴 LIVE เองภายใน ~10 วินาที

### ✅ ก่อนเริ่ม เช็ค 2 อย่าง
1. **แอดมินตั้งค่า media server แล้ว** — *Site administration → Plugins → Activity modules → Live
   stream* ต้องมี **RTMP server URL** และ **HLS base URL** (ถ้าว่าง ฟอร์มสร้างกิจกรรม OBS จะไม่ให้บันทึก — ดู §3.4 `validation()`)
2. **คุณเป็นครู** (มีสิทธิ์ `mod/livestream:addinstance`)

### ขั้นที่ 1 — สร้างกิจกรรม Live stream (โหมด OBS)
1. เข้ารายวิชา → เปิด **Edit mode** → **Add an activity or resource** → **Live stream**
2. ตั้งค่า: **Name** = ชื่อคาบ, **Stream type** = **OBS / media server**, (ไม่บังคับ) **Scheduled start**
3. กด **Save and display**

### ขั้นที่ 2 — คัดลอก Server URL + Stream key จากหน้ากิจกรรม
เปิดกิจกรรมในฐานะครู จะเห็น **กล่อง OBS setup** (นักเรียนไม่เห็น) มี 2 ค่า:

| ช่อง | ตัวอย่าง (เครื่อง dev) |
|---|---|
| **Server URL** | `rtmp://10.0.150.190:1935` |
| **Stream key** | `ef7873b3dfe22fb8b47341fab7bd860c` (สุ่มไม่ซ้ำต่อกิจกรรม) |

> 🔑 Stream key เป็นรหัสลับของสตรีมนี้ — อย่าโพสต์สาธารณะ (ใครมี key ก็สตรีมแทนได้)

### ขั้นที่ 3 — ติดตั้ง OBS (ถ้ายังไม่มี)
โหลดฟรีจาก **obsproject.com** (Windows / macOS / Linux) → ติดตั้ง → เปิดโปรแกรม

### ขั้นที่ 4 — ใส่ค่าสตรีมใน OBS  ⭐ หัวใจสำคัญ
**Settings → Stream**
- **Service:** เลือก **Custom...**
- **Server:** วาง Server URL จากขั้นที่ 2 (เช่น `rtmp://10.0.150.190:1935`)
- **Stream Key:** วาง Stream key จากขั้นที่ 2
- กด **Apply**

> ⚠️ Server กับ Key อยู่คนละช่อง — **อย่ารวม key ต่อท้าย URL**

### ขั้นที่ 5 — ตั้ง encoder ให้ไลฟ์ลื่น (สำคัญเรื่อง latency)
**Settings → Output → Output Mode = Advanced** แล้วแท็บ **Streaming**:

| ตั้งค่า | แนะนำ | ทำไม |
|---|---|---|
| **Keyframe Interval** | **2 วินาที** (หรือ 1) | ⭐ สำคัญสุด — MediaMTX ตัด HLS segment ตาม keyframe ถ้าปล่อย 0 (auto) จะ latency สูง/เริ่มเล่นช้า |
| Rate Control | **CBR** | บิตเรตคงที่ ภาพนิ่ง |
| Bitrate | 720p ≈ **2500–4000 Kbps** / 1080p ≈ 4500–6000 | ตามความเร็ว upload |
| Encoder | x264 (หรือ NVENC/QSV ถ้าการ์ดจอรองรับ) | ลดโหลด CPU |
| Audio Bitrate | 128–160 Kbps | — |

**Settings → Video:** Output Resolution 1280×720 (หรือ 1920×1080), FPS 25 หรือ 30

> ถ้าใช้ Output Mode = **Simple** จะตั้ง Keyframe Interval ตรงๆ ไม่ได้ — แนะนำใช้ **Advanced**

### ขั้นที่ 6 — ใส่สิ่งที่จะออกอากาศ (Sources)
ในกล่อง **Sources** (ล่างซ้าย) กด **+**: **Display Capture** (ทั้งจอ) / **Window Capture**
(เฉพาะหน้าต่าง เช่น สไลด์) / **Video Capture Device** (กล้อง) / **Audio Input Capture** (ไมค์) —
ใส่ซ้อนกันได้ จัดตำแหน่งในพื้นที่ preview

### ขั้นที่ 7 — เริ่มสตรีม
กด **Start Streaming** (ขวาล่าง) → มุมล่าง OBS ขึ้นสถานะเขียว + bitrate เดินอยู่ = ภาพส่งเข้า media server แล้ว

### ขั้นที่ 8 — กลับไปดูใน Moodle
กลับไปหน้ากิจกรรม (หรือให้นักเรียนเปิด):
- ป้ายเปลี่ยน **Offline → 🔴 LIVE** เองภายใน ~10 วินาที (ไม่ต้อง refresh) และวิดีโอเล่นในหน้าเลย
- นักเรียนเข้าได้ 2 ทาง: คลิกกิจกรรมตรงๆ **หรือ** เมนู **"Stream" → เลือกสตรีมที่ LIVE** (ดู §17.10)
- ระบบเริ่มนับ **เช็กชื่อ** อัตโนมัติ (ครูดู/โหลด CSV ที่ลิงก์ Attendance — ดู §17.8)

### ขั้นที่ 9 — จบคาบ
กด **Stop Streaming** ใน OBS → ปลั๊กอินปิด session + ลบแชท + **ปุ่ม "ดูย้อนหลัง"** โผล่ให้นักเรียนเอง
(ถ้าแอดมินตั้ง Recording playback URL ไว้)

### 🔧 แก้ปัญหาเร็ว
| อาการ | แก้ |
|---|---|
| OBS แดง / bitrate = 0 | Server/Key ผิด หรือพอร์ต 1935 ถูกบล็อก — ตรวจ 2 ค่าจากขั้นที่ 2 อีกครั้ง |
| OBS เขียวแล้วแต่ Moodle ยัง Offline | รอ ~10 วิ / refresh 1 ครั้ง; ถ้ายัง = Moodle เข้าถึง `<HLS base URL>/<key>/index.m3u8` ไม่ได้ (แอดมินเช็ก HLS base URL) |
| เล่นแล้วกระตุก/เริ่มช้า | ตั้ง **Keyframe Interval = 2** (ขั้นที่ 5) แล้วสตรีมใหม่ |
| นักเรียนจอดำ (Moodle เป็น HTTPS) | HLS ต้องเป็น HTTPS ด้วย — แอดมินรัน media server ผ่าน Caddy (ดู §12.2) |

### 💡 เทสได้โดยไม่มี OBS (ใช้ ffmpeg ยิง test pattern)
```bash
ffmpeg -re -f lavfi -i testsrc=size=1280x720:rate=25 -f lavfi -i sine=frequency=440 \
  -c:v libx264 -preset ultrafast -tune zerolatency -g 50 -c:a aac \
  -f flv rtmp://10.0.150.190:1935/<stream-key>
```
`-g 50` = keyframe ทุก 50 เฟรมที่ 25fps (= 2 วินาที) เทียบเท่า Keyframe Interval = 2 ใน OBS
