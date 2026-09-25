<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cms\Aggregate\CmsSlot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\CmsException;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsSlotEntity::class)]
class CmsSlotEntityTest extends TestCase
{
    public function testFieldConfigIsBuiltFromTheTranslatedConfigAndCached(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setTranslated(['config' => [
            'content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'Hello'],
        ]]);

        $config = $slot->getFieldConfig();

        static::assertCount(1, $config);
        $field = $config->get('content');
        static::assertInstanceOf(FieldConfig::class, $field);
        static::assertSame('Hello', $field->getValue());
        static::assertSame($config, $slot->getFieldConfig());
    }

    public function testFieldConfigRejectsANonStringSource(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setTranslated(['config' => [
            'content' => ['source' => 42, 'value' => 'Hello'],
        ]]);

        $this->expectException(CmsException::class);

        $slot->getFieldConfig();
    }
}
