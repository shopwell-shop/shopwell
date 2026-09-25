<?php

declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PropertyNativeTypeRule;

class PromotedPropertiesNotTypedWithoutDocBlock
{
    public function __construct(
        public $promotedStringPropertyNotTyped,
    ) {
    }
}
