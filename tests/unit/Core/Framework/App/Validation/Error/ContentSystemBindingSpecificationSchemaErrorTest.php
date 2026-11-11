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
    public function testAggregatesAllViolationsInTheirOriginalOrder(): void
    {
        $error = new ContentSystemBindingSpecificationSchemaError(['first.binding: missing loader', 'second.binding: invalid input']);

        static::assertSame("The following content-system binding specifications are invalid:\n- first.binding: missing loader\n- second.binding: invalid input", $error->getMessage());
    }

    public function testSingleViolationIsNotLostOrDuplicated(): void
    {
        $error = new ContentSystemBindingSpecificationSchemaError(['binding: invalid type']);

        static::assertSame("The following content-system binding specifications are invalid:\n- binding: invalid type", $error->getMessage());
    }

    public function testProvidesTheGenericValidationErrorContract(): void
    {
        $error = new ContentSystemBindingSpecificationSchemaError(['Invalid binding']);

        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
    }
}
