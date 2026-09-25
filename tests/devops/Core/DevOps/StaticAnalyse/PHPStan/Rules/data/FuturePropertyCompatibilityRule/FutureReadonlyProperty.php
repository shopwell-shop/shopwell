<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyCompatibilityRule;

use Shopwell\Core\Framework\Deprecation\BCChange\BecomesReadonly;

class FutureReadonlyProperty
{
    #[BecomesReadonly(version: 'v6.8.0')]
    protected string $value = 'value';

    public function ownAssignment(): void
    {
        $this->value = 'value';
    }
}
