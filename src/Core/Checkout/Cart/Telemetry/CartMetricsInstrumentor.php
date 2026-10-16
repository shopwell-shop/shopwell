<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Telemetry;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\Error\Error;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Instrumentation\DurationMetric;
use Shopwell\Core\Framework\Telemetry\Instrumentation\OperationResult;
use Shopwell\Core\Framework\Telemetry\Instrumentation\Span;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopwell\Core\Framework\Telemetry\Telemetry;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;

/**
 * Telemetry collaborator for {@see CartCalculator}: times a cart
 * calculation and derives the cart calculation metrics (duration, line item count, errors) from the
 * resulting cart, keeping telemetry logic and dependencies out of the Calculator.
 *
 * Failed calculations are timed too (`result=failed`, `has_promotions=no` as the cart is unknown);
 * the follow-up metrics (line items, errors) only exist for completed carts.
 *
 * Merely-hot path: relies on the Meter's early-return when telemetry is disabled, not compiler pass based disabling.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('checkout')]
class CartMetricsInstrumentor
{
    public function __construct(
        private readonly Telemetry $telemetry,
        private readonly SalesChannelTypeResolver $salesChannelTypeResolver,
    ) {
    }

    /**
     * @param \Closure(): Cart $callback
     */
    public function measure(SalesChannelContext $context, \Closure $callback): Cart
    {
        $salesChannelType = $this->salesChannelTypeResolver->resolve($context->getSalesChannel()->getTypeId());

        $cart = $this->telemetry->instrument(
            $callback,
            new DurationMetric(
                name: 'cart.calculation.duration',
                labels: static fn (?Cart $cart, ?\Throwable $e): array => [
                    'sales_channel_type' => $salesChannelType,
                    'has_promotions' => $cart !== null && $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->count() > 0 ? 'yes' : 'no',
                    'result' => OperationResult::fromOutcome($e),
                ],
            ),
            new Span('cart-calculation'),
        );

        // Counts top-level cart rows, not the flattened tree: a bundle of three sub-items is 1,
        // not 4. Nested children are excluded intentionally so basket size reflects rows rather than
        // structure — revisit if counting bundle sub-items proves the more useful signal.
        $this->telemetry->emit(new ConfiguredMetric(
            name: 'cart.line_items.count',
            value: $cart->getLineItems()->count(),
            labels: ['sales_channel_type' => $salesChannelType],
        ));

        $this->emitErrorCount($cart);

        return $cart;
    }

    private function emitErrorCount(Cart $cart): void
    {
        // A single aggregate count per calculation (no per-domain/level breakdown):
        // spikes flag checkout friction, and the flat counter keeps that signal cheap
        // and low-noise. Per-error emission and error classification probably is a logging concern.
        //
        // Count warning-level and above only — informational cart messages ("discount applied",
        // "method switched") sit at NOTICE and are excluded. Equals-or-bigger guards against new low-severity
        // levels added later
        $count = 0;
        foreach ($cart->getErrors() as $error) {
            if ($error->getLevel() >= Error::LEVEL_WARNING) {
                ++$count;
            }
        }

        if ($count === 0) {
            return;
        }

        $this->telemetry->emit(new ConfiguredMetric(name: 'cart.errors.count', value: $count));
    }
}
