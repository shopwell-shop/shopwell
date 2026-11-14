<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Approval\WorkflowFormOptionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Currency\CurrencyWorkflowOptionsSubscriberTest
 */
#[Package('framework')]
final readonly class CurrencyWorkflowOptionsSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<CurrencyCollection> $currencies
     */
    public function __construct(private EntityRepository $currencies)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowFormOptionsEvent::class => 'resolve'];
    }

    public function resolve(WorkflowFormOptionsEvent $event): void
    {
        if ($event->source !== 'core:currency') {
            return;
        }
        $criteria = (new Criteria())->setLimit(500)->addSorting(new FieldSorting('isoCode'));
        $event->options = [];
        foreach ($this->currencies->search($criteria, $event->context)->getEntities() as $currency) {
            $code = $currency->getIsoCode();
            $event->options[] = ['value' => $code, 'label' => ($currency->getName() ?: $code) . ' · ' . $code];
        }
    }
}
