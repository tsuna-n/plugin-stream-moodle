# CLAUDE.md — mod_livestream "Full Streaming" (real-time push) build spec

This file is the **canonical brief** for the next agent (Sonnet) implementing the
"full streaming" feature in this repo. It captures the current architecture, the
target design, the exact contracts, and a staged plan written so the work lands
**without regressions**. Read this whole file before writing any code, then follow
the phases in order. Do not improvise around the "Guardrails" section — every item
there is a known trap in this codebase or in Moodle/PHP generally.

> Language note: this repo's code, comments, and docs are all in **English**; keep
> new code/comments/strings English too (a Thai `lang/th` mirror is required for
> user-facing strings — see Guardrails). Talk to the human in Thai; write the
> artifact in English.

---

## 1. What "full streaming" means here (scope)

Today the plugin is **entirely polling-based**. Three browser surfaces each run
their own `setTimeout` poll loop against a Moodle AJAX web service:

| Surface | AMD module | Web service | Interval |
|---|---|---|---|
| Activity player live/offline status | `amd/src/player.js` | `mod_livestream_get_stream_status` | **10 s** |
| Ephemeral live chat | `amd/src/chat.js` | `mod_livestream_get_chat_messages` | **3 s** |
| Course-nav "Live now" badge | `amd/src/navbadge.js` | `mod_livestream_get_course_live_status` | **20 s** |

Each status poll also does a **server-side curl probe** of the MediaMTX HLS
manifest, and doubles as the **attendance heartbeat** (`attendance::touch()` runs
on every `get_stream_status` call). So "polling" is load in three places at once:
browser → Moodle → media server, once per viewer per interval.

**"Full streaming, every platform" = replace all three poll loops with one
real-time push channel (SSE)**, so status/chat/badge update instantly, on every
browser platform (desktop, Android Chrome, iOS Safari), with **no client polling**
— while keeping attendance, sessions, and the recording flow working exactly as
they do now, and **degrading gracefully to today's polling** on any install that
does not deploy the new realtime service.

Non-goals: changing the video transport (HLS stays; low-latency HLS ~3–8 s is
fine), the Moodle Mobile app native views, or Zoom's own in-Zoom chat.

---

## 2. The graceful-fallback principle (do not break existing installs)

This plugin already follows one consistent idiom: **a feature lights up only when
its backing service is configured, otherwise it degrades quietly.** No media
server → plain "Join Zoom" button. No `playbackbaseurl` → no recording link. You
**must** extend the same idiom:

- New admin setting `realtimeurl` **empty** ⇒ everything behaves exactly as today
  (the three poll loops run unchanged). Zero behavior change, zero risk for
  current deployments.
- `realtimeurl` **set** ⇒ the browser opens one SSE connection instead of polling;
  polling is the automatic fallback if the SSE connection cannot be established or
  drops and cannot recover.

Every AMD module keeps its existing poll code as the fallback path. You are
**adding** a push path in front of it, not deleting the poll path.

---

## 3. Target architecture

The core constraint that dictates this whole design:

> **Never hold a PHP request open for streaming.** PHP is one-process-per-request
> (php-fpm/apache-prefork). An SSE endpoint served *by Moodle* pins one PHP worker
> per connected student for the entire lesson — a class of 40 exhausts the pool
> and takes the **whole Moodle site** down, not just this plugin. Long-polling in
> PHP has the same failure mode. **The persistent connections must live in an
> event-loop service, never in Moodle.**

So we add one small **realtime gateway** (Node, event-loop, holds thousands of
idle SSE connections cheaply) next to MediaMTX in the existing
`media-server/docker-compose.yml`. **Moodle keeps all business logic** (auth,
capabilities, DB, sessions, attendance, chat storage); the gateway is a **dumb,
stateless-ish fan-out relay** with no database.

