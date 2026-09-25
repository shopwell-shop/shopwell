<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Document\Zugferd;

use Shopwell\Core\Checkout\Cart\Price\AmountCalculator;
use Shopwell\Core\Checkout\Document\DocumentException;
use Shopwell\Core\Checkout\Document\Zugferd\ZugferdDocument;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class ZugferdDocumentMock extends ZugferdDocument
{
    public function getDomContent(OrderEntity $order, ?AmountCalculator $calculator): \DOMDocument
    {
        try {
            $this->getContent($order, $calculator);
        } catch (DocumentException) {
        }

        return $this->zugferdBuilder->getContentAsDomDocument();
    }
}
