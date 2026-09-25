<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoStaticRuntimeExceptionReturnRuleTest;

use Shopwell\Core\Framework\HttpException;

class DummyException extends HttpException
{
    public static function runtimeException(): \RuntimeException
    {
        return new \RuntimeException('bad');
    }

    public static function runtimeExceptionOrSelf(): self|\RuntimeException
    {
        return new \RuntimeException('bad');
    }

    public static function self(): self
    {
        return new self(200, 'ERROR_CODE', 'message');
    }

    public static function invalidArgumentOrSelf(): self|\InvalidArgumentException
    {
        return new self(200, 'ERROR_CODE', 'message');
    }
}