```mermaid
flowchart LR
  subgraph Browser
    P[player.js SSE]
    C[chat.js SSE]
    N[navbadge.js SSE]
  end
  subgraph Moodle[Moodle PHP]
    WS[external WS:\nget_realtime_token\nsend/delete chat]
    SE[streamevent.php\n(secret)]
    PR[presence.php\n(secret)]
    RC[resolve_course.php\n(secret)]
    DB[(livestream_*\ntables)]
  end
  subgraph MS[media-server docker network]
    GW[realtime gateway\nNode SSE + rooms]
    MTX[MediaMTX\nRTMP->HLS]
    CADDY[Caddy TLS]
  end

  P & C & N -- "1. GET token (Bearer)" --> WS
  P & C & N -- "2. EventSource ?token=" --> CADDY --> GW
  GW -- "probe HLS (per active stream)" --> MTX
  GW -- "transition -> streamevent (secret)" --> SE
  SE -- "open/close session, purge chat" --> DB
  SE -- "publish status->cm room, badge->course room" --> GW
  WS -- "send/delete chat -> publish to cm room (secret)" --> GW
  GW -- "periodic room roster -> presence (secret)" --> PR --> DB
  GW -- "resolve course streamkeys (secret)" --> RC --> DB
```

### Who does what

- **Browser (AMD)** holds exactly **one** `EventSource` per surface. It never
  polls when SSE is healthy. Sending a chat message stays a normal Moodle WS
  `POST` (`send_chat_message`) — SSE is receive-only, which is why SSE (not
  WebSocket) is the right transport.
- **Gateway (Node)**:
  - Terminates SSE, groups connections into **rooms**: `cm-<cmid>` (status +
    chat for one activity) and `course-<courseid>` (badge).
  - **Owns liveness detection**: for each stream that currently has ≥1 subscriber
    it probes `http://mediamtx:8888/<streamkey>/index.m3u8` on the internal docker
    network every ~3 s — **one probe per stream, not per viewer** (this is the
    scalability win over today's per-viewer probe). On a live↔offline transition it
    notifies Moodle.
  - Accepts authenticated **publishes** from Moodle (chat, enriched status/badge)
    and fans them out to the room.
  - Periodically posts each room's **presence roster** (the user ids currently
    connected) to Moodle for attendance.
  - Never touches a database, never holds Moodle secrets other than one shared
    HMAC secret.
- **Moodle** stays authoritative:
  - Mints short-lived **HMAC tokens** scoping a browser to one room (`get_realtime_token`).
  - On a gateway "transition" callback (`streamevent.php`), opens/closes the
    `livestream_session`, purges chat on offline (**exactly today's `attendance::touch`
    offline logic**), then publishes the `status` event to `cm-<cmid>` and `badge`
    to `course-<courseid>`.
  - On chat send/delete, publishes to `cm-<cmid>`.
  - On a gateway presence roster (`presence.php`), upserts `livestream_attendance`
    for the open session (**replacing the per-poll heartbeat** with real presence).

### Why the gateway probes instead of MediaMTX push hooks

MediaMTX has `runOnReady`/`runOnNotReady` hooks, but the shipped
`bluenviron/mediamtx` image is **scratch-based — no shell, no curl** — so a hook
`curl ...` silently does nothing. Rather than fight the image, the gateway does
the probe itself (it is already an event-loop service on the same docker network
and can reach `mediamtx:8888` directly). MediaMTX hooks are documented as an
**optional** latency optimization for operators who run an image with curl — not
required, not in the critical path. Do **not** make the feature depend on them.

---

## 4. Exact contracts (implement both sides identically)

These are the interfaces where a mismatch = a silent bug. Specify nothing loosely.

### 4.1 Token (`mod_livestream_get_realtime_token`)

New `read` web service, `ajax => true`, capability `mod/livestream:view`.
Params: `{cmid?: int, courseid?: int}` (exactly one non-zero). Returns
`{token: string, url: string, room: string, expires: int}` where `url` is the
admin `realtimeurl`, `room` is `cm-<cmid>` or `course-<courseid>`.

Token format — a minimal signed blob (implement identically in PHP and Node):

```
payload  = {"uid": <int>, "room": "<room>", "sk": "<streamkey|''>",
            "cid": <courseid|0>, "cm": <cmid|0>, "mod": <0|1 canmoderate>,
            "exp": <unixtime + 60>}
b64      = base64url(json_encode(payload))            // no padding
sig      = hex( hmac_sha256(realtimesecret, b64) )
token    = b64 + "." + sig
```

