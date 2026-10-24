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

このプロジェクトにテストスイートは存在しない。PHPを編集した後は `php -l <file>` で構文チェックすること(PHPのLintツールは導入していない)。フロントエンドは、ローカルで `/api/*` や `/admin/*` を解決するためにPHPバックエンドの起動が必要 — `vite.config.js` の開発用プロキシがこれらのパスを `http://localhost:8080` へ転送しているため、`npm run dev` と合わせて必ず `npm run dev:php`(または `npm run dev:all` だけ)を起動すること。

## アーキテクチャ

OREFLIXは2層構成のアプリ: React製SPA(`src/`)と、サイト共通設定を保持しYouTube Data API v3への全呼び出しをプロキシする小さなPHPバックエンド(`server/`)。この分離が必要な理由は、再生リスト設定を(ブラウザ単位ではなく)全訪問者で共有する必要があることと、YouTube APIキーを絶対にブラウザへ渡してはいけないため。詳しい経緯とデプロイ手順は `README.md` と `server/README.md` を参照。

### フロントエンド(`src/`)

- `App.jsx` が唯一の状態管理の中心: `/api/config.php` から再生リスト一覧・カテゴリー・連絡先メールアドレスを取得し、`hooks/usePlaylists.js`(`api/youtube.js` 経由で再生リストごとに取得)を駆動し、`Hero`/`Row`/`VideoModal` を描画する。
- `api/youtube.js` はGoogleに直接アクセスしない — 全ての呼び出しは `/api/youtube.php` を経由する。`fetchPlaylistMeta`、`fetchPlaylistItemsPage`、`fetchVideosDetails` と、表示用のフォーマットヘルパーを公開している。
- `usePlaylists.js` は各再生リストを独立して読み込む(基本情報 → ページ単位の動画一覧 → 動画詳細の順)。最大200本(`MAX_PAGES = 4`)まで。設定済みの再生リスト一覧が変わった際は `AbortController` でキャンセルする。
- トップページでは、管理者が割り当てたカテゴリーごとに再生リストをグループ化する(`App.jsx` の `groupedPlaylists`、サーバー側の `categoryOrder` で順序づけ)。カテゴリーが1つも設定されていない場合は、従来のフラットな行レイアウトにフォールバックするため、この機能を使っていないサイトの表示は変わらない。
- `hooks/useConsent.js` と `components/ConsentBanner.jsx` が実際のYouTubeプレーヤーをゲートしている — `Hero`/`VideoCard`/`VideoModal` は、訪問者が同意するまでiframeを絶対にマウントしてはならない。これは単なるUXではなくYouTube Developer Policiesへの準拠要件であり、同意前に動画再生に到達できる状態にしないこと。
- `components/ErrorBoundary.jsx` と `index.html` 内のインラインフォールバック `<script>` は、意図的な二段構えのクラッシュ画面: インラインスクリプトは、Reactそのものが決して捕捉できない失敗(バンドルの解析・読み込み自体の失敗)を捕捉し、Reactのエラー境界は描画時のエラーを捕捉する。`main.jsx` はReactがマウントした時点で `root.dataset.mounted` をセットし、それ以降インラインスクリプト側の介入を止める。
- `domPatch.js`(他の何かがDOMに触れる前に、`main.jsx` の先頭でimportされる)は、Reactの知らないところで既にノードが削除されていた場合、`Node.prototype.removeChild`/`insertBefore` が例外を投げる代わりに何もしないようパッチしている。これは実際の本番クラッシュを修正したもの: iOSのコンテンツブロッカーがYouTubeの `<iframe>` を取り除き、その後のReactのクリーンアップ処理が捕捉されない `NotFoundError` を投げていた。存在理由を理解せずに削除しないこと。

### バックエンド(`server/`)

