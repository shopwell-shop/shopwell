<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Provider;

use Shopwell\Core\Checkout\DocumentV2\DocumentType;
use Shopwell\Core\Checkout\DocumentV2\Provider\RenderData\CreditNoteRenderData;
use Shopwell\Core\Checkout\DocumentV2\Service\CreditItemResolver;
use Shopwell\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopwell\Core\Checkout\DocumentV2\Template\Calculation\CreditOrderReducer;
use Shopwell\Core\Checkout\DocumentV2\Template\View\LineItemView;
use Shopwell\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class CreditNoteDataProvider extends AbstractDocumentDataProvider implements ReferencesDocument
{
    final public const KEY = 'credit_note';

    public function __construct(
        private InvoiceDataProvider $invoiceDataProvider,
        private CreditItemResolver $creditItemResolver,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(string $documentType): bool
    {
        return $documentType === DocumentType::CREDIT_NOTE->value;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $this->invoiceDataProvider->enrichOrderCriteria($criteria);
    }

    public function provideRenderingData(
        ProviderInput $input,
        Context $context,
    ): CreditNoteRenderData {
        $resolvedReference = $input->resolvedReference;
        \assert($resolvedReference !== null);

        $creditItems = $this->creditItemResolver->resolve($input->order, $resolvedReference->id);

        CreditOrderReducer::reduce($input->order, $creditItems);

        $invoice = $this->invoiceDataProvider->provideRenderingData($input, $context);
        $lineItems = LineItemView::listFromCreditItems($input->order);

        return CreditNoteRenderData::fromInvoice(
            $invoice,
            $input->generationRequest->documentNumber,
            $resolvedReference->documentNumber,
            $lineItems,
            MonetarySummationView::fromOrder($input->order, $lineItems, []),
        );
    }
}
