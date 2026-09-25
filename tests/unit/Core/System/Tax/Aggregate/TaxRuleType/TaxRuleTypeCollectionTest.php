<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Tax\Aggregate\TaxRuleType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeCollection;
use Shopwell\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TaxRuleTypeCollection::class)]
class TaxRuleTypeCollectionTest extends TestCase
{
    public function testGetByTechnicalName(): void
    {
        $entireCountry = new TaxRuleTypeEntity();
        $entireCountry->setUniqueIdentifier('entire-country');
        $entireCountry->setTechnicalName('entire_country');

        $collection = new TaxRuleTypeCollection([$entireCountry]);

        static::assertSame($entireCountry, $collection->getByTechnicalName('entire_country'));
        static::assertNull($collection->getByTechnicalName('zip_code_range'));
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('tax_rule_type_collection', (new TaxRuleTypeCollection())->getApiAlias());
    }
}
