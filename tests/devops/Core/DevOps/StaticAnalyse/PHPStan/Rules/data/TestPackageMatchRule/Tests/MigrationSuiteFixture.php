<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Covered\CheckoutService;

#[Package('framework')]
#[CoversClass(CheckoutService::class)]
class MigrationSuiteFixture extends TestCase
{
}
