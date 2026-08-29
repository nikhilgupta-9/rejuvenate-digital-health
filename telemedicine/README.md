# Telemedicine (WebRTC video consultation)

1:1 doctor↔patient video calls, tied to an existing `appointments` row. Video/audio goes **peer-to-peer** (WebRTC) always. The **signaling** (how the two sides exchange the offer/answer/ICE candidates needed to set up that peer-to-peer link, plus in-call chat) goes over plain **HTTP polling** — no WebSocket, no persistent background process, no custom port. This runs on ordinary shared PHP hosting (built and tested against Hostinger shared/Business hosting).

## How it fits into the existing app

- No new appointment table. It reuses columns that already existed on `appointments` but were unused: `meeting_provider`, `meeting_link`, `meeting_event_id` (room token), `meeting_status` (`not_created → created → started → completed/cancelled`), `meeting_created_at`, `meeting_started_at`, `meeting_completed_at`.
- Three tables support the signaling relay + chat history: `telemedicine_rooms` (presence heartbeat), `telemedicine_signals` (the message mailbox), `telemedicine_chat_messages` (persisted chat history). Migrations: `database/migration_telemedicine.sql` and `database/migration_telemedicine_polling.sql` — already applied to the local dev DB; run both on any other environment before first use.
- "Join Video Call" buttons were added to `doctor/appointments.php` and `user/my-doctor-appointments.php`, shown only when `appointment_type = 'online'` and `status = 'approved'`.

## Pieces

| File | Role |
|---|---|
| `join.php` | Web page a doctor/patient hits from their appointments list. Verifies they own the appointment, creates the room token on first visit, issues a short-lived signed "join ticket," redirects to `room.php`. |
| `room.php` | The actual call UI (video tiles, mute/camera, chat, end call). Trusts only the signed ticket from `join.php` — never re-touches PHP sessions. |
| `assets/js/room.js` | `getUserMedia` + `RTCPeerConnection` for the actual peer-to-peer video/audio, plus the polling client (`send()` posts a signal, a `setInterval` loop calls `poll()` every 2s). |
| `api/poll.php` | Receive side. Browser calls this every ~2s with its ticket and the last signal `id` it's seen. Marks the caller present (heartbeat), computes whether the peer is currently present, fires the one-time `ready` signal once both sides are simultaneously present (doctor is always the WebRTC offer initiator), and returns any new signals from the other side. |
| `api/send.php` | Send side. POST `{ticket, type, payload}` — writes one row to `telemedicine_signals` for the peer's next poll to pick up. Handles `offer` / `answer` / `ice-candidate` / `toggle-media` / `chat` (also persisted to `telemedicine_chat_messages`) / `end-call`. |
| `api/end-session.php` | Fallback HTTP endpoint hit via `sendBeacon` on tab close, in case the browser never gets to click "End call" — marks the appointment completed and posts one last `call-ended` signal so the peer isn't left waiting out the full presence timeout. |
| `config.php` | Shared constants (ICE servers, ticket signing secret — reuses `JWT_SECRET`). |

### Deprecated (kept for reference only)

`signaling-server.php` and `SignalingServer.php` were the original Ratchet-based WebSocket signaling server. **Not used by the app anymore** — see "Why polling, not WebSocket" below. They're left in the repo in case a future move to a VPS makes reviving true WebSocket signaling (lower latency than polling) worthwhile.

## Why polling, not WebSocket

A WebSocket signaling server needs a **persistent background process listening on a custom port**. Hostinger shared/Business hosting (cPanel/hPanel) does not support this — only outgoing connections are allowed, and there's no way to open a custom port for incoming traffic or keep a non-PHP-FPM process running. This is a hosting-plan limitation, not something fixable in code, short of moving to a VPS or a managed Node.js hosting product.

HTTP polling works within those constraints because it's just ordinary PHP requests — no different from any other AJAX endpoint in the app.

**Trade-off:** call setup (the offer/answer/ICE handshake) takes a couple of seconds longer than with WebSocket, and chat messages arrive with up to ~2s of latency (the poll interval). **Video/audio quality itself is unaffected** — once connected, the media stream is still direct peer-to-peer WebRTC, not touched by polling at all.

## What's intentionally out of scope (v1)

- **TURN server** — only public STUN is configured. Most direct connections work fine with STUN alone, but calls from behind strict corporate firewalls / some mobile carriers can fail to connect without a TURN relay. Add one later (e.g. self-hosted `coturn`) by extending `TELEMED_ICE_SERVERS` in `config.php`.
- **Recording.**
- **Group calls** — this is strictly 1 doctor + 1 patient per appointment.
- **Screen share** — not wired up, though `room.js`'s `RTCPeerConnection` setup would support adding it later via `getDisplayMedia`.
