<?php
/**
 * Constants needed by PHPStan to analyse the plugin without bootstrapping
 * WordPress or running the main plugin file.
 *
 * @package Estimate
 */

declare(strict_types=1);

namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }
    if (! defined('COOKIEPATH')) {
        define('COOKIEPATH', '/');
    }
    if (! defined('COOKIE_DOMAIN')) {
        define('COOKIE_DOMAIN', '');
    }
    if (! defined('ESTIMATE_DIR')) {
        define('ESTIMATE_DIR', '/tmp/estimate/');
    }
    if (! defined('ESTIMATE_URL')) {
        define('ESTIMATE_URL', 'https://example.test/wp-content/plugins/estimate/');
    }
}

namespace Estimate {
    if (! defined('Estimate\\VERSION')) {
        define('Estimate\\VERSION', '0.1.0');
    }
    if (! defined('Estimate\\PLUGIN_FILE')) {
        define('Estimate\\PLUGIN_FILE', '/tmp/estimate/estimate.php');
    }
}
