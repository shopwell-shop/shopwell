<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Api;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyBenefitResolver;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
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
     * @param EntityRepository<LoyaltyAccountCollection> $loyaltyAccountRepository
     * @param EntityRepository<LoyaltyPointTransactionCollection> $pointTransactionRepository
     * @param EntityRepository<LoyaltyTierCollection> $loyaltyTierRepository
     */
    public function __construct(
        private readonly EntityRepository $loyaltyAccountRepository,
        private readonly EntityRepository $pointTransactionRepository,
        private readonly EntityRepository $loyaltyTierRepository,
        private readonly LoyaltyPointService $loyaltyPointService,
        private readonly MembershipSubscriptionService $subscriptions,
        private readonly LoyaltyBenefitResolver $benefits,
        private readonly TierResolver $tiers,
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
     * that was paid outside of the shop. It goes through the membership itself, so granting the tier the
     * customer already holds continues where the running membership ends instead of restarting today.
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

        $this->assertTierExists($tierId, $context);

        $subscription = $this->subscriptions->grant($customerId, $tierId, $durationDays, $context);

        return new JsonResponse([
            'startsAt' => $subscription->getStartsAt()->format(\DATE_ATOM),
            'expiresAt' => $subscription->getEndsAt()->format(\DATE_ATOM),
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
        $criteria->setLimit(1);

        $loyalty = $this->loyaltyAccountRepository->search($criteria, $context)->getEntities()->first();

        if (!$loyalty instanceof LoyaltyAccountEntity) {
            return new JsonResponse([
                'pointsBalance' => 0,
                'tierPoints' => 0,
                'tier' => null,
                'membershipPaidUntil' => null,
            ]);
        }

        $tier = $this->tiers->currentTier($customerId, $context);

        return new JsonResponse([
            'pointsBalance' => $loyalty->getPointsBalance(),
            'tierPoints' => $loyalty->getTierPoints(),
            'tier' => $tier === null ? null : [
                'id' => $tier->getId(),
                'technicalName' => $tier->getTechnicalName(),
                'name' => $tier->getName(),
                'level' => $tier->getLevel(),
                // the tier's grants travel with the summary so the customer tab does not need a second
                // request; the multiplier the tab prints is one entry of this map
                'benefits' => $this->benefits->forTier($tier, $context)->all(),
            ],
            'membershipPaidUntil' => $loyalty->getMembershipPaidUntil()?->format(\DATE_ATOM),
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
