# Telemedicine (WebRTC video consultation)

1:1 doctor↔patient video calls, tied to an existing `appointments` row. Video/audio goes **peer-to-peer** (WebRTC) — the PHP server only relays signaling messages (offer/answer/ICE) and chat, so it stays lightweight even under load.

## How it fits into the existing app

- No new appointment table. It reuses columns that already existed on `appointments` but were unused: `meeting_provider`, `meeting_link`, `meeting_event_id` (room token), `meeting_status` (`not_created → created → started → completed/cancelled`), `meeting_created_at`, `meeting_started_at`, `meeting_completed_at`.
- One new table: `telemedicine_chat_messages` (in-call chat history). Migration: `database/migration_telemedicine.sql` — already applied to the local dev DB; run it on any other environment before first use.
- "Join Video Call" buttons were added to `doctor/appointments.php` and `user/my-doctor-appointments.php`, shown only when `appointment_type = 'online'` and `status = 'approved'`.

## Pieces

| File | Role |
|---|---|
| `signaling-server.php` | **Run this from the CLI.** Long-running WebSocket process (Ratchet). Not a normal web-request PHP file. |
| `SignalingServer.php` | The actual signaling logic: room membership, offer/answer/ICE relay, chat relay + persistence, waiting-room presence, call lifecycle → DB. |
| `join.php` | Web page a doctor/patient hits from their appointments list. Verifies they own the appointment, creates the room token on first visit, issues a short-lived signed "join ticket," redirects to `room.php`. |
| `room.php` | The actual call UI (video tiles, mute/camera, chat, end call). Trusts only the signed ticket from `join.php` — never re-touches PHP sessions. |
| `assets/js/room.js` | `getUserMedia` + `RTCPeerConnection` + the WebSocket client. |
| `api/end-session.php` | Fallback HTTP endpoint hit via `sendBeacon` on tab close, in case the WebSocket close event doesn't reach the server in time. |
| `config.php` | Shared constants (WS host/port, ICE servers, ticket signing secret — reuses `JWT_SECRET`). |

## Running it locally

```bash
composer require cboden/ratchet   # already installed
php telemedicine/signaling-server.php
```

It binds to `TELEMED_WS_BIND_HOST:TELEMED_WS_PORT` from `.env` (default `0.0.0.0:8090`). The browser connects to `TELEMED_WS_SCHEME://TELEMED_WS_HOST:TELEMED_WS_PORT` — update `TELEMED_WS_HOST` in `.env` to whatever hostname/IP the browser can actually reach (not `0.0.0.0`).

## ⚠️ Production hosting — read before deploying

Your `.env` points at Hostinger shared cPanel hosting. **Typical shared hosting cannot run this signaling server** — it has no support for long-running background processes or binding to a custom port. Two options:

1. **Get a small VPS** (even the cheapest tier works — this process is very light, it only relays JSON text) and run `signaling-server.php` there under a process supervisor (`systemd`, `supervisord`, or `pm2` via `pm2 start signaling-server.php --interpreter php`), fronted by nginx doing a `wss://` reverse proxy to the PHP process on `127.0.0.1:8090` so it can share your existing domain/SSL cert.
2. **Ask your host** if they support a persistent Node/PHP process on a custom port (some "Business"/VPS-tier shared hosting does via Cloud Panel or similar — cPanel alone usually doesn't).

The rest of the app (the PHP web pages, MySQL, appointment booking) is unaffected either way — only the signaling server needs this different kind of hosting.

## What's intentionally out of scope (v1)

- **TURN server** — only public STUN is configured. Most direct connections work fine with STUN alone, but calls from behind strict corporate firewalls / some mobile carriers can fail to connect without a TURN relay. Add one later (e.g. self-hosted `coturn`) by extending `TELEMED_ICE_SERVERS` in `config.php`.
- **Recording.**
- **Group calls** — this is strictly 1 doctor + 1 patient per appointment.
- **Screen share** — not wired up, though `room.js`'s `RTCPeerConnection` setup would support adding it later via `getDisplayMedia`.