- PHP: `hash_hmac('sha256', $b64, $secret)`, base64url = `rtrim(strtr(base64_encode($j),'+/','-_'),'=')`.
- Node: `crypto.createHmac('sha256', secret).update(b64).digest('hex')`.
- Gateway verification: recompute sig (constant-time compare), reject if `exp < now`.
  Token is validated **only at SSE connect**; the connection then lives until
  closed. On reconnect the browser fetches a fresh token first.
- `sk` (streamkey) is included for `cm-` rooms so the gateway knows what to probe.
  For `course-` rooms the gateway calls `resolve_course.php` (4.5) to get the
  `{streamkey, cmid}` list. Putting the streamkey in the token is consistent with
  the existing model where the streamkey is already effectively viewer-visible in
  the HLS URL, and everything is over HTTPS.

### 4.2 SSE stream (gateway → browser)

`GET {realtimeurl}/sse?token=<token>` → `Content-Type: text/event-stream`.
`EventSource` cannot set headers, so the token rides as a query param (short TTL,
HTTPS — same security posture as the streamkey-in-URL model). Event types:

```
event: status   data: {"live": true|false}                       // cm room
event: chat     data: {"id":N,"userid":N,"username":"…","message":"…","timecreated":N}
event: chatdelete data: {"id": N}
event: badge    data: {"live": true|false, "cmid": N}            // course room
event: ping     data: {}                                          // ~20s keep-alive/heartbeat
```

Send an initial `status`/`badge` snapshot on connect (from the gateway's current
probe state) so a late joiner is correct immediately without waiting for a
transition. `ping` every ~20 s keeps intermediaries from closing idle connections
and lets the client detect a dead link.

### 4.3 Moodle ← gateway callbacks (server-to-server, shared secret)

All authenticated by header `X-Livestream-Secret: <realtimesecret>` (reject
otherwise with 403). These are plain plugin PHP endpoints (e.g.
`mod/livestream/streamevent.php`) that **must** run *without* a Moodle login
session — call `define('NO_MOODLE_COOKIES', true)` and validate purely by the
secret. Never expose them to the internet without the secret check.

- `POST streamevent.php` body `{streamkey, live: bool}` — gateway reports a
  probed transition. Moodle: resolve streamkey → instance/cmid/courseid; run the
  **same session open/close + chat purge** as `attendance::touch()` does for the
  live/offline edges; then publish `status` to `cm-<cmid>` and `badge` to
  `course-<courseid>` (4.4). Idempotent (a repeated same-state report is a no-op).
- `POST presence.php` body `{rooms: {"cm-<cmid>": [uid,uid,…], …}}` — gateway's
  periodic roster (every ~30 s). Moodle upserts `livestream_attendance`
  (first/last seen) for each uid against that activity's **open** session, reusing
  the existing `attendance` upsert logic. Skip guests/managers exactly as today.
- `GET resolve_course.php?courseid=N` → `{streams:[{streamkey,cmid}, …]}` — the
  embeddable (OBS, or Zoom-with-meeting) streams in the course, so the gateway can
  probe them for a `course-` room. Reuse the filter from `get_course_live_status`.

### 4.4 Moodle → gateway publish

`POST {realtimeurl}/publish` with `X-Livestream-Secret`, body
`{room, event, data}`. Gateway fans `data` out to every SSE client in `room` as
`event: <event>`. Used by `streamevent.php` (status/badge) and by
`send_chat_message`/`delete_chat_message` (chat/chatdelete). Publishing is
**best-effort**: wrap in try/catch, short timeout (2–3 s), never let a gateway
outage break the Moodle request (chat must still store to DB and return 200 even
if the push fails — the browser also gets it on its next reconnect snapshot).

### 4.5 Admin settings (add to `settings.php`)

- `mod_livestream/realtimeurl` (PARAM_URL, default `''`) — public base URL of the
  gateway (through Caddy, e.g. `https://rt.media.example.com`). Empty = feature off.
- `mod_livestream/realtimesecret` (`admin_setting_configpasswordunmask`, default
  `''`) — shared HMAC secret; also passed to the gateway via its env.

Follow the existing settings.php comment style (empty default = "not configured").

---

