<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Product\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Extends a provider's product catalog before it is validated and frozen.
 */
#[Package('framework')]
final class PaymentProductsEvent extends Event
{
    /**
     * @param list<PaymentProduct> $products
     */
    public function __construct(public readonly string $providerName, private array $products)
    {
    }

    public function add(PaymentProduct $product): void
    {
        $this->products[] = $product;
    }

    public function addScene(string $productName, PaymentScene $scene): void
    {
        foreach ($this->products as $index => $product) {
            if ($product->technicalName === $productName) {
                $this->products[$index] = $product->withScenes([...$product->scenes, $scene]);

                return;
            }
        }
        throw PaymentProviderException::unknownChannel($productName);
    }

    /**
     * @return list<PaymentProduct>
     */
    public function products(): array
    {
        return $this->products;
    }
}
