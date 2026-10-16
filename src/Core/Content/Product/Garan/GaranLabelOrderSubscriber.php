<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Garan;

use Shopwell\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stores the GARAN label values in the payload of the order's product line items, so the order keeps showing the
 * label it was placed with. Every order conversion passes here, whichever cart processors calculated the cart.
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

        // an edited order keeps the GARAN label it was placed with
        $keepStoredLabels = $event->getCart()->getBehavior()?->hasPermission(CheckoutPermissions::SKIP_PRODUCT_RECALCULATION) ?? false;

        $productIds = [];

        foreach ($order['lineItems'] ?? [] as $key => $lineItem) {
            if (!\is_string($lineItem['productId'] ?? null)) {
                continue;
            }

            if ($keepStoredLabels && \array_key_exists('garanLabel', $lineItem['payload'] ?? [])) {
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

            if ($product === null) {
                continue;
            }

            $order['lineItems'][$key]['payload']['garanLabel'] = $this->resolver->resolveSnapshot($product);
        }

        $event->setConvertedCart($order);
    }
}
