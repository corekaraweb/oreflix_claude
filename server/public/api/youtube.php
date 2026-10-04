<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$resource = $_GET['resource'] ?? '';
$playlistIds = Config::getPlaylistIds();

// Validate the client-supplied identifying parameters. The server decides
// `part`, `key`, etc. itself (see YouTubeClient) — the client can only ask
// for playlists the admin has actually configured.
switch ($resource) {
    case 'playlist':
        $id = (string) ($_GET['id'] ?? '');
        if (!in_array($id, $playlistIds, true)) {
            respond(403, ['error' => ['message' => '許可されていない再生リストです。']]);
        }
        $identifyingParams = ['id' => $id];
        break;

    case 'playlistItems':
        $playlistId = (string) ($_GET['playlistId'] ?? '');
        if (!in_array($playlistId, $playlistIds, true)) {
            respond(403, ['error' => ['message' => '許可されていない再生リストです。']]);
        }
        $pageToken = (string) ($_GET['pageToken'] ?? '');
        if ($pageToken !== '' && !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $pageToken)) {
            respond(400, ['error' => ['message' => '不正なpageTokenです。']]);
        }
        $identifyingParams = ['playlistId' => $playlistId, 'pageToken' => $pageToken];
        break;

    case 'videos':
        $idsParam = (string) ($_GET['id'] ?? '');
        $ids = array_filter(explode(',', $idsParam), static fn ($v) => $v !== '');
        if (count($ids) === 0 || count($ids) > 50) {
            respond(400, ['error' => ['message' => '不正な動画IDです。']]);
        }
        foreach ($ids as $id) {
            if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $id)) {
                respond(400, ['error' => ['message' => '不正な動画IDです。']]);
            }
        }
        $identifyingParams = ['id' => implode(',', $ids)];
        break;

    default:
        respond(400, ['error' => ['message' => '不正なresourceです。']]);
}

$result = YouTubeClient::request($resource, $identifyingParams);

// Let the admin's custom display name (set in /admin/) override the real
// YouTube title, transparently, before it ever reaches the client.
if ($resource === 'playlist' && $result['status'] === 200 && isset($result['body']['items'][0]['snippet'])) {
    $customTitle = Config::getCustomTitle($id);
    if ($customTitle !== null) {
        $result['body']['items'][0]['snippet']['title'] = $customTitle;
    }
}

respond($result['status'], $result['body']);
