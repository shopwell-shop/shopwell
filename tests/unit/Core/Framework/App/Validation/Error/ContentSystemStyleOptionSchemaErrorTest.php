<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Validation\Error\ContentSystemStyleOptionSchemaError;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemStyleOptionSchemaError::class)]
class ContentSystemStyleOptionSchemaErrorTest extends TestCase
{
    #[TestDox('formats the message from the filename and reason')]
    public function testFormatsMessageWithFilenameAndReason(): void
    {
        $error = new ContentSystemStyleOptionSchemaError('/path/to/style-options', 'YAML syntax invalid');

        static::assertSame('Invalid style option schema in "/path/to/style-options": YAML syntax invalid', $error->getMessage());
    }
}
