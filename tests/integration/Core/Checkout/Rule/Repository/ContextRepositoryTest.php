<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Rule\Repository;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\AndRule;
use Shopwell\Core\Framework\Rule\Container\OrRule;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Currency\Rule\CurrencyRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ContextRepositoryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('rule.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testWriteRuleWithObject(): void
    {
        $id = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $currencyId2 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'type' => (new AndRule())->getName(),
                    'children' => [
                        [
                            'type' => (new OrRule())->getName(),
                            'children' => [
                                [
                                    'type' => (new CurrencyRule())->getName(),
                                    'value' => [
                                        'currencyIds' => [
                                            $currencyId,
                                            $currencyId2,
                                        ],
                                        'operator' => CurrencyRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], $this->context);

        $rules = $this->repository->search(new Criteria([$id]), $this->context)->getEntities();

        $currencyRule = (new CurrencyRule())->assign(['currencyIds' => [$currencyId, $currencyId2]]);

        $entity = $rules->get($id);
        static::assertNotNull($entity);

        static::assertEquals(
            new AndRule([new AndRule([new OrRule([$currencyRule])])]),
            $entity->getPayload()
        );
    }
}
