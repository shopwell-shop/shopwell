<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolResponseTraitRule;

class NotATool
{
    public function __invoke(): string
    {
        return '{}';
    }
}
