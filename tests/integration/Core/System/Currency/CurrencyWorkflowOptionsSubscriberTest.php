<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Currency;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowFormOptionsEvent;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Currency\CurrencyWorkflowOptionsSubscriber;

/**
 * @internal
 */
#[Package('framework')]
class CurrencyWorkflowOptionsSubscriberTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCurrencyOptionsContainOnlyConfiguredCodesAndLabels(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<CurrencyCollection> $repository */
        $repository = static::getContainer()->get('currency.repository');
        $options = static::getContainer()->get(WorkflowInputSchema::class)->options('core:currency', $context);
        $currencies = $repository->search(new Criteria(), $context)->getEntities();
        $codes = array_values(array_map(static fn (CurrencyEntity $currency): string => $currency->getIsoCode(), $currencies->getElements()));
        sort($codes);
        static::assertSame($codes, array_column($options, 'value'));
        foreach ($options as $option) {
            static::assertSame(['value', 'label'], array_keys($option));
            static::assertStringContainsString($option['value'], $option['label']);
        }
        static::assertSame([WorkflowFormOptionsEvent::class => 'resolve'], CurrencyWorkflowOptionsSubscriber::getSubscribedEvents());
    }

    public function testOtherOptionSourcesAreLeftForTheirOwnSubscribers(): void
    {
        $event = new WorkflowFormOptionsEvent('inventory:warehouse', Context::createDefaultContext());
        (new CurrencyWorkflowOptionsSubscriber(static::getContainer()->get('currency.repository')))->resolve($event);
        static::assertNull($event->options);
    }
}
