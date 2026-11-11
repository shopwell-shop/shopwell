<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Validation\Error\ContentSystemElementTypeSchemaError;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemElementTypeSchemaError::class)]
class ContentSystemElementTypeSchemaErrorTest extends TestCase
{
    #[TestDox('formats message with filename and reason')]
    public function testFormatsMessageWithFilenameAndReason(): void
    {
        $error = new ContentSystemElementTypeSchemaError('/path/to/types', 'YAML syntax invalid');

        static::assertSame('Invalid element type schema in "/path/to/types": YAML syntax invalid', $error->getMessage());
    }

    public function testProvidesTheGenericValidationErrorContract(): void
    {
        $error = new ContentSystemElementTypeSchemaError('/path/to/schema', 'Invalid schema');

        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
    }
}
