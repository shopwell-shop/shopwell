<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoKernelInUnitTestsRule;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;

/**
 * @internal
 */
class DirectKernelCases extends TestCase
{
    use KernelTestBehaviour;

    public function testOne(): void
    {
        static::assertTrue(true);
    }
}

/**
 * @internal
 */
class NestedBehaviourCases extends TestCase
{
    use SalesChannelApiTestBehaviour;

    public function testOne(): void
    {
        static::assertTrue(true);
    }
}

/**
 * @internal
 */
class LocalTraitCases extends TestCase
{
    use LocalFixtureBehaviour;

    public function testOne(): void
    {
        static::assertTrue(true);
    }
}

/**
 * @internal
 */
class LifecycleManagerCases extends TestCase
{
    public function testOne(): void
    {
        $kernel = KernelLifecycleManager::getKernel();

        static::assertNotNull($kernel);
    }
}

/**
 * @internal
 */
class HarmlessCases extends TestCase
{
    use EnvTestBehaviour;

    public function testOne(): void
    {
        static::assertTrue(true);
    }
}
