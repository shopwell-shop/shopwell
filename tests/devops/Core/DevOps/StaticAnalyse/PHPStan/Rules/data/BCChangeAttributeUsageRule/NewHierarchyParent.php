<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule;

class NewHierarchyParent
{
    public function providedByNewParent(): void
    {
    }

    protected function providedByProtectedNewParent(): void
    {
    }
}
