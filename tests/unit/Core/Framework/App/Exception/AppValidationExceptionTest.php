<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Exception\AppValidationException;
use Shopwell\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopwell\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppValidationException::class)]
class AppValidationExceptionTest extends TestCase
{
    #[TestDox('the message aggregates every validation error')]
    public function testMessageAggregatesErrors(): void
    {
        $exception = new AppValidationException('MyApp', new ErrorCollection([
            new MissingPermissionError(['product:read']),
            new MissingPermissionError(['order:read']),
        ]));

        static::assertStringContainsString('The app "MyApp" is invalid:', $exception->getMessage());
        static::assertStringContainsString("The following permissions are missing:\n- product:read", $exception->getMessage());
        static::assertStringContainsString("The following permissions are missing:\n- order:read", $exception->getMessage());
    }
}
