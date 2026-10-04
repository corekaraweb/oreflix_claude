<?php

declare(strict_types=1);

// PRIVATE_DIR must point to a directory that is NOT served over HTTP.
// By default this assumes the layout documented in server/README.md:
//   .../public/   <- this file's directory (web root)
//   .../private/  <- sibling directory, one level up
define('PRIVATE_DIR', dirname(__DIR__) . '/private');

require PRIVATE_DIR . '/Config.php';
require PRIVATE_DIR . '/Security.php';
require PRIVATE_DIR . '/YouTubeClient.php';
