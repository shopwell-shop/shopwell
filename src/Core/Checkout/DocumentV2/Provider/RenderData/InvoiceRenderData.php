<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopwell\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopwell\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopwell\Core\Checkout\DocumentV2\Template\View\AllowanceChargeView;
use Shopwell\Core\Checkout\DocumentV2\Template\View\LineItemView;
use Shopwell\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopwell\Core\Checkout\DocumentV2\Template\View\PaymentMeansView;
use Shopwell\Core\Checkout\DocumentV2\Template\View\TaxBreakdownView;
use Shopwell\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopwell\Core\Framework\Log\Package;

/**
 * Invoice-specific render data: the `Template\View\*` structs shaped to the XRechnung schema for
 * the XML renderer, plus the fields the HTML templates read. Cross-cutting data (config, company,
 * display, document identity) lives in {@see DocumentMetaRenderData}.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class InvoiceRenderData extends AbstractRenderData
{
    /**
     * @param list<LineItemView> $lineItems
     * @param list<AllowanceChargeView> $allowanceCharges
     * @param list<TaxBreakdownView> $taxBreakdown
     * @param array<string, mixed> $custom
     */
    public function __construct(
        public TypeCode $typeCode,
        public string $buyerReference,
        public TradePartyView $buyer,
        public ?\DateTimeImmutable $deliveryDate,
        public array $lineItems,
        public array $allowanceCharges,
        public array $taxBreakdown,
        public MonetarySummationView $monetarySummation,
        public ?PaymentMeansView $paymentMeans,
        public ?\DateTimeImmutable $paymentDueDate,
        public bool $intraCommunityDelivery,
        /**
         * @deprecated tag:v6.8.0 - feeds the legacy flat `config.custom.*` template contract
         */
        public array $custom = [],
    ) {
    }
}
