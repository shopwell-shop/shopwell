<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Method;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * A payer-facing method shared by any number of provider products.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class PaymentMethod
{
    /**
     * @param list<PaymentCapability> $capabilities the operations this method's products may implement
     */
    public function __construct(
        public string $technicalName,
        public string $translationKey,
        public array $capabilities = [PaymentCapability::CAPTURE, PaymentCapability::QUERY, PaymentCapability::CLOSE, PaymentCapability::REFUND, PaymentCapability::PARTIAL_REFUND, PaymentCapability::NOTIFICATION, PaymentCapability::MANDATE],
    ) {
    }
}
