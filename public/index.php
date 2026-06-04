<?php

/**
 * public/index.php — Entry Point do TuskPress
 *
 * Ponto de entrada para requisições web.
 * Em modo Apache/Nginx clássico: executado a cada request.
 * Em modo Tusk Engine: o kernel é persistente, apenas handle() é chamado.
 */

declare(strict_types=1);

use Tusk\Web\Http\Request;

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

// Carrega o kernel (em modo engine, já estará em memória)
/** @var \Tusk\Web\HttpKernel $kernel */
$kernel = require_once dirname(__DIR__) . '/bootstrap/app.php';

// Cria o request a partir das superglobais
$request = Request::fromGlobals();

// Processa e emite a resposta
$response = $kernel->handle($request);
$response->send();
