<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\RuleIdMatcher;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends EntityCollection<PaymentMethodEntity>
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Storefront\Page\Checkout\ConfirmPageTest
 */
#[Package('checkout')]
class PaymentMethodCollection extends EntityCollection
{
    /**
     * @deprecated tag:v6.8.0 use RuleIdMatcher instead
     */
    public function filterByActiveRules(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', RuleIdMatcher::class)
        );

        return $this->filter(
            static function (PaymentMethodEntity $paymentMethod) use ($salesChannelContext) {
                if ($paymentMethod->getAvailabilityRuleId() === null) {
                    return true;
                }

                return \in_array($paymentMethod->getAvailabilityRuleId(), $salesChannelContext->getRuleIds(), true);
            }
        );
    }

    /**
     * @return array<string>
     */
    public function getPluginIds(): array
    {
        return $this->fmap(static fn (PaymentMethodEntity $paymentMethod) => $paymentMethod->getPluginId());
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(static fn (PaymentMethodEntity $paymentMethod) => $paymentMethod->getPluginId() === $id);
    }

    /**
     * Sorts the selected payment method first
     * If a different default payment method is defined, it will be sorted second
     * All other payment methods keep their respective sorting
     */
    public function sortPaymentMethodsByPreference(SalesChannelContext $context): void
    {
        $ids = array_merge(
            [$context->getSalesChannel()->getPaymentMethodId()],
            $this->getIds(),
        );

        $this->sortByIdArray($ids);
    }

    public function getApiAlias(): string
    {
        return 'payment_method_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodEntity::class;
    }
}
