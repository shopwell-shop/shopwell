<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules;

class ExcludedNamespace
{
    public function throwException(): void
    {
        throw new \RuntimeException('This exception should not be reported');
    }
}
