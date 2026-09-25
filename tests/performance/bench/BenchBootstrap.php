<?php declare(strict_types=1);

namespace Shopwell\Tests\Bench;

require __DIR__ . '/../../../src/Core/TestBootstrapper.php';

use Shopwell\Core\TestBootstrapper;

(new TestBootstrapper())
    ->setForceInstall(false)
    ->setPlatformEmbedded(false)
    ->bootstrap();
