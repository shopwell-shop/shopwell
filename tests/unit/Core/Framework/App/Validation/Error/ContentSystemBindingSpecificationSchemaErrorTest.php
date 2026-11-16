<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemBindingSpecificationSchemaError::class)]
class ContentSystemBindingSpecificationSchemaErrorTest extends TestCase
{
    public function testReportsAggregatedViolationsAsAnAppValidationError(): void
    {
        $error = new ContentSystemBindingSpecificationSchemaError(['Missing binding name', 'Invalid source']);

        static::assertSame(
            "The following content-system binding specifications are invalid:\n- Missing binding name\n- Invalid source",
            $error->getMessage(),
        );
        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());

        $exception = AppException::validationFailedFromError($error);
        static::assertSame(AppException::VALIDATION_FAILED, $exception->getErrorCode());
        static::assertSame($error->getMessage(), $exception->getMessage());
    }
}
