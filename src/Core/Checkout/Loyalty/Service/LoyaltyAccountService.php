<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;

/**
 * Reads the loyalty account of a customer and opens it when the customer gets one.
 *
 * The account is the row the points, the tier points and the tier of a customer are projected onto.
 * Reading it is what every loyalty page and every booking starts with, opening it is what a new
 * customer needs - and both belong here instead of in the services that write the ledger, so that
 * "who may open an account" has one answer. The balances are never written from here.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyAccountService
{
    /**
     * @internal
     *
     * @param EntityRepository<LoyaltyAccountCollection> $loyaltyAccountRepository
     */
    public function __construct(
        private readonly EntityRepository $loyaltyAccountRepository,
        private readonly TierResolver $tierResolver,
        private readonly RowLock $rows,
    ) {
    }

    public function account(string $customerId, Context $context): ?LoyaltyAccountEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $account = $this->loyaltyAccountRepository->search($criteria, $context)->getEntities()->first();

        return $account instanceof LoyaltyAccountEntity ? $account : null;
    }

    public function balance(string $customerId, Context $context): int
    {
        return $this->account($customerId, $context)?->getPointsBalance() ?? 0;
    }

    /**
     * Opens the account of a customer with a zero balance and the tier that zero tier points unlock,
     * so a customer owns an account from the moment it exists instead of from its first booking.
     *
     * An account that is already there is left untouched - including its balance and its tier - which
     * makes the call safe to repeat. Opening books nothing: points are the ledger's business.
     */
    public function open(string $customerId, Context $context): void
    {
        if ($this->account($customerId, $context) !== null) {
            return;
        }

        // a booking of the same customer may open the very same account at the same time; losing that
        // race is not an error, the winner's row is the one the ledger books onto
        $this->rows->createUnlessExists($this->loyaltyAccountRepository, [
            'customerId' => $customerId,
            'pointsBalance' => 0,
            'tierId' => $this->tierResolver->findTierIdForTierPoints(0, $context),
        ], $context);
    }
}