## 5. Repo conventions you MUST follow (Moodle idioms)

1. **External API classes**: `classes/external/<name>.php`, namespace
   `mod_livestream\external`, extend `core_external\external_api`, implement
   `execute_parameters()/execute()/execute_returns()`. Inside `execute()`:
   `get_course_and_cm_from_cmid()` (or `context_course::instance`),
   `self::validate_context($context)`, `require_capability(...)`. Copy the shape of
   `get_stream_status.php` verbatim.
2. **Register every new WS** in `db/services.php` with `'ajax' => true` and the
   right `type`/`capabilities`. A new/edited service is only picked up after the
   **version bump** (next item) triggers a service reload.
3. **Bump `version.php`** on any DB/services/capability/lang change: raise
   `$plugin->version` (use the current date, e.g. `2026072000`) and
   `$plugin->release` (e.g. `'1.4.0'`). Nothing installs without this.
4. **AMD build is mandatory.** Moodle serves `amd/build/*.min.js`, **not**
   `amd/src`. Editing only `amd/src` changes nothing in the browser. After editing
   any `amd/src/*.js` you must regenerate the matching `amd/build/*.min.js` **and**
   `.map` and commit them (they are committed in this repo — `git ls-files
   amd/build` shows 6 files today). Build options, in order of preference:
   - Moodle grunt from the Moodle checkout the plugin is deployed in:
     `cd /home/tsuna/project/moodle && npx grunt amd --root=public/mod/livestream`
     (requires `npm ci` once in the Moodle root).
   - Fallback used previously in this repo: `terser` with an AMD-safe config that
     preserves the `define([...], function(){…})` wrapper and emits a source map
     (`--source-map "url='<name>.min.js.map',includeSources=true"`), prepended with
     the standard Moodle license header comment. Verify behavioral equivalence
     (the prior session kept an `equivalence_test.js`) before committing.
   - Either way: **`node --check amd/build/<name>.min.js`** must pass, and the
     built file must still be a single `define(...)` call.
5. **Lang strings**: add every user-facing string to `lang/en/livestream.php`
   **and** mirror it in `lang/th/livestream.php`. Both files exist and are kept in
   sync. New setting strings need `settingrealtimeurl` / `_desc`, etc.
6. **SSRF**: any server-side `curl` to the media server (you should not need new
   ones, but if you do) uses `new \curl(['ignoresecurity' => true])` — the media
   server runs on non-allowlisted ports (8888/9996). See `mediamtx_client.php` for
   the exact, commented pattern. The **gateway's** probe is Node, not subject to
   Moodle SSRF rules.
7. **Secret-only endpoints** (`streamevent.php`, `presence.php`,
   `resolve_course.php`): `define('NO_MOODLE_COOKIES', true)` before
   `require(config.php)`, then reject unless `X-Livestream-Secret` matches
   `get_config('mod_livestream','realtimesecret')` with `hash_equals()`. No
   `require_login`. Return JSON.
8. **Privacy**: the design stores **no new personal data** — tokens are ephemeral,
   presence writes to the **existing** `livestream_attendance` table, chat stays
   ephemeral. So `classes/privacy/provider.php` needs **no change**. Do not add a
   metadata entry for tokens/SSE. If you find yourself persisting anything new,
   stop and reconsider.
9. **Capabilities** are unchanged: `view` (status/badge), `chat` (chat). The token
   endpoint enforces `view`; the chat room still requires `chat` (encode
   `canchat`/`mod` in the token and let the gateway gate chat delivery, or simply
   only hand out a chat-capable token when the caller has `chat`).

---

## 6. Staged plan (each phase compiles, installs, and is testable on its own)

Work top-to-bottom. After each phase: bump version, redeploy to the dev instance
(§7), click through, and confirm **no regression with `realtimeurl` empty** before
moving on.

**Phase 0 — Groundwork (no behavior change).**
- Add `realtimeurl` + `realtimesecret` settings and their en/th strings.
- Add a tiny `classes/local/realtime.php` helper: `token(...)`,
  `publish($room,$event,$data)` (best-effort POST to `{realtimeurl}/publish`),
  `enabled()` (returns `realtimeurl !== ''`). Unit-test the token sign/verify
  round-trip against a known vector.
