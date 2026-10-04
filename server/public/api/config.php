<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'playlistIds' => Config::getPlaylistIds(),
    'categories' => Config::getPlaylistCategories(),
    'contactEmail' => Config::getContactEmail(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
