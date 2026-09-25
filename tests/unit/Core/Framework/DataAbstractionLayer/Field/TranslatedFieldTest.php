<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TranslatedField::class)]
class TranslatedFieldTest extends TestCase
{
    public function testInstantiate(): void
    {
        $field = new TranslatedField('name');

        static::assertFalse($field->useForSorting());

        $field = new TranslatedField(
            'name',
            true,
        );

        static::assertSame('name', $field->getPropertyName());
        static::assertTrue($field->useForSorting());
    }
}
