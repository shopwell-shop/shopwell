<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\Fixture;

use Shopwell\Core\Framework\Log\Package;

/**
 * Fixture for the NoReflectionOnNonPublicMethodsRule test: a Shopwell class with every method
 * visibility. It lives in src so the rule's ReflectionProvider lookup resolves it through the
 * regular autoloader, which no class in a tests namespace can satisfy (the rule skips those).
 *
 * @internal
 */
#[Package('framework')]
class ReflectionTarget
{
    public function publicApi(): void
    {
    }

    public function touchHidden(): void
    {
        $this->hiddenCalculation();
    }

    protected function guardedStep(): void
    {
    }

    private function hiddenCalculation(): void
    {
        $this->guardedStep();
    }
}
