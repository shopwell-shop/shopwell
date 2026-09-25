<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetGroupScopeDiscountPackager;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SetGroupScopeDiscountPackager::class)]
class SetGroupScopeDiscountPackagerTest extends TestCase
{
    public function testFormatRuleCollection(): void
    {
        $builder = static::createStub(LineItemGroupBuilder::class);
        $builder
            ->method('findGroupPackages')
            ->willReturnCallback(static function (array $groupDefinitions) {
                static::assertCount(4, $groupDefinitions);
                static::assertInstanceOf(LineItemGroupDefinition::class, $groupDefinitions[0]);
                static::assertInstanceOf(LineItemGroupDefinition::class, $groupDefinitions[1]);
                static::assertInstanceOf(LineItemGroupDefinition::class, $groupDefinitions[2]);
                static::assertInstanceOf(LineItemGroupDefinition::class, $groupDefinitions[3]);

                $array = $groupDefinitions[0]->getRules();
                $collection = $groupDefinitions[1]->getRules();
                $null = $groupDefinitions[2]->getRules();
                $unset = $groupDefinitions[3]->getRules();

                static::assertCount(1, $array);
                static::assertSame('Rule Name', $array->first()?->getName());
                static::assertCount(0, $collection);
                static::assertCount(0, $null);
                static::assertCount(0, $unset);

                return new LineItemGroupBuilderResult();
            });

        $payload = [
            'discountScope' => 'scope',
            'discountType' => 'type',
            'setGroups' => [
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'key',
                    'value' => 10,
                    'sorterKey' => 'ASC',
                    'rules' => [['id' => Uuid::randomHex(), 'name' => 'Rule Name']],
                ],
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'key',
                    'value' => 10,
                    'sorterKey' => 'ASC',
                    'rules' => new RuleCollection(),
                ],
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'key',
                    'value' => 10,
                    'sorterKey' => 'ASC',
                    'rules' => null,
                ],
                [
                    'groupId' => Uuid::randomHex(),
                    'packagerKey' => 'key',
                    'value' => 10,
                    'sorterKey' => 'ASC',
                ],
            ],
        ];

        (new SetGroupScopeDiscountPackager($builder))->getMatchingItems(
            new DiscountLineItem('label', new AbsolutePriceDefinition(10), $payload, null),
            new Cart('token'),
            static::createStub(SalesChannelContext::class)
        );
    }
}
