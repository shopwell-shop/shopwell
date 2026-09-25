<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UseCLIContextRule;

use Shopwell\Core\Framework\Context;

/**
 * @internal
 */
class NonRestrictedClass
{
    public function create(): void
    {
        Context::createDefaultContext();
    }
}
