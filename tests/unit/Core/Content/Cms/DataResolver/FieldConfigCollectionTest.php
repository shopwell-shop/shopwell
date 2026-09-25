<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FieldConfigCollection::class)]
class FieldConfigCollectionTest extends TestCase
{
    public function testAddKeysTheConfigByItsName(): void
    {
        $config = new FieldConfig('title', FieldConfig::SOURCE_STATIC, 'Hello');
        $collection = new FieldConfigCollection();

        $collection->add($config);

        static::assertSame($config, $collection->get('title'));
    }

    public function testSetIgnoresTheGivenKeyInFavourOfTheName(): void
    {
        $config = new FieldConfig('title', FieldConfig::SOURCE_STATIC, 'Hello');
        $collection = new FieldConfigCollection();

        $collection->set('something-else', $config);

        static::assertNull($collection->get('something-else'));
        static::assertSame($config, $collection->get('title'));
    }

    public function testApiAlias(): void
    {
        static::assertSame('cms_data_resolver_field_config_collection', (new FieldConfigCollection())->getApiAlias());
    }
}
