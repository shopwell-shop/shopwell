<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Administration\Elasticsearch;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopwell\Elasticsearch\Product\ElasticsearchProductDefinition;

/**
 * @internal
 */
#[Package('framework')]
class StreamConditionPropertyMappingTest extends TestCase
{
    use KernelTestBehaviour;

    private SalesChannelProductDefinition $productDefinition;

    private AbstractElasticsearchDefinition $elasticDefinition;

    protected function setUp(): void
    {
        $this->productDefinition = static::getContainer()->get(SalesChannelProductDefinition::class);

        $this->elasticDefinition = static::getContainer()->get(ElasticsearchProductDefinition::class);
    }

    public function testMappingHasConditionField(): void
    {
        $js = file_get_contents(__DIR__ . '/../../../../src/Administration/Resources/app/administration/src/app/service/product-stream-condition.service.js');

        if (!$js) {
            static::fail('product-stream-condition.service.js not found');
        }

        $regex = '/product:(.*?)\[(.*?)]/s';
        preg_match($regex, $js, $matches);

        if ($matches === [] || $matches[2] === '') {
            static::fail('could not find product properties in product-stream-condition.service.js');
        }

        $json = \sprintf('[%s]', rtrim(trim(str_replace(['\'', \PHP_EOL], ['"', ''], $matches[2])), ','));
        $properties = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($properties)) {
            static::fail('could not extract product properties from product-stream-condition.service.js');
        }

        $mapping = $this->elasticDefinition->getMapping(Context::createDefaultContext());
        $mappedProperties = array_keys($mapping['properties']);

        $unmappedProperties = array_filter($properties, function (string $property) use ($mappedProperties): bool {
            $field = $this->productDefinition->getField($property);

            if ($field instanceof AssociationField || $field instanceof CheapestPriceField || $field instanceof PriceField) {
                return false;
            }

            return !\in_array($property, $mappedProperties, true);
        });

        static::assertCount(
            0,
            $unmappedProperties,
            \sprintf(
                'The following product fields available for filters in product streams are not mapped for elasticsearch: %s',
                implode(', ', $unmappedProperties)
            )
        );
    }
}
