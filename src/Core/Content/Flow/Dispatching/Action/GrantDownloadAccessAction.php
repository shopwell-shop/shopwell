<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Action;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Flow\Dispatching\DelayableAction;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\State;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Event\OrderAware;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class GrantDownloadAccessAction extends FlowAction implements DelayableAction
{
    /**
     * @param EntityRepository<OrderLineItemDownloadCollection> $orderLineItemDownloadRepository
     */
    public function __construct(private readonly EntityRepository $orderLineItemDownloadRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.grant.download.access';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(OrderAware::ORDER)) {
            return;
        }

        /** @var OrderEntity $order */
        $order = $flow->getData(OrderAware::ORDER);

        $this->update($flow->getContext(), $flow->getConfig(), $order);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, OrderEntity $order): void
    {
        if (!isset($config['value'])) {
            return;
        }

        $lineItems = $order->getLineItems();

        if (!$lineItems) {
            return;
        }

        $downloadIds = [];

        foreach ($lineItems->filterGoodsFlat() as $lineItem) {
            $isDigital = Feature::isActive('v6.8.0.0')
                ? $lineItem->getPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE) === ProductDefinition::TYPE_DIGITAL
                : (\in_array(State::IS_DOWNLOAD, $lineItem->getStates(), true)
                    || $lineItem->getPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE) === ProductDefinition::TYPE_DIGITAL);

            if (!$lineItem->getDownloads() || !$isDigital) {
                continue;
            }

            foreach ($lineItem->getDownloads() as $download) {
                $downloadIds[] = $download->getId();
                $download->setAccessGranted((bool) $config['value']);
            }
        }

        if ($downloadIds === []) {
            return;
        }

        $this->orderLineItemDownloadRepository->update(
            array_map(static fn (string $id): array => ['id' => $id, 'accessGranted' => $config['value']], array_unique($downloadIds)),
            $context
        );
    }
}
