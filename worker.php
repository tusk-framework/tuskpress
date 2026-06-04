<?php

/**
 * worker.php — Tusk Native Engine Entry Point
 * 
 * This file is invoked by the Engine (Go) only ONCE when the container starts.
 * It keeps the application alive in memory and responds to requests via NDJSON.
 */

require_once __DIR__ . '/bootstrap/app.php'; // This already loads the autoload, wp-config and returns the HttpKernel

use Tusk\Runtime\Kernel;
use Tusk\Runtime\Adapters\NativeLoopAdapter;

// We retrieve the Container that was injected into the HttpKernel during bootstrap
$container = $kernel->getContainer();

// We inject the ready instance of HttpKernel into the Container so the Runtime can find it
// The instance() method now works thanks to the RuntimeCompiledContainer in app.php
$container->instance(\Tusk\Web\HttpKernel::class, $kernel);

// We create the master Runtime Kernel, plugged with the Native NDJSON Loop adapter
$runtimeKernel = new Kernel($container, new NativeLoopAdapter());

// We start the Loop. This method will block execution indefinitely.
$runtimeKernel->start();
