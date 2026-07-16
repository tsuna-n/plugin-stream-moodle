# คู่มือการใช้งาน mod_livestream (ภาษาไทย)

ปลั๊กอินถ่ายทอดสดสำหรับ Moodle รองรับ 3 อย่าง:

| ฟีเจอร์ | ครูทำอะไร | นักเรียนเห็นอะไร |
|---|---|---|
| **OBS mode** | สตรีมจาก OBS Studio → media server | เครื่องเล่นฝังในหน้ากิจกรรม |
| **Zoom mode (ฝัง)** | โฮสต์ประชุม Zoom แล้วให้ Zoom ส่งภาพเข้า media server | เครื่องเล่นฝังในหน้ากิจกรรม (ไม่ต้องเปิด Zoom) |
| **อัดย้อนหลัง** | — (อัตโนมัติ) | ปุ่ม "ดูย้อนหลัง" หลังสอนจบ |

**เส้นทางข้อมูล**

```
OBS / Zoom  --RTMP :1935-->  MediaMTX  --HLS :8888-->      Caddy (live.โดเมน, HTTPS) --> เครื่องเล่นใน Moodle
                                  |
                                  +--อัดลงดิสก์--> playback :9996 --> Caddy (vod.โดเมน, HTTPS) --> ปุ่มดูย้อนหลัง
```

