<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Extension\LoyaltyRouteExtension;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyBenefitResolver;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRedemptionCalculator;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
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

/**
 * The rewards page: how many points a customer holds, what they are worth, and which tier they are in.
 *
 * Points are earned, a paid membership is bought - the offer side of the account lives on
 * {@see \Shopwell\Core\Checkout\Membership\SalesChannel\MembershipRoute} since the two have their own
 * page. What stays here is everything the customer reached without paying for it.
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LoyaltyRoute
{
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
        private readonly LoyaltyBenefitResolver $benefits,
        private readonly LoyaltyRedemptionCalculator $redemption,
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

        /** @var array<string, mixed> $data */
        $data = [
            'pointsBalance' => $loyalty?->getPointsBalance() ?? 0,
            'growthValue' => $loyalty?->getGrowthValue() ?? 0,
            // the tier is a projection of what the customer earned and what a membership grants; both
            // write it, and the page only reads it - the paid membership itself has its own route
            'membershipTierId' => $loyalty?->getMembershipTierId(),
            // the rate below the first step; the steps themselves are what the page has to show, so the
            // storefront never has to multiply the balance by one number and get a wrong amount
            'pointValueInCurrency' => $this->settings->pointValueInCurrency($context->getSalesChannelId()),
            'redemptionLevels' => $this->redemption->rates($context->getSalesChannelId(), $context->getContext())->levels(),
            'minRedeemablePoints' => $this->settings->minRedeemablePoints($context->getSalesChannelId()),
            'maxRedeemablePercent' => $this->settings->maxRedeemablePercent($context->getSalesChannelId()),
            'tiers' => $this->loadTiers($context),
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
        $criteria->addAssociation('benefits');

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
                // the three columns stay until the templates read `benefits`; a benefit row already wins
                // over them here, so a shop that configured one sees it without changing the template
                'pointsMultiplier' => $tier->getPointsMultiplier(),
                'discountPercent' => $tier->getDiscountPercent(),
                'freeShipping' => $tier->getFreeShipping(),
                'benefits' => $this->benefits->forTier($tier, $context->getContext())->all(),
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
