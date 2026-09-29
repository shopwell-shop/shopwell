<?php declare(strict_types=1);

namespace Shopwell\Core;

use Composer\Autoload\ClassLoader;
use PHPUnit\TextUI\Configuration\SourceFilter;

require __DIR__ . '/TestBootstrapper.php';

/*
 * Some PHP builds (observed on PHP 8.5 with ICU 78) report an EMPTY default locale from
 * locale_get_default(). Symfony Intl then tries to read a resource bundle named ".php" and
 * fails on every locale lookup (Locales::exists(), NumberFormatter creation, ...). Fall back
 * to "en" only when the runtime cannot determine any default locale at all.
 *
 * Static analysis sees a non-empty string here, which is exactly the case this guard covers.
 */
// @phpstan-ignore-next-line
if (\function_exists('locale_get_default') && locale_get_default() === '') {
    locale_set_default('en');
}

(new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->setEnableCommercial()
    ->bootstrap();

/*
 * The Danger rules (src/Core/DevOps/StaticAnalyze/Danger) type against the danger-php package
 * from vendor-bin (run `composer install -d vendor-bin/danger-php` once to make their unit tests
 * runnable). Only its own Danger\ namespace is registered — appended, never the package's full
 * vendor autoloader, which would take priority over the root vendor for the symfony packages
 * both ship.
 */
$dangerSrc = \dirname(__DIR__, 2) . '/vendor-bin/danger-php/vendor/shyim/danger-php/src';
if (is_dir($dangerSrc)) {
    $dangerClassLoader = new ClassLoader();
    $dangerClassLoader->addPsr4('Danger\\', $dangerSrc);
    $dangerClassLoader->register();
}

/*
 * Eagerly build PHPUnit's source map (full <source> tree traversal). It is otherwise built
 * lazily while classifying the FIRST triggered deprecation/notice — billing seconds (native fs)
 * to minutes (Docker bind mount) to whichever test happens to trigger it, which poisons the
 * slow-test-detector output with a wandering false entry. @internal API, so fail soft.
 * Skipped outside a PHPUnit process (e.g. `composer init:testdb` running this script directly):
 * there is no loaded configuration to build the map from.
 */
if (\defined('PHPUNIT_COMPOSER_INSTALL')) {
    try {
        if (!class_exists(SourceFilter::class)) {
            throw new \RuntimeException('class no longer exists — the pre-warm needs to be ported to the current PHPUnit internals');
        }

        SourceFilter::instance();
    } catch (\Throwable $e) {
        // pre-warming is an optimization only — warn (the stall would silently return), never break the suite
        fwrite(\STDERR, \sprintf('Could not pre-warm the PHPUnit source map (%s): %s%s', SourceFilter::class, $e->getMessage(), \PHP_EOL));
    }
}
