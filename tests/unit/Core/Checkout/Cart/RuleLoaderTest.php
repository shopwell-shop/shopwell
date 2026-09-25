<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopwell\Core\Checkout\Cart\RuleLoader;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Content\Rule\RuleDefinition;
use Shopwell\Core\Content\Rule\RuleEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RuleLoader::class)]
class RuleLoaderTest extends TestCase
{
    public function testDecorated(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(RuleLoader::class));
        $ruleRepository = new StaticEntityRepository([], new RuleDefinition());
        $ruleLoader = new RuleLoader($ruleRepository);
        $ruleLoader->getDecorated();
    }

    public function testLoad(): void
    {
        $ruleRepository = new StaticEntityRepository(
            [
                function (Criteria $criteria): RuleCollection {
                    static::assertSame(500, $criteria->getLimit());
                    static::assertSame('cart-rule-loader::load-rules', $criteria->getTitle());
                    static::assertCount(2, $criteria->getSorting());
                    static::assertInstanceOf(EqualsFilter::class, $criteria->getFilters()[0]);
                    static::assertSame('invalid', $criteria->getFilters()[0]->getField());
                    static::assertFalse($criteria->getFilters()[0]->getValue());

                    return $this->getRuleCollection(500);
                },
                $this->getRuleCollection(1),
            ],
            new RuleDefinition(),
        );

        $ruleLoader = new RuleLoader($ruleRepository);
        $rules = $ruleLoader->load(Context::createDefaultContext());

        static::assertCount(501, $rules);
    }

    public function testLoadWithoutSecondResult(): void
    {
        $ruleRepository = new StaticEntityRepository(
            [
                function (Criteria $criteria): RuleCollection {
                    static::assertSame(500, $criteria->getLimit());
                    static::assertSame('cart-rule-loader::load-rules', $criteria->getTitle());
                    static::assertCount(2, $criteria->getSorting());
                    static::assertInstanceOf(EqualsFilter::class, $criteria->getFilters()[0]);
                    static::assertSame('invalid', $criteria->getFilters()[0]->getField());
                    static::assertFalse($criteria->getFilters()[0]->getValue());

                    return $this->getRuleCollection(500);
                },
                $this->getRuleCollection(0),
            ],
            new RuleDefinition(),
        );

        $ruleLoader = new RuleLoader($ruleRepository);
        $rules = $ruleLoader->load(Context::createDefaultContext());

        static::assertCount(500, $rules);
    }

    private function getRuleCollection(int $count): RuleCollection
    {
        $ruleCollection = new RuleCollection();

        for ($i = 0; $i < $count; ++$i) {
            $rule = new RuleEntity();
            $rule->setId(Uuid::randomHex());
            $rule->setPayload(new AlwaysValidRule());
            $ruleCollection->add($rule);
        }

        return $ruleCollection;
    }
}
