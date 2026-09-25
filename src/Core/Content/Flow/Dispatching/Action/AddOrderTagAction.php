<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Action;

use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Content\Flow\Dispatching\DelayableAction;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Event\OrderAware;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class AddOrderTagAction extends FlowAction implements DelayableAction
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(private readonly EntityRepository $orderRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.add.order.tag';
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
        if (!$flow->hasData(OrderAware::ORDER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(OrderAware::ORDER_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $orderId): void
    {
        if (!\array_key_exists('tagIds', $config) || array_keys($config['tagIds']) === []) {
            return;
        }

        $tagIds = array_keys($config['tagIds']);

        $tags = array_map(static fn ($tagId) => ['id' => $tagId], $tagIds);

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'tags' => $tags,
            ],
        ], $context);
    }
}
