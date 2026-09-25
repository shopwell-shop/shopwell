<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AutoIncrementField::class)]
class AutoIncrementFieldTest extends TestCase
{
    public function testConstructorConfiguresStorageAndWriteProtection(): void
    {
        $field = new AutoIncrementField();

        static::assertSame('auto_increment', $field->getStorageName());
        static::assertSame('autoIncrement', $field->getPropertyName());
        static::assertTrue($field->is(WriteProtected::class));
    }
}
