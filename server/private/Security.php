<?php

declare(strict_types=1);

/**
 * Small security helpers shared by the admin panel and the API proxy:
 * CSRF tokens, login lockout, and a simple per-IP rate limiter.
 */
final class Security
{
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_WINDOW_SECONDS = 15 * 60;
    private const LOGIN_LOCKOUT_SECONDS = 15 * 60;

    public static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private static function ipKey(string $ip): string
    {
        return hash('sha256', $ip);
    }

    private static function loginAttemptsPath(): string
    {
        return __DIR__ . '/login_attempts.json';
    }

    private static function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private static function writeJsonFile(string $path, array $data): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($data), LOCK_EX);
        chmod($tmp, 0600);
        rename($tmp, $path);
    }

    public static function isLoginLockedOut(string $ip): bool
    {
        $attempts = self::readJsonFile(self::loginAttemptsPath());
        $entry = $attempts[self::ipKey($ip)] ?? null;
        if ($entry === null) {
            return false;
        }
        return isset($entry['lockedUntil']) && $entry['lockedUntil'] > time();
    }

    public static function loginLockoutRemaining(string $ip): int
    {
        $attempts = self::readJsonFile(self::loginAttemptsPath());
        $entry = $attempts[self::ipKey($ip)] ?? null;
        if ($entry === null || !isset($entry['lockedUntil'])) {
            return 0;
        }
        return max(0, $entry['lockedUntil'] - time());
    }

    public static function recordFailedLogin(string $ip): void
    {
        $path = self::loginAttemptsPath();
        $attempts = self::readJsonFile($path);
        $key = self::ipKey($ip);
        $now = time();
        $entry = $attempts[$key] ?? ['count' => 0, 'firstAttempt' => $now];

        if ($now - $entry['firstAttempt'] > self::LOGIN_WINDOW_SECONDS) {
            $entry = ['count' => 0, 'firstAttempt' => $now];
        }

        $entry['count']++;
        if ($entry['count'] >= self::LOGIN_MAX_ATTEMPTS) {
            $entry['lockedUntil'] = $now + self::LOGIN_LOCKOUT_SECONDS;
        }
        $attempts[$key] = $entry;

        // Keep the file small: drop stale entries.
        foreach ($attempts as $k => $v) {
            $expired = ($v['lockedUntil'] ?? 0) < $now
                && ($now - $v['firstAttempt']) > self::LOGIN_WINDOW_SECONDS;
            if ($expired) {
                unset($attempts[$k]);
            }
        }

        self::writeJsonFile($path, $attempts);
    }

    public static function clearFailedLogins(string $ip): void
    {
        $path = self::loginAttemptsPath();
        $attempts = self::readJsonFile($path);
        unset($attempts[self::ipKey($ip)]);
        self::writeJsonFile($path, $attempts);
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /** Generic sliding-window rate limiter, e.g. 60 requests per 60 seconds. */
    public static function checkRateLimit(string $bucket, int $limit, int $windowSeconds): bool
    {
        $dir = __DIR__ . '/cache/ratelimit';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $key = hash('sha256', $bucket . '|' . self::clientIp());
        $path = $dir . '/' . $key . '.json';

        $now = time();
        $entry = self::readJsonFile($path);
        if (empty($entry) || ($now - ($entry['windowStart'] ?? 0)) > $windowSeconds) {
            $entry = ['windowStart' => $now, 'count' => 0];
        }
        $entry['count']++;
        self::writeJsonFile($path, $entry);

        return $entry['count'] <= $limit;
    }
}
