<?php

declare(strict_types=1);

/**
 * PROMIS - Procurement Management Information System
 * University of Science and Technology, Dedicated (USTED)
 *
 * Front Controller & Public Entry Point
 */

require_once dirname(__DIR__) . '/core/autoload.php';

use Promis\Core\App;

// Bootstrap application with base directory
App::bootstrap(dirname(__DIR__));

// Execute request lifecycle
App::run();
