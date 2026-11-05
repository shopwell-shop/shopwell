<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentScene;

/**
 * The payer's entry point and the handovers its frontend can complete.
 * Runtime identities are trusted values scoped to the product that issued them, never shared between appids.
 */
#[Package('framework')]
final readonly class PaymentContext
{
    public string $device;

    /**
     * @param list<string> $supportedInteractions
     * @param list<string> $supportedSdkProducts the concrete SDK products the caller can hand over
     * @param array<string, array<string, mixed>> $runtimeParams common runtime values indexed by product
     * @param array<string, array<string, array<string, mixed>>> $accountRuntimeParams payer identities indexed by merchant account then product
     */
    public function __construct(
        public string $currency,
        public PaymentScene $scene,
        public array $supportedInteractions = [PaymentInteraction::REDIRECT, PaymentInteraction::QRCODE],
        public array $supportedSdkProducts = [],
        public array $runtimeParams = [],
        public array $accountRuntimeParams = [],
    ) {
        $this->device = $scene->device;
    }
}
