<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyCompatibilityRule;

use Shopwell\Core\Framework\Deprecation\BCChange\PropertyTypeNarrowing;

class FutureNarrowedProperty
{
    #[PropertyTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected ?string $value = null;

    public function ownAssignment(): void
    {
        $this->value = null;
    }
}
