<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Api;

use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
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

    /**
     * @internal
     *
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     */
    public function __construct(
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly LoyaltyPointService $loyaltyPointService,
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
        $criteria->addAssociation('transactions');
        $criteria->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(self::TRANSACTION_LIMIT);
        $criteria->setLimit(1);

        $loyalty = $this->customerLoyaltyRepository->search($criteria, $context)->getEntities()->first();

        if (!$loyalty instanceof CustomerLoyaltyEntity) {
            return new JsonResponse([
                'pointsBalance' => 0,
                'growthValue' => 0,
                'membershipTier' => null,
                'membershipValidUntil' => null,
                'transactions' => [],
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
            'transactions' => $this->mapTransactions($loyalty),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapTransactions(CustomerLoyaltyEntity $loyalty): array
    {
        $collection = $loyalty->getTransactions();

        if ($collection === null) {
            return [];
        }

        $transactions = [];

        foreach ($collection as $transaction) {
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

        return $transactions;
    }

    private function resolveAdministratorId(Context $context): ?string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }
}
