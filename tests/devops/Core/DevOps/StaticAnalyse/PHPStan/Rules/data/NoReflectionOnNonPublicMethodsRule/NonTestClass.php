<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReflectionOnNonPublicMethodsRule;

use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\Fixture\ReflectionTarget;

/**
 * @internal
 */
class NonTestClass
{
    public function run(): void
    {
        // NOT flagged: the rule only applies to test classes
        $method = new \ReflectionMethod(ReflectionTarget::class, 'hiddenCalculation');

        $method->invoke(new ReflectionTarget());
    }
}
