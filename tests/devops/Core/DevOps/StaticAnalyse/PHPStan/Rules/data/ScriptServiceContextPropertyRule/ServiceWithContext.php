<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\ScriptServiceContextPropertyRule;

use Shopwell\Core\Framework\Context;

class ServiceWithContext
{
    public function __construct(private readonly Context $context)
    {
    }
}
