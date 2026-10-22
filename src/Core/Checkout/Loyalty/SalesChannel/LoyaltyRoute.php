<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\SalesChannel;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Extension\LoyaltyRouteExtension;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Loyalty\Service\MembershipService;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LoyaltyRoute
{
    private const RECENT_ORDER_LIMIT = 5;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     * @param EntityRepository<LoyaltyTierCollection> $tierRepository
     */
    public function __construct(
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly EntityRepository $tierRepository,
        private readonly LoyaltySettings $settings,
        private readonly MembershipService $membershipService,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/loyalty',
        name: 'store-api.loyalty',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_GET]
    )]
    public function load(Request $request, SalesChannelContext $context, CustomerEntity $customer): LoyaltyRouteResponse
    {
        return $this->extensions->publish(
            name: LoyaltyRouteExtension::NAME,
            extension: new LoyaltyRouteExtension($context, $customer),
            function: $this->_load(...),
        );
    }

    private function _load(SalesChannelContext $context, CustomerEntity $customer): LoyaltyRouteResponse
    {
        $loyalty = $this->loadLoyalty($customer->getId(), $context);
        $validUntil = $loyalty?->getMembershipValidUntil();

        /** @var array<string, mixed> $data */
        $data = [
            'pointsBalance' => $loyalty?->getPointsBalance() ?? 0,
            'growthValue' => $loyalty?->getGrowthValue() ?? 0,
            'membershipTierId' => $loyalty?->getMembershipTierId(),
            'membershipValidUntil' => $validUntil?->format(\DATE_ATOM),
            // a membership that was bought runs until its end, a tier that was only reached through the
            // growth value has no end - the storefront shows the plans only in the second case as an upgrade
            'membershipActive' => $validUntil !== null && $validUntil > $this->clock->now(),
            'pointValueInCurrency' => $this->settings->pointValueInCurrency($context->getSalesChannelId()),
            'minRedeemablePoints' => $this->settings->minRedeemablePoints($context->getSalesChannelId()),
            'maxRedeemablePercent' => $this->settings->maxRedeemablePercent($context->getSalesChannelId()),
            'tiers' => $this->loadTiers($context),
            'plans' => $this->membershipService->plans($context->getCurrency()->getId(), $context->getContext()),
            // the account page is the same on every device, so no device is filtered out here: a plan
            // bought on a desktop is often paid by scanning it with a phone
            'membershipChannels' => $this->membershipService->channels($context->getCurrency()->getIsoCode(), null),
            'membershipOrders' => $this->membershipService->orders(
                $customer->getId(),
                self::RECENT_ORDER_LIMIT,
                $context->getContext()
            ),
        ];

        return new LoyaltyRouteResponse(new ArrayStruct($data));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadTiers(SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('level', FieldSorting::ASCENDING));

        $tiers = [];
        foreach ($this->tierRepository->search($criteria, $context->getContext())->getEntities() as $tier) {
            if (!$tier instanceof LoyaltyTierEntity) {
                continue;
            }

            $tiers[] = [
                'id' => $tier->getId(),
                'technicalName' => $tier->getTechnicalName(),
                'name' => $tier->getName(),
                'level' => $tier->getLevel(),
                'minGrowthValue' => $tier->getMinGrowthValue(),
                'pointsMultiplier' => $tier->getPointsMultiplier(),
                'discountPercent' => $tier->getDiscountPercent(),
                'freeShipping' => $tier->getFreeShipping(),
            ];
        }

        return $tiers;
    }

    private function loadLoyalty(string $customerId, SalesChannelContext $context): ?CustomerLoyaltyEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $loyalty = $this->customerLoyaltyRepository->search($criteria, $context->getContext())->getEntities()->first();

        return $loyalty instanceof CustomerLoyaltyEntity ? $loyalty : null;
    }
}
