<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Garan;

use Shopwell\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopwell\Core\Content\Product\Cart\ProductCartProcessor;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stores the GARAN label values of their product in the payload of order line items that did not get them from the
 * cart ({@see ProductCartProcessor}), so every order shows the label it was placed with.
 *
 * @internal
 */
#[Package('inventory')]
class GaranLabelOrderSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly GaranLabelResolver $resolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'storeLabels',
        ];
    }

    public function storeLabels(CartConvertedEvent $event): void
    {
        $order = $event->getConvertedCart();
        $productIds = [];

        foreach ($order['lineItems'] ?? [] as $key => $lineItem) {
            if (!\is_string($lineItem['productId'] ?? null)) {
                continue;
            }

            // a new order keeps the label its cart showed, an edited order keeps the label it was placed with
            if (\array_key_exists(GaranLabelResolver::PAYLOAD_KEY, $lineItem['payload'] ?? [])) {
                continue;
            }

            $productIds[$key] = $lineItem['productId'];
        }

        // empty criteria would load every product
        if ($productIds === []) {
            return;
        }

        $criteria = new Criteria(array_values(array_unique($productIds)));
        $criteria->addAssociation('manufacturer');

        // variants inherit the guarantee and manufacturer
        $products = $event->getContext()->enableInheritance(
            fn (Context $context): ProductCollection => $this->productRepository->search($criteria, $context)->getEntities()
        );

        foreach ($productIds as $key => $productId) {
            $product = $products->get($productId);

            $order['lineItems'][$key]['payload'][GaranLabelResolver::PAYLOAD_KEY] = $product !== null ? $this->resolver->resolveSnapshot($product) : null;
        }

        $event->setConvertedCart($order);
    }
}
