<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Api;

use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class LoyaltyController
{
    private const TRANSACTION_LIMIT = 25;

    private const TRANSACTION_LIMIT_MAX = 100;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     * @param EntityRepository<CustomerPointTransactionCollection> $pointTransactionRepository
     * @param EntityRepository<LoyaltyTierCollection> $loyaltyTierRepository
     */
    public function __construct(
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly EntityRepository $pointTransactionRepository,
        private readonly EntityRepository $loyaltyTierRepository,
        private readonly LoyaltyPointService $loyaltyPointService,
        private readonly LoyaltyLedger $loyaltyLedger,
    ) {
    }

    #[Route(
        path: '/api/_action/loyalty/customer/{customerId}/points',
        name: 'api.action.loyalty.customer.points',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['loyalty.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function adjustPoints(string $customerId, Request $request, Context $context): Response
    {
        $points = $request->request->getInt('points');
        $comment = (string) $request->request->get('comment', '');

        $this->loyaltyPointService->adjust($customerId, $points, $comment, $this->resolveAdministratorId($context), $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The point ledger of one customer, one page at a time - the summary carries the balances only, so a
     * customer with a long history does not load all of it to show the last ten bookings.
     */
    #[Route(
        path: '/api/_action/loyalty/customer/{customerId}/transactions',
        name: 'api.action.loyalty.customer.transactions',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['loyalty.viewer']],
        methods: [Request::METHOD_GET]
    )]
    public function transactions(string $customerId, Request $request, Context $context): Response
    {
        $page = max($request->query->getInt('page', 1), 1);
        $limit = min(max($request->query->getInt('limit', self::TRANSACTION_LIMIT), 1), self::TRANSACTION_LIMIT_MAX);

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);

        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        $type = (string) $request->query->get('type', '');

        if ($type !== '') {
            $criteria->addFilter(new EqualsFilter('type', $type));
        }

        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->pointTransactionRepository->search($criteria, $context);

        $transactions = [];

        foreach ($result->getEntities() as $transaction) {
            $transactions[] = [
                'id' => $transaction->getId(),
                'type' => $transaction->getType(),
                'points' => $transaction->getPoints(),
                'balanceAfter' => $transaction->getBalanceAfter(),
                'orderId' => $transaction->getOrderId(),
                'comment' => $transaction->getComment(),
                'expiresAt' => $transaction->getExpiresAt()?->format(\DATE_ATOM),
                'createdAt' => $transaction->getCreatedAt()?->format(\DATE_ATOM),
            ];
        }

        return new JsonResponse([
            'total' => $result->getTotal(),
            'transactions' => $transactions,
        ]);
    }

    /**
     * Grants a membership the operator decided on - a goodwill upgrade, a correction, or a membership
     * that was paid outside of the shop. The period is written by the ledger, so an extension of the
     * same tier continues where the running one ends instead of restarting today.
     */
    #[Route(
        path: '/api/_action/loyalty/customer/{customerId}/membership',
        name: 'api.action.loyalty.customer.membership',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['loyalty.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function grantMembership(string $customerId, Request $request, Context $context): Response
    {
        $tierId = (string) $request->request->get('tierId', '');
        $durationDays = $request->request->getInt('durationDays');

        if ($durationDays < 1) {
            throw LoyaltyException::invalidMembershipDuration($durationDays);
        }

        $this->assertTierExists($tierId, $context);

        $period = $this->loyaltyLedger->activateMembership($customerId, $tierId, $durationDays, $context);

        return new JsonResponse([
            'startsAt' => $period['startsAt']->format(\DATE_ATOM),
            'expiresAt' => $period['expiresAt']->format(\DATE_ATOM),
        ]);
    }

    #[Route(
        path: '/api/_action/loyalty/customer/{customerId}/summary',
        name: 'api.action.loyalty.customer.summary',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['loyalty.viewer']],
        methods: [Request::METHOD_GET]
    )]
    public function summary(string $customerId, Context $context): Response
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addAssociation('membershipTier');
        $criteria->setLimit(1);

        $loyalty = $this->customerLoyaltyRepository->search($criteria, $context)->getEntities()->first();

        if (!$loyalty instanceof CustomerLoyaltyEntity) {
            return new JsonResponse([
                'pointsBalance' => 0,
                'growthValue' => 0,
                'membershipTier' => null,
                'membershipValidUntil' => null,
            ]);
        }

        $tier = $loyalty->getMembershipTier();

        return new JsonResponse([
            'pointsBalance' => $loyalty->getPointsBalance(),
            'growthValue' => $loyalty->getGrowthValue(),
            'membershipTier' => $tier === null ? null : [
                'id' => $tier->getId(),
                'technicalName' => $tier->getTechnicalName(),
                'name' => $tier->getName(),
                'level' => $tier->getLevel(),
                'pointsMultiplier' => $tier->getPointsMultiplier(),
            ],
            'membershipValidUntil' => $loyalty->getMembershipValidUntil()?->format(\DATE_ATOM),
        ]);
    }

    private function assertTierExists(string $tierId, Context $context): void
    {
        $criteria = new Criteria([$tierId]);
        $criteria->setLimit(1);

        if ($this->loyaltyTierRepository->searchIds($criteria, $context)->getTotal() === 0) {
            throw LoyaltyException::tierNotFound($tierId);
        }
    }

    private function resolveAdministratorId(Context $context): ?string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }
}
