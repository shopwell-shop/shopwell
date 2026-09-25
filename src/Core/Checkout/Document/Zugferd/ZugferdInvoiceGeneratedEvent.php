<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Zugferd;

use Shopwell\Core\Checkout\Document\DocumentConfiguration;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'ZUGFeRD XML is produced by the DocumentV2 XML renderer without a public builder API.',
)]
class ZugferdInvoiceGeneratedEvent extends Event
{
    public function __construct(
        public readonly ZugferdDocument $document,
        public readonly OrderEntity $order,
        public readonly DocumentConfiguration $config,
        public readonly Context $context
    ) {
    }
}
