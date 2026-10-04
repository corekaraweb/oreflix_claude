<?php

declare(strict_types=1);

function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => isHttps(),
]);
session_start();

require __DIR__ . '/../bootstrap.php';

$ip = Security::clientIp();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function redirectBack(string $flash = null): void
{
    if ($flash !== null) {
        $_SESSION['flash'] = $flash;
    }
    header('Location: index.php');
    exit;
}

$setupComplete = Config::isSetupComplete();
$loggedIn = $setupComplete && !empty($_SESSION['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        redirectBack('セッションの有効期限が切れました。もう一度お試しください。');
    }

    if ($action === 'setup' && !$setupComplete) {
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($password) < 8) {
            redirectBack('パスワードは8文字以上で設定してください。');
        }
        if ($password !== $confirm) {
            redirectBack('パスワードが一致しません。');
        }
        Config::setPassword($password);
        $_SESSION['admin'] = true;
        session_regenerate_id(true);
        redirectBack('管理者パスワードを設定しました。');
    }

    if ($action === 'login' && $setupComplete && !$loggedIn) {
        if (Security::isLoginLockedOut($ip)) {
            $mins = (int) ceil(Security::loginLockoutRemaining($ip) / 60);
            redirectBack("ログイン試行回数が上限に達しました。{$mins}分後に再試行してください。");
        }
        $password = (string) ($_POST['password'] ?? '');
        if (Config::verifyPassword($password)) {
            Security::clearFailedLogins($ip);
            $_SESSION['admin'] = true;
            session_regenerate_id(true);
            redirectBack();
        }
        Security::recordFailedLogin($ip);
        redirectBack('パスワードが違います。');
    }

    if ($loggedIn && $action === 'update_api_key') {
        $key = (string) ($_POST['api_key'] ?? '');
        if ($key !== '') {
            Config::setApiKey($key);
            redirectBack('APIキーを更新しました。');
        }
        redirectBack();
    }

    if ($loggedIn && $action === 'update_contact_email') {
        $email = trim((string) ($_POST['contact_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectBack('メールアドレスの形式が正しくありません。');
        }
        Config::setContactEmail($email);
        redirectBack($email !== '' ? '連絡先メールアドレスを更新しました。' : '連絡先メールアドレスを削除しました。');
    }

    if ($loggedIn && $action === 'add_playlist') {
        $input = (string) ($_POST['playlist'] ?? '');
        $added = Config::addPlaylist($input);
        redirectBack($added !== null ? '再生リストを追加しました。' : '追加できませんでした(空欄か、既に追加済みです)。');
    }

    if ($loggedIn && $action === 'remove_playlist') {
        $id = (string) ($_POST['id'] ?? '');
        Config::removePlaylist($id);
        redirectBack('再生リストを削除しました。');
    }

    if ($loggedIn && $action === 'reorder_playlists') {
        $orderRaw = (string) ($_POST['order'] ?? '');
        $orderedIds = array_values(array_filter(explode(',', $orderRaw), static fn ($v) => $v !== ''));
        Config::reorderPlaylists($orderedIds);
        redirectBack('並び順を保存しました。');
    }

    if ($loggedIn && $action === 'update_playlist_title') {
        $id = (string) ($_POST['id'] ?? '');
        $title = (string) ($_POST['title'] ?? '');
        if (in_array($id, Config::getPlaylistIds(), true)) {
            Config::setCustomTitle($id, $title);
            redirectBack($title !== '' ? '表示名を変更しました。' : '表示名を自動取得タイトルに戻しました。');
        }
        redirectBack();
    }

    if ($loggedIn && $action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        if (!Config::verifyPassword($current)) {
            redirectBack('現在のパスワードが違います。');
        }
        if (strlen($new) < 8) {
            redirectBack('新しいパスワードは8文字以上で設定してください。');
        }
        if ($new !== $confirm) {
            redirectBack('新しいパスワードが一致しません。');
        }
        Config::setPassword($new);
        redirectBack('パスワードを変更しました。');
    }

    redirectBack();
}

$csrf = Security::csrfToken();
$config = Config::load();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>OREFLIX 管理画面</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: #141414;
    color: #fff;
    font: 16px/1.6 'Helvetica Neue', Helvetica, Arial, 'Hiragino Sans', 'Noto Sans JP', sans-serif;
    display: flex;
    justify-content: center;
    padding: 3rem 1.25rem;
  }
  main { width: 100%; max-width: 560px; }
  h1 { color: #e50914; font-size: 1.6rem; margin: 0 0 1.5rem; }
  h2 { font-size: 1.1rem; margin: 0 0 0.75rem; }
  section {
    background: #1f1f1f;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
  }
  p.hint { color: #b3b3b3; font-size: 0.85rem; margin: 0 0 1rem; line-height: 1.5; }
  label { display: block; font-size: 0.85rem; color: #b3b3b3; margin-bottom: 0.3rem; }
  input[type="text"], input[type="password"], input[type="url"] {
    width: 100%;
    background: #141414;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px;
    color: #fff;
    padding: 0.6rem 0.75rem;
    font-size: 0.95rem;
    margin-bottom: 0.9rem;
  }
  button {
    background: #e50914;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 0.6rem 1.25rem;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
  }
  button:hover { background: #f6121d; }
  button.secondary { background: rgba(109,109,110,0.5); }
  button.secondary:hover { background: rgba(109,109,110,0.8); }
  .flash {
    background: #2b2b2b;
    border-left: 3px solid #e50914;
    padding: 0.75rem 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
  }
  .warning {
    background: #3a2a00;
    border-left: 3px solid #f0a500;
    padding: 0.75rem 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.85rem;
  }
  ul.playlist-list { list-style: none; margin: 0 0 1rem; padding: 0; }
  ul.playlist-list li {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: #141414;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
  }
  ul.playlist-list li.dragging { opacity: 0.4; }
  .drag-handle {
    cursor: grab;
    color: #808080;
    font-size: 1.1rem;
    line-height: 1;
    padding: 0.2rem 0.3rem;
    user-select: none;
    flex-shrink: 0;
  }
  .drag-handle:active { cursor: grabbing; }
  .playlist-label { flex: 1; min-width: 0; word-break: break-all; }
  .playlist-label .playlist-title { display: block; font-weight: 600; }
  .playlist-label .playlist-id { display: block; color: #808080; font-size: 0.75rem; }
  .playlist-label .playlist-warning { display: block; color: #f0a500; font-size: 0.75rem; }
  ul.playlist-list form.playlist-title-form { display: flex; gap: 0.4rem; margin-top: 0.4rem; }
  .playlist-title-form input[type="text"] {
    flex: 1;
    min-width: 0;
    margin-bottom: 0;
    padding: 0.35rem 0.5rem;
    font-size: 0.8rem;
  }
  .playlist-title-form button { padding: 0.3rem 0.7rem; font-size: 0.75rem; white-space: nowrap; }
  ul.playlist-list form { margin: 0; flex-shrink: 0; }
  ul.playlist-list button { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
  .top-bar { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.5rem; }
  .top-bar a { color: #b3b3b3; font-size: 0.85rem; }
  .top-bar nav { display: flex; gap: 1.25rem; }
  .back-link { display: inline-block; margin-top: 1.5rem; color: #b3b3b3; font-size: 0.85rem; }
</style>
</head>
<body>
<main>
  <?php if (!isHttps()): ?>
    <div class="warning">
      HTTPS接続ではありません。パスワードや設定を安全に送信するため、本番環境では必ずHTTPS(SSL)を有効にしてください。
    </div>
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="flash"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if (!$setupComplete): ?>
    <h1>OREFLIX 初期設定</h1>
    <section>
      <h2>管理者パスワードを設定してください</h2>
      <p class="hint">この画面は初回のみ表示されます。設定したパスワードで、以後この管理画面にログインします。</p>
      <form method="post">
        <input type="hidden" name="action" value="setup" />
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
        <label for="password">パスワード(8文字以上)</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" />
        <label for="password_confirm">パスワード(確認)</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password" />
        <button type="submit">設定する</button>
      </form>
    </section>
    <a class="back-link" href="/">トップページに戻る</a>

  <?php elseif (!$loggedIn): ?>
    <h1>OREFLIX 管理画面</h1>
    <section>
      <h2>ログイン</h2>
      <form method="post">
        <input type="hidden" name="action" value="login" />
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
        <label for="password">パスワード</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" autofocus />
        <button type="submit">ログイン</button>
      </form>
    </section>
    <a class="back-link" href="/">トップページに戻る</a>

  <?php else: ?>
    <div class="top-bar">
      <h1>OREFLIX 管理画面</h1>
      <nav>
        <a href="/">トップページに戻る</a>
        <a href="logout.php">ログアウト</a>
      </nav>
    </div>

    <section>
      <h2>YouTube Data API キー</h2>
      <p class="hint">
        <?= $config['apiKey'] !== '' ? '現在キーが設定されています。変更する場合のみ入力して保存してください。' : 'まだAPIキーが設定されていません。' ?>
      </p>
      <form method="post">
        <input type="hidden" name="action" value="update_api_key" />
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
        <label for="api_key">新しいAPIキー</label>
        <input type="text" id="api_key" name="api_key" autocomplete="off" placeholder="変更する場合のみ入力" />
        <button type="submit">保存</button>
      </form>
    </section>

    <section>
      <h2>連絡先メールアドレス</h2>
      <p class="hint">
        プライバシーポリシーページ(<a href="/privacy.html" target="_blank">/privacy.html</a>)の「お問い合わせ」欄に表示されます。
        空欄のまま保存すると、お問い合わせ欄は非表示になります。
      </p>
      <form method="post">
        <input type="hidden" name="action" value="update_contact_email" />
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
        <label for="contact_email">メールアドレス</label>
        <input
          type="text"
          id="contact_email"
          name="contact_email"
          autocomplete="off"
          value="<?= htmlspecialchars($config['contactEmail'], ENT_QUOTES, 'UTF-8') ?>"
          placeholder="example@example.com"
        />
        <button type="submit">保存</button>
      </form>
    </section>

    <section>
      <h2>再生リスト</h2>
      <p class="hint">
        追加した再生リストは、サイトを訪れる全員に同じ内容で表示されます。
        <?= count($config['playlistIds']) > 1 ? '「⠿」をドラッグすると表示順を並び替えられます。' : '' ?>
      </p>
      <ul class="playlist-list" id="playlist-list">
        <?php if (empty($config['playlistIds'])): ?>
          <li>まだ再生リストがありません。</li>
        <?php endif; ?>
        <?php foreach ($config['playlistIds'] as $id): ?>
          <?php
            $realTitle = YouTubeClient::fetchPlaylistTitle($id);
            $customTitle = Config::getCustomTitle($id);
            $displayTitle = $customTitle ?? $realTitle;
          ?>
          <li data-id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
            <span class="drag-handle" draggable="true" aria-label="ドラッグして並び替え">⠿</span>
            <span class="playlist-label">
              <?php if ($displayTitle !== null): ?>
                <span class="playlist-title"><?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($customTitle !== null && $realTitle !== null): ?>
                  <span class="playlist-id">元のタイトル: <?= htmlspecialchars($realTitle, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="playlist-id"><?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?></span>
              <?php else: ?>
                <span class="playlist-title"><?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="playlist-warning">タイトルを取得できませんでした(APIキーや再生リストIDを確認してください)</span>
              <?php endif; ?>
              <form method="post" class="playlist-title-form">
                <input type="hidden" name="action" value="update_playlist_title" />
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" />
                <input
                  type="text"
                  name="title"
                  value="<?= htmlspecialchars((string) $customTitle, ENT_QUOTES, 'UTF-8') ?>"
                  placeholder="サイトに表示する名前(空欄で自動取得タイトルを使用)"
                />
                <button type="submit" class="secondary">変更</button>
              </form>
            </span>
            <form method="post">
              <input type="hidden" name="action" value="remove_playlist" />
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
              <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" />
              <button type="submit" class="secondary">削除</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <form method="post" id="reorder-form" style="display:none;">
        <input type="hidden" name="action" value="reorder_playlists" />
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="order" id="playlist-order" value="" />
      </form>
      <form method="post">
        <input type="hidden" name="action" value="add_playlist" />
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
        <label for="playlist">再生リストのURLまたはID</label>
        <input type="text" id="playlist" name="playlist" placeholder="https://www.youtube.com/playlist?list=..." />
        <button type="submit">追加</button>
      </form>
    </section>

    <section>
      <h2>パスワード変更</h2>
      <form method="post">
        <input type="hidden" name="action" value="change_password" />
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
        <label for="current_password">現在のパスワード</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password" />
        <label for="new_password">新しいパスワード(8文字以上)</label>
        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" />
        <label for="new_password_confirm">新しいパスワード(確認)</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password" />
        <button type="submit">変更する</button>
      </form>
    </section>
  <?php endif; ?>
</main>
<script>
  (function () {
    var list = document.getElementById('playlist-list');
    if (!list) return;
    var dragLi = null;

    list.addEventListener('dragstart', function (e) {
      dragLi = e.target.closest('li[data-id]');
      if (dragLi) {
        dragLi.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
      }
    });

    list.addEventListener('dragend', function () {
      if (dragLi) dragLi.classList.remove('dragging');
      dragLi = null;
    });

    list.addEventListener('dragover', function (e) {
      if (!dragLi) return;
      e.preventDefault();
      var overLi = e.target.closest('li[data-id]');
      if (!overLi || overLi === dragLi) return;
      var rect = overLi.getBoundingClientRect();
      var before = (e.clientY - rect.top) < rect.height / 2;
      list.insertBefore(dragLi, before ? overLi : overLi.nextSibling);
    });

    list.addEventListener('drop', function (e) {
      if (!dragLi) return;
      e.preventDefault();
      var ids = Array.prototype.map.call(
        list.querySelectorAll('li[data-id]'),
        function (li) { return li.dataset.id; }
      );
      var form = document.getElementById('reorder-form');
      document.getElementById('playlist-order').value = ids.join(',');
      // Save in the background (fetch) instead of form.submit() — a real
      // navigation would reload the page and jump the scroll to the top.
      fetch('index.php', {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
      }).catch(function () {
        // Best-effort; if it fails the next full page load will show the
        // previous order and the admin can just try dragging again.
      });
    });
  })();
</script>
</body>
</html>
