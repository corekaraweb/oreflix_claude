<?php

declare(strict_types=1);

/**
 * Reads/writes the shared site configuration (API key, playlist IDs, admin
 * password hash) from a single JSON file. All visitors share this config;
 * only the /admin panel can change it.
 */
final class Config
{
    private static function path(): string
    {
        return __DIR__ . '/config.json';
    }

    private static function defaults(): array
    {
        return [
            'apiKey' => '',
            'playlistIds' => [],
            'customTitles' => [],
            'playlistCategories' => [],
            'contactEmail' => '',
            'adminPasswordHash' => null,
            'updatedAt' => null,
        ];
    }

    public static function load(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return self::defaults();
        }
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return self::defaults();
        }
        return array_merge(self::defaults(), $data);
    }

    public static function save(array $data): void
    {
        $data['updatedAt'] = gmdate('c');
        $path = self::path();
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $fp = fopen($tmp, 'w');
        if ($fp === false) {
            throw new RuntimeException('設定ファイルを書き込めませんでした。');
        }
        flock($fp, LOCK_EX);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($tmp, 0600);
        rename($tmp, $path);
    }

    public static function isSetupComplete(): bool
    {
        $data = self::load();
        return !empty($data['adminPasswordHash']);
    }

    public static function verifyPassword(string $password): bool
    {
        $data = self::load();
        if (empty($data['adminPasswordHash'])) {
            return false;
        }
        return password_verify($password, $data['adminPasswordHash']);
    }

    public static function setPassword(string $password): void
    {
        $data = self::load();
        $data['adminPasswordHash'] = password_hash($password, PASSWORD_DEFAULT);
        self::save($data);
    }

    public static function getApiKey(): string
    {
        return (string) self::load()['apiKey'];
    }

    public static function setApiKey(string $key): void
    {
        $data = self::load();
        $data['apiKey'] = trim($key);
        self::save($data);
    }

    /** Contact address shown on the public privacy policy page. */
    public static function getContactEmail(): string
    {
        return (string) self::load()['contactEmail'];
    }

    public static function setContactEmail(string $email): void
    {
        $data = self::load();
        $data['contactEmail'] = trim($email);
        self::save($data);
    }

    public static function getPlaylistIds(): array
    {
        $ids = self::load()['playlistIds'];
        return is_array($ids) ? array_values($ids) : [];
    }

    /** Accepts a full playlist URL or a bare playlist ID and returns the ID. */
    public static function extractPlaylistId(string $input): string
    {
        $trimmed = trim($input);
        $query = parse_url($trimmed, PHP_URL_QUERY);
        if ($query !== null && $query !== false) {
            parse_str($query, $params);
            if (!empty($params['list'])) {
                return $params['list'];
            }
        }
        return $trimmed;
    }

    /** Returns the added ID, or null if empty/duplicate. */
    public static function addPlaylist(string $urlOrId): ?string
    {
        $id = self::extractPlaylistId($urlOrId);
        if ($id === '') {
            return null;
        }
        $data = self::load();
        $ids = is_array($data['playlistIds']) ? $data['playlistIds'] : [];
        if (in_array($id, $ids, true)) {
            return null;
        }
        $ids[] = $id;
        $data['playlistIds'] = $ids;
        self::save($data);
        return $id;
    }

    public static function removePlaylist(string $id): void
    {
        $data = self::load();
        $ids = is_array($data['playlistIds']) ? $data['playlistIds'] : [];
        $data['playlistIds'] = array_values(array_filter($ids, static fn ($existing) => $existing !== $id));
        $titles = is_array($data['customTitles']) ? $data['customTitles'] : [];
        unset($titles[$id]);
        $data['customTitles'] = $titles;
        $categories = is_array($data['playlistCategories']) ? $data['playlistCategories'] : [];
        unset($categories[$id]);
        $data['playlistCategories'] = $categories;
        self::save($data);
    }

    /** The admin-set display name for a playlist, or null if not overridden. */
    public static function getCustomTitle(string $id): ?string
    {
        $titles = self::load()['customTitles'];
        if (!is_array($titles) || empty($titles[$id])) {
            return null;
        }
        return (string) $titles[$id];
    }

    public static function getCustomTitles(): array
    {
        $titles = self::load()['customTitles'];
        return is_array($titles) ? $titles : [];
    }

    /** An empty $title clears the override (falls back to the real YouTube title). */
    public static function setCustomTitle(string $id, string $title): void
    {
        $data = self::load();
        $titles = is_array($data['customTitles']) ? $data['customTitles'] : [];
        $title = trim($title);
        if ($title === '') {
            unset($titles[$id]);
        } else {
            $titles[$id] = $title;
        }
        $data['customTitles'] = $titles;
        self::save($data);
    }

    /** The admin-set category tag for a playlist, or null if uncategorized. */
    public static function getCategory(string $id): ?string
    {
        $categories = self::load()['playlistCategories'];
        if (!is_array($categories) || empty($categories[$id])) {
            return null;
        }
        return (string) $categories[$id];
    }

    public static function getPlaylistCategories(): array
    {
        $categories = self::load()['playlistCategories'];
        return is_array($categories) ? $categories : [];
    }

    /** An empty $category clears it (the playlist becomes uncategorized). */
    public static function setCategory(string $id, string $category): void
    {
        $data = self::load();
        $categories = is_array($data['playlistCategories']) ? $data['playlistCategories'] : [];
        $category = trim($category);
        if ($category === '') {
            unset($categories[$id]);
        } else {
            $categories[$id] = $category;
        }
        $data['playlistCategories'] = $categories;
        self::save($data);
    }

    /**
     * Reorders playlists per the admin's drag-and-drop. Only IDs that are
     * already configured are honored (ignores anything else submitted);
     * any configured ID missing from $orderedIds is appended at the end so
     * nothing is silently dropped.
     */
    public static function reorderPlaylists(array $orderedIds): void
    {
        $data = self::load();
        $current = is_array($data['playlistIds']) ? $data['playlistIds'] : [];

        $reordered = [];
        foreach ($orderedIds as $id) {
            if (in_array($id, $current, true) && !in_array($id, $reordered, true)) {
                $reordered[] = $id;
            }
        }
        foreach ($current as $id) {
            if (!in_array($id, $reordered, true)) {
                $reordered[] = $id;
            }
        }

        $data['playlistIds'] = $reordered;
        self::save($data);
    }
}
