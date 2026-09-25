<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelistsenum\ZugferdPaymentMeans;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\AmountCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Document\DocumentConfiguration;
use Shopwell\Core\Checkout\Document\DocumentException;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'ZUGFeRD XML is produced by the DocumentV2 XML renderer without a public builder API.',
)]
class ZugferdBuilder
{
    private string $currentDocumentType;

    /**
     * @internal
     */
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected AmountCalculator $calculator
    ) {
    }

    public function buildDocument(
        OrderEntity $order,
        DocumentConfiguration $config,
        Context $context,
    ): string {
        return $this->build($order, $config, $context, ZugferdInvoiceType::INVOICE);
    }

    /**
     * @param array<string, mixed>|null $invoiceReference
     */
    public function buildDocumentWithType(
        OrderEntity $order,
        DocumentConfiguration $config,
        Context $context,
        string $documentType,
        ?array $invoiceReference = null,
    ): string {
        return $this->build($order, $config, $context, $documentType, $invoiceReference);
    }

    protected function addLineItems(ZugferdDocument $document, ?OrderLineItemCollection $lineItems, string $parentPosition = ''): self
    {
        if (!$lineItems) {
            return $this;
        }

        foreach ($lineItems as $lineItem) {
            $this->matchByType($document, $lineItem, $parentPosition);
            $this->addLineItems($document, $lineItem->getChildren(), $lineItem->getPosition() . '-');
        }

        return $this;
    }

    protected function matchByType(ZugferdDocument $document, OrderLineItemEntity $lineItem, string $parentPosition = ''): void
    {
        match ($lineItem->getType()) {
            LineItem::PRODUCT_LINE_ITEM_TYPE, LineItem::CUSTOM_LINE_ITEM_TYPE => $document->withProductLineItem($lineItem, $parentPosition),
            LineItem::PROMOTION_LINE_ITEM_TYPE => $document->withDiscountItem($lineItem),
            LineItem::CREDIT_LINE_ITEM_TYPE => $this->handleCreditLineItem($document, $lineItem, $parentPosition),
            default => null,
        };

        $this->eventDispatcher->dispatch(new ZugferdInvoiceItemAddedEvent($document, $lineItem, $parentPosition), 'zugferd-item-added.' . $lineItem->getType());
    }

    /**
     * @param array<string, mixed>|null $invoiceReference
     */
    private function build(
        OrderEntity $order,
        DocumentConfiguration $config,
        Context $context,
        string $documentType,
        ?array $invoiceReference = null,
    ): string {
        $this->currentDocumentType = $documentType;

        $billingAddress = $order->getAddresses()?->get($order->getBillingAddressId());
        if (!$billingAddress) {
            throw DocumentException::generationError('Billing address not found');
        }

        $customer = $order->getOrderCustomer();
        if (!$customer) {
            throw DocumentException::generationError('Customer not found');
        }

        $deliveryDate = $order->getPrimaryOrderDelivery()?->getShippingDateLatest();
        $transaction = $order->getPrimaryOrderTransaction();

        if (!Feature::isActive('v6.8.0.0')) {
            $transaction = $order->getTransactions()?->last();
            $deliveryDate = $order->getDeliveries()?->first()?->getShippingDateLatest()
                ?? $order->getPrimaryOrderDelivery()?->getShippingDateLatest();
        }

        if ($deliveryDate instanceof \DateTimeImmutable) {
            $deliveryDate = \DateTime::createFromImmutable($deliveryDate);
        }

        $taxStatus = $order->getTaxStatus() ?? $order->getPrice()->getTaxStatus();
        $document = (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), $taxStatus === CartPrice::TAX_STATE_GROSS))
            ->withBuyerInformation($customer, $billingAddress)
            ->withSellerInformation($config)
            ->withDocumentInformation($config->getDocumentDate() ?? 'now', $config->getDocumentNumber() ?? '', $order->getCurrency()?->getIsoCode() ?? '', $documentType)
            ->withDelivery($order->getDeliveries() ?? new OrderDeliveryCollection())
            ->withTaxes($order->getPrice())
            ->withBuyerReference($order->getOrderNumber() ?? '');

        if ($deliveryDate !== null) {
            $document->withDocumentSupplyChainEvent($deliveryDate);
        }

        if ($invoiceReference !== null && isset($invoiceReference['documentNumber'], $invoiceReference['config']['documentDate'])) {
            $document->withInvoiceReference(
                $invoiceReference['documentNumber'],
                new \DateTime($invoiceReference['config']['documentDate']),
            );
        }

        if ($order->getAmountTotal() < 0.0) {
            $document->allowNegativeProductLineItems();
        }

        $this->addLineItems($document, $order->getLineItems());

        if ($transaction !== null) {
            if ($transaction->getStateMachineState()?->getTechnicalName() === 'paid') {
                $document->withPaidAmount($order->getAmountTotal());
            }

            $paymentMethod = $transaction->getPaymentMethod();

            if ($paymentMethod !== null) {
                $this->addPaymentInfo($document, $config, $paymentMethod);
            }
        }

        $this->eventDispatcher->dispatch(new ZugferdInvoiceGeneratedEvent($document, $order, $config, $context));

        return $document->getContent($order, $this->calculator);
    }

    private function handleCreditLineItem(ZugferdDocument $document, OrderLineItemEntity $lineItem, string $parentPosition = ''): void
    {
        if ($lineItem->getType() !== LineItem::CREDIT_LINE_ITEM_TYPE) {
            return;
        }

        if ($this->currentDocumentType === ZugferdInvoiceType::CREDITNOTE) {
            $document->withProductLineItem($lineItem, $parentPosition);
        } else {
            $document->withDiscountItem($lineItem);
        }
    }

    private function addPaymentInfo(ZugferdDocument $document, DocumentConfiguration $config, PaymentMethodEntity $paymentMethod): void
    {
        if ($paymentMethod->getTechnicalName() === 'payment_cashpayment') {
            $document->getBuilder()->addDocumentPaymentMean(
                typeCode: (string) ZugferdPaymentMeans::UNTDID_4461_10->value,
                information: $paymentMethod->getName()
            );
        } elseif ($paymentMethod->getTechnicalName() === 'payment_invoicepayment' || $paymentMethod->getTechnicalName() === 'payment_prepayment') {
            $document->getBuilder()->addDocumentPaymentMean(
                typeCode: (string) ZugferdPaymentMeans::UNTDID_4461_30->value,
                information: $paymentMethod->getName(),
                payeeIban: $config->getBankIban(),
                payeeBic: $config->getBankBic()
            );
        }
    }
}
