<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document;

use Shopwell\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopwell\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopwell\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopwell\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'Part of the legacy document generation pipeline. DocumentV2 handles this concern internally and exposes no counterpart.',
)]
class DocumentEvents
{
    public const CREDIT_NOTE_ORDER_CRITERIA_EVENT = CreditNoteRenderer::TYPE . '.document.criteria';
    public const DELIVERY_ORDER_CRITERIA_EVENT = DeliveryNoteRenderer::TYPE . '.document.criteria';
    public const INVOICE_ORDER_CRITERIA_EVENT = InvoiceRenderer::TYPE . '.document.criteria';
    public const STORNO_ORDER_CRITERIA_EVENT = StornoRenderer::TYPE . '.document.criteria';
}
