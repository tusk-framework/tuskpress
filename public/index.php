<?php

/**
 * Conventional front controller for TuskPress.
 */

declare(strict_types=1);

if (php_sapi_name() === 'cli-server') {
    $url  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $url;
    if (is_file($file)) {
        return false;
    }
}

// Security: prevents direct access to internal files
if (!defined('TUSKPRESS_BOOTSTRAP')) {
    define('TUSKPRESS_BOOTSTRAP', true);
}

require_once dirname(__DIR__).'/vendor/autoload.php';

/** @var \Tusk\Foundation\Application $application */
$application = require dirname(__DIR__).'/bootstrap/app.php';
$factory = new \Nyholm\Psr7\Factory\Psr17Factory();
$request = (new \Nyholm\Psr7Server\ServerRequestCreator(
    $factory,
    $factory,
    $factory,
    $factory,
))->fromGlobals();

$response = $application->handle($request);
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name.': '.$value, false);
    }
}

$body = $response->getBody();
while (! $body->eof()) {
    echo $body->read(8192);
}
