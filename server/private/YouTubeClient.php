<?php

declare(strict_types=1);

/**
 * Shared YouTube Data API client used by both the public proxy
 * (server/public/api/youtube.php) and the admin panel (playlist titles).
 * Handles injecting the API key, caching responses to a file, and the
 * actual HTTP call. Callers are responsible for validating any
 * user-supplied input before calling request().
 */
final class YouTubeClient
{
    private const ENDPOINTS = [
        'playlist' => 'playlists',
        'playlistItems' => 'playlistItems',
        'videos' => 'videos',
    ];

    private const DEFAULT_PARAMS = [
        'playlist' => ['part' => 'snippet,contentDetails'],
        'playlistItems' => ['part' => 'snippet,contentDetails', 'maxResults' => 50],
        'videos' => ['part' => 'contentDetails,statistics'],
    ];

    private const CACHE_TTL = [
        'playlist' => 3600,
        'playlistItems' => 600,
        'videos' => 1800,
    ];

    /**
     * @param array<string, scalar|null> $identifyingParams e.g. ['id' => $playlistId]
     *   or ['playlistId' => ..., 'pageToken' => ...]
     * @return array{status: int, body: array}
     */
    public static function request(string $resource, array $identifyingParams): array
    {
        if (!isset(self::ENDPOINTS[$resource])) {
            return ['status' => 400, 'body' => ['error' => ['message' => '不正なresourceです。']]];
        }

        $apiKey = Config::getApiKey();
        if ($apiKey === '') {
            return ['status' => 500, 'body' => ['error' => ['message' => 'サーバーにYouTube APIキーが設定されていません。']]];
        }

        $params = self::DEFAULT_PARAMS[$resource];
        foreach ($identifyingParams as $key => $value) {
            if ($value !== null && $value !== '') {
                $params[$key] = $value;
            }
        }

        $cacheDir = __DIR__ . '/cache/youtube';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, true);
        }
        $cacheKey = hash('sha256', $resource . '|' . http_build_query($params));
        $cachePath = $cacheDir . '/' . $cacheKey . '.json';
        $ttl = self::CACHE_TTL[$resource];

        if (is_file($cachePath) && (filemtime($cachePath) + $ttl) > time()) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cached)) {
                return ['status' => 200, 'body' => $cached];
            }
        }

        // Only requests that actually reach YouTube count against the rate
        // limit — cache hits are free and shared by every visitor, so they
        // must never be throttled (a page with many playlists can easily
        // make far more than one request per second on a warm cache).
        if (!Security::checkRateLimit('youtube-upstream', 300, 60)) {
            return ['status' => 429, 'body' => ['error' => ['message' => 'リクエストが多すぎます。しばらくしてから再試行してください。']]];
        }

        $params['key'] = $apiKey;
        $url = 'https://www.googleapis.com/youtube/v3/' . self::ENDPOINTS[$resource] . '?' . http_build_query($params);

        [$status, $body] = self::httpGet($url);
        if ($body === null) {
            return ['status' => 502, 'body' => ['error' => ['message' => 'YouTube APIへの接続に失敗しました。']]];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['status' => 502, 'body' => ['error' => ['message' => 'YouTube APIから不正な応答がありました。']]];
        }

        if ($status === 200) {
            file_put_contents(
                $cachePath,
                json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX,
            );
        }

        return ['status' => $status, 'body' => $decoded];
    }

    /** Convenience for the admin panel: the playlist's title, or null on any failure. */
    public static function fetchPlaylistTitle(string $playlistId): ?string
    {
        $result = self::request('playlist', ['id' => $playlistId]);
        return $result['body']['items'][0]['snippet']['title'] ?? null;
    }

    /** @return array{0: int, 1: ?string} */
    private static function httpGet(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $status = $body !== false ? (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) : 502;
            curl_close($ch);
            return [$status, $body !== false ? $body : null];
        }

        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 502;
        if (isset($http_response_header[0]) && preg_match('/(\d{3})/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        return [$status, $body !== false ? $body : null];
    }
}
