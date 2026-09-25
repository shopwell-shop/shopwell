<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Provider;

use Shopwell\Core\Checkout\DocumentV2\DocumentType;
use Shopwell\Core\Checkout\DocumentV2\Provider\RenderData\CancellationInvoiceRenderData;
use Shopwell\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopwell\Core\Checkout\DocumentV2\Template\Calculation\OrderInverter;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class CancellationInvoiceDataProvider extends AbstractDocumentDataProvider implements RendersReferencedSnapshot
{
    final public const KEY = 'storno';

    public function __construct(
        private InvoiceDataProvider $invoiceDataProvider,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(string $documentType): bool
    {
        return $documentType === DocumentType::CANCELLATION_INVOICE->value;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $this->invoiceDataProvider->enrichOrderCriteria($criteria);
    }

    public function provideRenderingData(
        ProviderInput $input,
        Context $context,
    ): CancellationInvoiceRenderData {
        $resolvedReference = $input->resolvedReference;
        \assert($resolvedReference !== null);

        OrderInverter::invert($input->order);

        $invoice = $this->invoiceDataProvider->provideRenderingData($input, $context);

        return CancellationInvoiceRenderData::fromInvoice(
            $invoice,
            $input->generationRequest->documentNumber,
            $resolvedReference->documentNumber,
        );
    }
}
