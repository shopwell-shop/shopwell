<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Rule\IsNewsletterRecipientRule;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class IsNewsletterRecipientRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new IsNewsletterRecipientRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'isNewsletterRecipient' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }

    public function testExistingNewsletterSalesChannelIdMatchesToTrue(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->setNewsletterSalesChannelIds([Uuid::randomHex() => 'foo', Uuid::randomHex() => 'bar']);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext->method('getSalesChannelId')
            ->willReturn('foo');
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertTrue($isCompanyRule->match($scope));
    }

    public function testEmptyNewsletterSalesChannelIdsMatchesToFalse(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }

    public function testMissingNewsletterSalesChannelIdMatchesToFalse(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->setNewsletterSalesChannelIds([Uuid::randomHex() => 'bar']);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext->method('getSalesChannelId')
            ->willReturn('foo');
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }
}
