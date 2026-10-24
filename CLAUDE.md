# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 開発方針(必ず守ること)

- **外部ライブラリを追加しない。** フロントエンドの依存は `react`/`react-dom`(+ビルドツールのVite/oxlint)のみ、バックエンドはPHP標準関数のみで、Composer等のパッケージ管理も導入していない。新機能もこの制約の中で素のReact/PHPで実装すること。
- **既存の構造を大きく変えない。** 現在のディレクトリ構成・ファイル分割・アーキテクチャ(React SPA + 軽量PHPバックエンドの2層構成)を尊重する。大規模なリファクタリングや再設計は提案・実行しないこと。

## Commands

```bash
npm install
npm run dev       # Vite dev server only (frontend)
npm run dev:php   # PHP built-in server on :8080 (backend)
npm run dev:all   # both of the above together, via `concurrently`
npm run build     # production build to dist/
npm run lint      # oxlint
npm run preview   # preview the production build
```

No test suite exists in this project. After editing PHP, syntax-check with `php -l <file>` (there's no PHP linter configured). The frontend needs the PHP backend running for `/api/*` and `/admin/*` to resolve locally — `vite.config.js`'s dev proxy forwards those paths to `http://localhost:8080`, so always run `npm run dev:php` (or just `npm run dev:all`) alongside `npm run dev`.

## Architecture

OREFLIX is a two-layer app: a React SPA (`src/`) and a small PHP backend (`server/`) that holds the shared site config and proxies all YouTube Data API v3 calls. The split exists because the playlist config must be shared by every visitor (not per-browser) and the YouTube API key must never reach the browser — see `README.md` and `server/README.md` for the full rationale and deployment instructions.

### Frontend (`src/`)

- `App.jsx` is the single stateful orchestrator: fetches `/api/config.php` for the playlist list/categories/contact email, drives `hooks/usePlaylists.js` (per-playlist fetch via `api/youtube.js`), and renders `Hero`/`Row`/`VideoModal`.
- `api/youtube.js` never calls Google directly — every call goes through `/api/youtube.php`. It exposes `fetchPlaylistMeta`, `fetchPlaylistItemsPage`, `fetchVideosDetails`, plus display-formatting helpers.
- `usePlaylists.js` loads each playlist independently (meta → paginated items → video details), capped at 200 videos (`MAX_PAGES = 4`), with `AbortController` cancellation when the configured playlist list changes.
- Playlists are grouped on the homepage by admin-assigned category (`App.jsx`'s `groupedPlaylists`, ordered via the server's `categoryOrder`); with no categories set, it falls back to the original flat row layout so sites that don't use the feature render unchanged.
- `hooks/useConsent.js` + `components/ConsentBanner.jsx` gate the actual YouTube player — `Hero`/`VideoCard`/`VideoModal` must never mount an iframe before the visitor has consented. This is a YouTube Developer Policies compliance requirement, not just UX; don't make video playback reachable before consent.
- `components/ErrorBoundary.jsx` plus the inline fallback `<script>` in `index.html` are a deliberate two-layer crash screen: the inline script catches failures React itself can never catch (the bundle failing to parse/load at all), while the React boundary catches render-time errors. `main.jsx` sets `root.dataset.mounted` once React mounts so the inline script stops intercepting after that point.
- `domPatch.js` (imported first in `main.jsx`, before anything else touches the DOM) patches `Node.prototype.removeChild`/`insertBefore` to no-op instead of throwing when a node was already removed out from under React. This fixed a real production crash: iOS content blockers strip the YouTube `<iframe>`, and React's later cleanup then threw an uncaught `NotFoundError`. Don't remove this without understanding why it's there.

### Backend (`server/`)

- `server/private/` must be deployed **outside the web root** — `server/public/bootstrap.php` resolves `PRIVATE_DIR` as `dirname(__DIR__) . '/private'` (a sibling of wherever `server/public/` is deployed). The `.htaccess` in `private/` only protects Apache; the actual production deployment is nginx, where the real protection is a `location ^~ /private/ { deny all; }` block in the server's nginx config (not anything in this repo).
- `Config.php` is the single source of truth for site config (API key, playlist IDs + order, custom titles, categories + category order, contact email, admin password hash), persisted atomically to `server/private/config.json` (write to a temp file, `chmod 0600`, then `rename()`).
- `Security.php` provides CSRF tokens, a file-based login lockout (5 attempts / 15 min / IP), and a generic sliding-window rate limiter. Both have a known, accepted TOCTOU race under concurrent requests (the read-modify-write cycle isn't lock-protected end to end) — low severity given bcrypt cost, but worth knowing before relying on them for anything stricter.
- `YouTubeClient.php` is the only code that ever sends the real API key to Google. It caches every response under `server/private/cache/youtube/` (TTL: playlist 1h, playlistItems 10min, videos 30min) and rate-limits only actual upstream calls — cache hits are always free, since a page with many playlists/categories can legitimately burst past any reasonable per-minute cap on a cold cache otherwise. It also maintains `server/private/cache/known_video_ids.json`, an allowlist of video IDs seen inside an approved playlist's items, which `api/youtube.php`'s `videos` resource checks so it can't become an open YouTube-metadata lookup proxy for arbitrary video IDs.
- `server/public/api/youtube.php` is the public proxy: validates `resource`/`playlistId`/`id` against the admin's configured playlists (plus the known-video-ID allowlist) before calling `YouTubeClient::request()`. It also rewrites the `playlist` resource's title with the admin's custom override before returning it, so the frontend never needs to know an override happened.
- `server/public/admin/index.php` is a single self-contained procedural script (setup/login/dashboard, branched by `$setupComplete`/`$loggedIn`) — every mutating action is POST + CSRF-checked, and it sends `X-Frame-Options: DENY` / `frame-ancestors 'none'` against clickjacking. Playlist/category drag-reorder POSTs in the background via `fetch()` rather than `form.submit()`, specifically so a page reload doesn't jump the scroll position back to the top.
- `server/public/api/config.php` is the only unauthenticated read endpoint — it returns playlist IDs, categories, category order, and contact email, never the API key or password hash.

### Deployment model

Production runs on a single nginx + PHP-FPM VPS (not classic shared Apache hosting, despite `server/README.md`'s Apache-oriented `.htaccess` note — that file is dead weight there). `dist/` and `server/public/`'s contents are deployed into the same nginx document root; `server/private/` goes one level above it. `index.html` and other `.html` files must be served with `Cache-Control: no-cache` and `/assets/` with a long immutable cache — this was the root cause of a real production incident (stale cached HTML on iOS Safari referencing old, un-deleted hashed bundles). Always replace `assets/` wholesale on deploy rather than adding to it.
