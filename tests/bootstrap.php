<?php

declare(strict_types=1);

// The module classes lean on the core (Symfony components, Thelia models): the autoloader
// of the shop the module is installed in provides them, or the module's own vendor after a
// composer install at its root (unit suite only). THELIA_VENDOR_AUTOLOAD points at another
// vendor/autoload.php. A checkout linked into a shop by symlink resolves __DIR__ to its real
// path, so it falls through to its own vendor: set THELIA_VENDOR_AUTOLOAD to the shop's for
// the integration suite.
$candidates = array_filter([
    getenv('THELIA_VENDOR_AUTOLOAD') ?: null,
    // local/modules/QueryBuilder
    __DIR__ . '/../../../../vendor/autoload.php',
    // vendor/thelia/modules/QueryBuilder
    __DIR__ . '/../../../../../vendor/autoload.php',
    // composer install at the module root
    dirname(__DIR__) . '/vendor/autoload.php',
]);

$autoload = null;

foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    fwrite(\STDERR, "No vendor/autoload.php found: install the module in a shop, run composer install at the module root, or set THELIA_VENDOR_AUTOLOAD.\n");
    exit(1);
}

require $autoload;

// The module and its tests, when the shop autoloader does not map them already
// (module checked out outside local/modules). Longest prefix first.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'QueryBuilder\\Tests\\' => __DIR__ . '/',
        'QueryBuilder\\' => dirname(__DIR__) . '/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $directory . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});