- Verify: settings page shows the fields; nothing else changes.

**Phase 1 — Gateway service (standalone).**
- New `realtime/` dir: `server.js` (Node, core `http`/`crypto` only, zero npm deps
  ideally), `Dockerfile` (or use `node:22-alpine` directly), `README.md`.
- Implement: `/sse` (token verify → join room → snapshot → keep-alive ping),
  `/publish` (secret → fan-out), per-stream HLS probe loop (only for streams with
  subscribers; dedupe by streamkey), transition → POST `streamevent.php`, periodic
  presence roster → POST `presence.php`, course room → `resolve_course.php`.
- Add the `realtime` service to `media-server/docker-compose.yml` on the `media`
  network; add a `{$REALTIME_DOMAIN}` block to the `Caddyfile`; add
  `REALTIME_DOMAIN` + `REALTIME_SECRET` to `.env.example`. **Caddy note**: SSE must
  not be buffered — Caddy's `reverse_proxy` streams by default, but set
  `flush_interval -1` on the realtime block to be explicit.
- Verify in isolation with `curl -N` against `/sse` and a manual `/publish`.

**Phase 2 — Moodle callback endpoints.**
- `streamevent.php`, `presence.php`, `resolve_course.php` per §4.3/§5.7. Factor the
  session open/close + chat purge out of `attendance::touch()` into reusable
  methods on `attendance` (or a new `session` helper) and call them from both the
  poll path and `streamevent.php` so the two paths stay identical.
- Verify with `curl` + the secret; watch `livestream_session`/`_attendance` rows.

**Phase 3 — Token WS + publish wiring.**
- Add `get_realtime_token` external class + register it; bump version.
- In `send_chat_message`/`delete_chat_message`, after the DB write, call
  `realtime::publish('cm-'.$cmid, 'chat'|'chatdelete', …)` (best-effort).
- Verify: authenticated token fetch returns a token the gateway accepts; sending a
  chat message fans out over SSE.

**Phase 4 — Browser: SSE with polling fallback.**
- In `view.php`/`lib.php`, pass `realtimeurl`/enabled flag into the AMD `init`
  configs (already pass `cmid`, etc.).
- In each of `player.js`, `chat.js`, `navbadge.js`: if realtime is enabled, fetch a
  token (`get_realtime_token`), open one `EventSource`, and drive the **existing**
  DOM update functions (`setLive`, `appendMessage`, badge show/hide) from SSE
  events instead of the poll. Keep the poll function intact and **start it as the
  fallback** if: realtime disabled, token fetch fails, or the `EventSource` errors
  and cannot reconnect within a grace period. On `EventSource` reconnect, re-fetch
  a token (old one expired). Chat send is unchanged.
- **Rebuild `amd/build`** for all three (Guardrail §5.4). Bump version, redeploy.
- Verify the full matrix in §7.

**Phase 5 — Attendance cutover + docs.**
- With SSE on, attendance now comes from `presence.php`. Confirm the player's SSE
  path does **not** also double-count via the old heartbeat (the poll path is off
  when SSE is healthy). The `close_stale_sessions` scheduled task stays as the
  safety net (now also covering a missed gateway transition).
- Update `README.md` (new "Realtime (full streaming)" section + settings table
  rows) and `docs/usage-th.md`. Document that `realtimeurl` empty = classic
  polling.

---

## 7. How to verify (there is a live dev instance — use it)

- **Moodle dev instance**: Docker Moodle at `http://localhost:8080` (Moodle 5.1+,
  web root under `public/`). Deploy the plugin by syncing into the container's
  mount: `rsync -a --delete livestream/ /home/tsuna/project/moodle/public/mod/livestream/`
  then run `php admin/cli/upgrade.php` (or **Site admin → Notifications**) after any
  version bump. (These exact paths/commands are already allow-listed for this repo.)
- **Media + gateway**: `cd media-server && docker compose up -d` (mediamtx, caddy,
  realtime). For local dev without Caddy/TLS you can hit the gateway on plain HTTP.
- **Drive a real stream**: publish a test pattern to a stream key with
  `ffmpeg -re -f lavfi -i testsrc -f flv rtmp://localhost:1935/<streamkey>` (ffmpeg
  is available). Start/stop it to exercise go-live/offline.
