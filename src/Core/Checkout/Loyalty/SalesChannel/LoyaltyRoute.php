<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Extension\LoyaltyRouteExtension;
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
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly SystemConfigService $systemConfigService,
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
            extension: new LoyaltyRouteExtension($request, $context, $customer),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context, CustomerEntity $customer): LoyaltyRouteResponse
    {
        $loyalty = $this->loadLoyalty($customer->getId(), $context);

        /** @var array<string, mixed> $data */
        $data = [
            'pointsBalance' => $loyalty?->getPointsBalance() ?? 0,
            'growthValue' => $loyalty?->getGrowthValue() ?? 0,
            'membershipTierId' => $loyalty?->getMembershipTierId(),
            'membershipValidUntil' => $loyalty?->getMembershipValidUntil()?->format(\DATE_ATOM),
            'pointValueInCurrency' => $this->systemConfigService->getFloat(
                LoyaltyPointCartProcessor::CONFIG_KEY_POINT_VALUE,
                $context->getSalesChannelId()
            ),
            'minRedeemablePoints' => $this->systemConfigService->getInt(
                LoyaltyPointCartProcessor::CONFIG_KEY_MIN_REDEEMABLE,
                $context->getSalesChannelId()
            ),
            'maxRedeemablePercent' => $this->systemConfigService->getFloat(
                LoyaltyPointCartProcessor::CONFIG_KEY_MAX_REDEEMABLE_PERCENT,
                $context->getSalesChannelId()
            ),
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
