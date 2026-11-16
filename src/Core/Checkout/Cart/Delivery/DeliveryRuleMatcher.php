<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Delivery;

use Shopwell\Core\Checkout\Cart\AbstractRuleLoader;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Content\Rule\RuleEvents;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Matches the rules referenced by a delivery's shipping method against that delivery and stores the matching ids on it
 *
 * @internal
 */
#[Package('checkout')]
class DeliveryRuleMatcher implements ResetInterface, EventSubscriberInterface
{
    private ?RuleCollection $rules = null;

    public function __construct(private readonly AbstractRuleLoader $ruleLoader)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RuleEvents::RULE_WRITTEN_EVENT => 'reset',
        ];
    }

    public function match(CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries, SalesChannelContext $context): void
    {
        $only = $deliveries->count() === 1 ? $deliveries->first() : null;
        if ($only !== null && $this->equalsContext($only, $context)) {
            $only->setRuleIds(array_values($context->getRuleIds()));

            return;
        }

        foreach ($deliveries as $delivery) {
            $delivery->setRuleIds($this->matchDelivery($data, $cart, $delivery, $context));
        }
    }

    public function reset(): void
    {
        $this->rules = null;
    }

    /**
     * @return list<string>
     */
    private function matchDelivery(CartDataCollection $data, Cart $cart, Delivery $delivery, SalesChannelContext $context): array
    {
        $referenced = $this->getReferencedRuleIds($this->getShippingMethod($data, $delivery));
        if ($referenced === []) {
            return [];
        }

        $scope = new DeliveryRuleScope($delivery, $cart, $context);

        $matched = [];
        foreach ($this->loadRules($context->getContext()) as $rule) {
            if (!\in_array($rule->getId(), $referenced, true)) {
                continue;
            }

            $payload = $rule->getPayload();
            if ($payload instanceof Rule && $payload->match($scope)) {
                $matched[] = $rule->getId();
            }
        }

        return $matched;
    }

    private function equalsContext(Delivery $delivery, SalesChannelContext $context): bool
    {
        if ($delivery->getShippingMethod()->getId() !== $context->getShippingMethod()->getId()) {
            return false;
        }

        return $this->equalsLocation($delivery->getLocation(), $context->getShippingLocation());
    }

    private function equalsLocation(ShippingLocation $location, ShippingLocation $other): bool
    {
        if ($location === $other) {
            return true;
        }

        return $location->getCountry()->getId() === $other->getCountry()->getId()
            && $location->getState()?->getId() === $other->getState()?->getId()
            && $location->getAddress()?->getId() === $other->getAddress()?->getId();
    }

    private function getShippingMethod(CartDataCollection $data, Delivery $delivery): ShippingMethodEntity
    {
        $shippingMethod = $data->get(DeliveryProcessor::buildKey($delivery->getShippingMethod()->getId()));

        return $shippingMethod instanceof ShippingMethodEntity ? $shippingMethod : $delivery->getShippingMethod();
    }

    /**
     * @return list<string>
     */
    private function getReferencedRuleIds(ShippingMethodEntity $shippingMethod): array
    {
        $ids = [$shippingMethod->getAvailabilityRuleId()];

        foreach ($shippingMethod->getPrices() as $price) {
            $ids[] = $price->getRuleId();
            $ids[] = $price->getCalculationRuleId();
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function loadRules(Context $context): RuleCollection
    {
        return $this->rules ??= $this->ruleLoader->load($context)->filterForContext();
    }
}