- `server/private/` は**公開ディレクトリの外**に配置しなければならない — `server/public/bootstrap.php` は `PRIVATE_DIR` を `dirname(__DIR__) . '/private'`(`server/public/` の配置先と同じ階層)として解決する。`private/` 内の `.htaccess` はApacheしか保護しない。実際の本番環境はnginxであり、本当の防御はサーバーのnginx設定にある `location ^~ /private/ { deny all; }` ブロック(このリポジトリには含まれない)。
- `Config.php` はサイト設定(APIキー、再生リストIDと並び順、カスタムタイトル、カテゴリーとカテゴリー並び順、連絡先メールアドレス、管理者パスワードのハッシュ)の唯一の正となる場所であり、`server/private/config.json` へ原子的に永続化する(一時ファイルに書き込み、`chmod 0600`、その後 `rename()`)。
- `Security.php` はCSRFトークン、ファイルベースのログインロックアウト(IPごとに15分で5回失敗)、汎用的なスライディングウィンドウ型レート制限を提供する。両方とも、同時リクエスト下での既知の(許容済みの)TOCTOU競合状態がある(読み取り→変更→書き込みのサイクルが全体を通してロックされていない) — bcryptの計算コストのおかげで深刻度は低いが、これ以上厳格な用途で頼る前には把握しておく価値がある。
- `YouTubeClient.php` は、実際のAPIキーをGoogleへ送信する唯一のコード。全てのレスポンスを `server/private/cache/youtube/` 配下にキャッシュし(TTL: 再生リスト1時間、動画一覧10分、動画詳細30分)、実際にアップストリームへ到達する呼び出しのみをレート制限する — キャッシュヒットは常に無料。そうしないと、再生リスト・カテゴリーが多いページでは、キャッシュが冷えている状態で正当な理由でも1分あたりの妥当な上限を超えてバーストしうるため。また `server/private/cache/known_video_ids.json`(承認済み再生リストの中で実際に見つかった動画IDの許可リスト)を保持しており、`api/youtube.php` の `videos` リソースがこれをチェックすることで、任意の動画IDを調べられる野良のYouTubeメタデータ参照プロキシになってしまうことを防いでいる。
- `server/public/api/youtube.php` は公開プロキシ: `YouTubeClient::request()` を呼ぶ前に、`resource`/`playlistId`/`id` を管理者が設定した再生リスト(および動画IDの許可リスト)に照らして検証する。また、`playlist` リソースのタイトルを管理者のカスタム上書きで書き換えてから返すため、フロントエンド側は上書きが起きたことを一切知る必要がない。
- `server/public/admin/index.php` は単一の自己完結した手続き型スクリプト(初期設定/ログイン/ダッシュボードを `$setupComplete`/`$loggedIn` で分岐)— 全ての状態変更操作はPOST + CSRFチェック必須で、クリックジャッキング対策として `X-Frame-Options: DENY` / `frame-ancestors 'none'` を送信する。再生リスト・カテゴリーのドラッグ並び替えは `form.submit()` ではなく `fetch()` でバックグラウンドPOSTする。これはページ再読み込みでスクロール位置が先頭に戻ってしまうのを防ぐため。
- `server/public/api/config.php` は唯一の認証不要な読み取りエンドポイント — 再生リストID・カテゴリー・カテゴリー並び順・連絡先メールアドレスを返すが、APIキーやパスワードのハッシュは決して返さない。

### デプロイ環境

本番環境は単一のnginx + PHP-FPMのVPS上で動作している(`server/README.md` にApache向けの `.htaccess` の記述があるが、古典的な共用Apacheホスティングではなく、そのファイルはここでは死んだ設定である)。`dist/` と `server/public/` の中身は同じnginxのドキュメントルートにデプロイし、`server/private/` はその1つ上の階層に置く。`index.html` など `.html` ファイルは `Cache-Control: no-cache` で、`/assets/` は長期の不変キャッシュで配信しなければならない — これは実際に起きた本番障害(iOS Safariが、削除されず残っていた古いハッシュ付きビルドを参照する古いキャッシュ済みHTMLを使い続けた)の根本原因だった。デプロイ時は `assets/` を追加するのではなく、必ず丸ごと置き換えること。
