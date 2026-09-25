<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoStaticRuntimeExceptionReturnRuleTest;

class NotException
{
    public static function create(): \RuntimeException
    {
        return new \RuntimeException('ok');
    }
}