- **Acceptance matrix**:
  1. `realtimeurl` **empty** → all three surfaces still work via polling exactly as
     before (primary non-regression gate).
  2. `realtimeurl` **set** → open the activity in two browsers: player flips to LIVE
     within ~1 probe interval of `ffmpeg` starting (no 10 s wait); a chat message
     typed in one appears in the other **instantly** (well under the old 3 s); the
     course page shows the "Live now" badge; stop `ffmpeg` → player returns to
     Offline and the recording link appears.
  3. Open the teacher's **Attendance** report → the second browser's user appears
     with sensible first/last-seen; **Download CSV** works.
  4. Kill the gateway container mid-lesson → browsers fall back to polling and keep
     working; bring it back → SSE resumes on reconnect.
- Use the `/verify` and `/run` skills if helpful; do not consider a phase done on a
  green typecheck alone — exercise the actual flow in the browser.

---

## 8. Guardrails — the "do not" list (this is where errors come from)

1. **Do NOT implement SSE inside Moodle PHP.** No `while(true){ echo; flush; sleep }`
   endpoint, no PHP long-poll. That pins php-fpm workers and takes down the site.
   Persistent connections live only in the Node gateway.
2. **Do NOT delete the polling code.** It is the required fallback. You are adding a
   push path in front of it.
3. **Do NOT forget `amd/build`.** Editing `amd/src` alone ships nothing. Rebuild the
   `.min.js` + `.map`, `node --check` them, commit them.
4. **Do NOT forget the version bump** after touching db/services/lang/capabilities —
   services and schema won't reload otherwise.
5. **Do NOT block the Moodle request on the gateway.** All `publish`/token side
   effects are best-effort with a short timeout in try/catch. Chat must persist and
   return 200 even if the gateway is down.
6. **Do NOT rely on MediaMTX `runOnReady` hooks** — the shipped image has no shell.
   Gateway-side probing is the source of truth.
7. **Do NOT expose the secret endpoints without `hash_equals()` on the secret**, and
   set `NO_MOODLE_COOKIES` so they never take a session lock.
8. **Do NOT skip the Thai `lang/th` mirror** for new user-facing strings.
9. **Do NOT add new personal-data storage** (keeps `privacy/provider.php` untouched).
   Tokens are ephemeral; presence reuses `livestream_attendance`.
10. **Do NOT double-count attendance**: when SSE is healthy the old per-poll
    heartbeat must be off; presence is the single source while pushing.
11. **Keep status/session logic single-sourced**: `streamevent.php` and the legacy
    poll must call the *same* session-open/close/chat-purge helper, or the two paths
    will drift.
12. **Probe once per stream, not per viewer** in the gateway — the whole point is to
    stop O(viewers) probing.

---

## 9. File map (what you will add / touch)

New:
- `realtime/server.js`, `realtime/Dockerfile`, `realtime/README.md`
- `livestream/classes/external/get_realtime_token.php`
- `livestream/classes/local/realtime.php` (token + publish helper)
- `livestream/streamevent.php`, `livestream/presence.php`, `livestream/resolve_course.php`
- (maybe) `livestream/classes/local/session.php` if you factor session logic out

Edit:
- `livestream/settings.php` (+ `lang/en` & `lang/th` strings)
- `livestream/db/services.php` (register token WS) + `livestream/version.php` (bump)
- `livestream/classes/external/send_chat_message.php` / `delete_chat_message.php` (publish)
- `livestream/classes/local/attendance.php` (extract reusable session/presence helpers)
- `livestream/amd/src/player.js` / `chat.js` / `navbadge.js` (+ rebuilt `amd/build/*`)
- `livestream/view.php` / `lib.php` (pass realtime config to AMD init)
- `media-server/docker-compose.yml`, `media-server/Caddyfile`, `media-server/.env.example`
- `README.md`, `docs/usage-th.md`

Reference (read, don't need to change): `mediamtx_client.php`,
`get_stream_status.php`, `get_course_live_status.php`, `close_stale_sessions.php`,
`db/install.xml`, `templates/view.mustache`.