Caddy คือ reverse proxy ที่ทำ HTTPS ให้อัตโนมัติ (ออก certificate ฟรีจาก Let's Encrypt เอง ไม่ต้องรันคำสั่ง certbot เอง) พอร์ต 8888/9996 ของ MediaMTX ไม่ถูกเปิดออกสู่อินเทอร์เน็ตโดยตรงอีกต่อไป — เข้าถึงได้ผ่าน Caddy เท่านั้น ใช้ **โดเมนย่อยแยกกัน 2 ตัว** สำหรับ HLS กับ playback (ไม่ใช่โดเมนเดียวแยก path) เพราะ MediaMTX มีขั้นตอน redirect ภายในที่ path-based proxy ทำให้พังได้

---

## ส่วนที่ 1 — แอดมินตั้งค่า (ทำครั้งเดียว)

### 1.1 รัน media server

**Production (มีโดเมนจริง)** — ใช้ docker-compose ที่มี Caddy ทำ HTTPS ให้อัตโนมัติ:

```bash
cd media-server
cp .env.example .env
# แก้ .env: HLS_DOMAIN และ PLAYBACK_DOMAIN = โดเมนย่อย 2 ตัวที่ชี้ A record มาเซิร์ฟเวอร์นี้แล้ว (คนละชื่อกัน), ACME_EMAIL=อีเมลจริง
docker compose up -d
```

เปิดพอร์ตที่ไฟร์วอลล์: `1935` (RTMP รับภาพ), `80` + `443` (Caddy/HTTPS) — ไม่ต้องเปิด `8888`/`9996` ออกสู่อินเทอร์เน็ตแล้ว เพราะ Caddy เป็นทางเข้าเดียว
คลิปถูกอัดไว้ที่ `media-server/recordings/<streamkey>/`

**Dev/ทดสอบในวง LAN (ไม่มีโดเมน)** — ข้าม Caddy ได้ โดยเอา service `caddy` ออกจาก `docker-compose.yml` แล้วเปิดพอร์ต `8888`/`9996` ตรงจาก service `mediamtx` แทน แล้วใช้ URL แบบ `http://` ธรรมดา (ดูค่าตัวอย่างเครื่อง dev ด้านล่าง)

### 1.2 ตั้งค่าปลั๊กอิน — *Site administration → Plugins → Activity modules → Live stream*

| Setting | ตัวอย่าง (production, ผ่าน Caddy) | จำเป็นสำหรับ | หมายเหตุ |
|---|---|---|---|
| RTMP server URL | `rtmp://media.example.com:1935` | OBS + Zoom | ห้ามมี path/stream key ต่อท้าย — ใช้ domain/IP อะไรก็ได้ที่ชี้มาเซิร์ฟเวอร์นี้ ไม่ต้องตรงกับ HLS_DOMAIN/PLAYBACK_DOMAIN |
| HLS base URL | `https://live.media.example.com` | OBS + Zoom | นักเรียนดูผ่าน URL นี้ — production ต้องเป็น HTTPS, ต้องเป็นคนละโดเมนกับ playback |
| Recording playback URL | `https://vod.media.example.com` | ดูย้อนหลัง | เว้นว่าง = ไม่โชว์ปุ่มย้อนหลังอัตโนมัติ |
| hls.js URL | *(ค่าเริ่มต้น CDN)* | ทุกโหมด | ถ้าเน็ตปิด ชี้ไปไฟล์ที่โฮสต์เอง |

> 💡 **ค่าของเครื่อง dev ชุดนี้** (media server บนเครื่องเดียวกับ Moodle, ไม่ได้ใช้ Caddy เพราะไม่มีโดเมนสาธารณะในวง LAN):
> RTMP `rtmp://10.0.150.190:1935` · HLS `http://10.0.150.190:8888` · Playback `http://10.0.150.190:9996`
> (ตั้ง 3 ตัวนี้ให้แล้ว) — ใช้ได้กับ OBS ทันที แต่ Zoom relay เข้า embedded player ยังไม่ได้เพราะเป็น IP วง LAN (ดูคำเตือนส่วน 2.2); การสร้างห้องประชุม Zoom เองยังใช้ได้ปกติ

> Zoom **ไม่มี** การตั้งค่าระดับเว็บไซต์ — ไม่ต้องกรอกอะไรในหน้านี้สำหรับ Zoom เพราะครูแต่ละคนอาจมีบัญชี Zoom คนละบัญชีกัน จึงให้ครูแต่ละคนตั้งค่าบัญชี Zoom ของตัวเอง (ดูส่วน 2.2)

---

## ส่วนที่ 2 — ครูใช้งาน (ทุกครั้งที่สอน)

### 2.1 โหมด OBS

| ขั้น | ทำอะไร |
|---|---|
| 1 | ในรายวิชา *Add an activity* → **Live stream** → **Stream type = OBS / media server** → Save |
| 2 | เปิดกิจกรรม จะเห็นกล่องครูที่มี **Server URL** และ **Stream key** |
| 3 | ใน OBS: **Settings → Stream → Custom** วาง Server URL + Stream key |
| 4 | กด **Start Streaming** → player ในหน้า Moodle เล่นเองภายในไม่กี่วินาที |

### 2.2 โหมด Zoom (ฝังในหน้า Moodle)

**ครูแต่ละคนต้องเชื่อมบัญชี Zoom ของตัวเองก่อนใช้ครั้งแรก** (ทำครั้งเดียว) เพราะครูแต่ละคนอาจมีบัญชี/องค์กร Zoom แยกกัน ปลั๊กอินจึงไม่มีบัญชี Zoom กลางให้ทุกคนใช้ร่วมกัน:

| ขั้น | ทำอะไร |
|---|---|
| 1 | [marketplace.zoom.us](https://marketplace.zoom.us) → **Develop → Build App → Server-to-Server OAuth** (สร้างในบัญชี Zoom ของตัวเอง) |
| 2 | ใส่ Scopes: `meeting:write:meeting` (+ `:admin` ถ้า account-level) และ scope live streaming ของ endpoint `/livestream` (`meeting:update:livestream` หรือ `meeting:write:admin`) |
| 3 | **Activate** แล้วในรายวิชาใดก็ได้ใน Moodle ไปที่เมนูนำทาง → **"จัดการบัญชี Zoom ของฉัน"** แล้ววาง Account ID / Client ID / Client Secret |
| 4 | เปิดฟีเจอร์ในบัญชี Zoom: **Settings → In Meeting (Advanced) → Allow live streaming of meetings → ✅ Custom Live Streaming Service** |

> ถ้าข้ามขั้น 4 หรือ scope ไม่ครบ กิจกรรมยังบันทึกได้ แต่จะ fallback เป็นปุ่ม "Join Zoom" ธรรมดา (ปลั๊กอินจะเตือนเหตุผล) ถ้ายังไม่เชื่อมบัญชี Zoom เลย ระบบจะไม่ให้บันทึกกิจกรรมแบบ Zoom (ฟอร์มจะแจ้งพร้อมลิงก์ไปหน้าเชื่อมบัญชี)

หลังเชื่อมบัญชีแล้ว สร้างกิจกรรมและออกอากาศตามปกติ:

| ขั้น | ครูทำ | เบื้องหลัง |
|---|---|---|
| 1 | *Add an activity* → **Live stream** → **Stream type = Zoom meeting** → Save | ปลั๊กอินสร้างห้อง Zoom ภายใต้บัญชี Zoom ของครูคนนั้น + ตั้ง custom live stream ชี้ RTMP+streamkey อัตโนมัติ |
| 2 | เปิดกิจกรรม → **Start meeting (host)** | เปิด Zoom ในฐานะโฮสต์ |
| 3 | ในโปรแกรม Zoom: **More (…) → Live on Custom Live Streaming Service** | Zoom เริ่มยิงภาพเข้า media server |
| 4 | สอน | player ในหน้า Moodle ขึ้น 🔴 LIVE เล่นเอง |
| 5 | ปิด live / ปิดประชุม | คลิปถูกอัด → ปุ่มดูย้อนหลังโผล่ให้นักเรียน |

> ⚠️ **สำคัญ:** Zoom ยิงภาพจาก **cloud ของ Zoom** ดังนั้น `RTMP server URL` ต้องเป็น address ที่ **เข้าถึงได้จากอินเทอร์เน็ต** — IP วง LAN เช่น `10.0.150.190` ใช้กับ Zoom ไม่ได้ ต้อง deploy media server แบบ public ก่อน (ต่างจาก OBS ที่รันบนเครื่องครูในวงเดียวกันได้เลย)
>
> latency โหมด Zoom สูงกว่า OBS (~20–30 วินาที เพราะผ่าน cloud Zoom)

---

## ส่วนที่ 3 — นักเรียน

เปิดกิจกรรมอย่างเดียว เห็น *Offline* จนครูเริ่ม แล้ววิดีโอเล่นเองในหน้า (ระบบตรวจสถานะทุก 10 วินาที) — **ไม่ต้องเปิด Zoom / ไม่ต้องมีบัญชี Zoom** (ยกเว้นกรณี fallback เป็น Join Zoom ธรรมดา)

## ส่วนที่ 4 — ดูย้อนหลัง

ทุกสตรีม (OBS หรือ Zoom) ถูกอัดอัตโนมัติ เมื่อจบและตั้งค่า *Recording playback URL* ไว้ ปุ่ม **ดูย้อนหลัง** จะปรากฏเอง หรือครูวาง **Recording URL** เองในหน้าตั้งค่ากิจกรรมเพื่อ override ก็ได้

---

## ส่วนที่ 5 — แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุ | วิธีแก้ |
|---|---|---|
| player ขึ้น Offline ตลอด ทั้งที่สตรีมอยู่ | HLS base URL ผิด / Moodle เข้าถึง media server ไม่ได้ | ตรวจว่า Moodle server เรียก `<hlsbaseurl>/<key>/index.m3u8` ได้ |
| นักเรียนเห็นจอดำ / โหลดไม่ขึ้น (Moodle เป็น HTTPS) | mixed content — HLS/playback เป็น HTTP | รัน docker-compose แบบมี Caddy (ส่วนที่ 1.1) แล้วตั้ง HLS/Playback URL เป็นโดเมนย่อย HTTPS 2 ตัว เช่น `https://live.โดเมน` และ `https://vod.โดเมน` |
| Zoom mode เด้งเป็นปุ่ม Join แทนที่จะฝัง | ยังไม่ได้ตั้ง RTMP/HLS หรือ Zoom ไม่มีสิทธิ์ live streaming | ตั้งค่า media server + เปิด Custom Live Streaming + เพิ่ม scope |
| กด "Live on Custom Live Streaming Service" แล้วภาพไม่เข้า | RTMP URL เป็น IP ในวง LAN — Zoom cloud ยิงไม่ถึง | ทำให้ RTMP เข้าถึงได้จากอินเทอร์เน็ต (public/cloud) |
| ไม่มีปุ่มดูย้อนหลัง | ยังไม่ได้ตั้ง Recording playback URL หรือคลิปยังไม่ถูก finalize | ตั้งค่า playback URL / รอจนสตรีมจบสนิท |

---
ดูรายละเอียดการติดตั้งและ production notes เพิ่มเติมได้ที่ [README.md](../README.md)
