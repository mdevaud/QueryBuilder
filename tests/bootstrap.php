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

// The core bootstrap (autoloaded with the vendor) derives THELIA_ROOT from its own
// location, which is vendor/thelia/ in a shop installed by Composer: the shop defines
// the constants in its root bootstrap.php before loading the vendor, so do the same.
$shopBootstrap = dirname($autoload, 2) . '/bootstrap.php';

if (is_file($shopBootstrap)) {
    require $shopBootstrap;
}

require $autoload;

// The shop kernel reads its parameters from the environment (DEFAULT_URI, the database):
// load the shop .env the way the shop test bootstrap does. In test mode Dotenv skips
// .env.local by design, where the database access of a development shop usually lives:
// bridge the DATABASE_* variables from it when nothing else defined them.
$shopEnvFile = dirname($autoload, 2) . '/.env';

if (is_file($shopEnvFile) && class_exists(\Symfony\Component\Dotenv\Dotenv::class)) {
    (new \Symfony\Component\Dotenv\Dotenv())->bootEnv($shopEnvFile);

    $shopEnvLocalFile = $shopEnvFile . '.local';

    if (empty($_SERVER['DATABASE_HOST']) && is_file($shopEnvLocalFile)) {
        $localVariables = (new \Symfony\Component\Dotenv\Dotenv())->parse((string) file_get_contents($shopEnvLocalFile));

        foreach (['DATABASE_HOST', 'DATABASE_PORT', 'DATABASE_NAME', 'DATABASE_USER', 'DATABASE_PASSWORD'] as $key) {
            if (isset($localVariables[$key]) && empty($_SERVER[$key])) {
                $_SERVER[$key] = $_ENV[$key] = $localVariables[$key];
            }
        }
    }
}

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
