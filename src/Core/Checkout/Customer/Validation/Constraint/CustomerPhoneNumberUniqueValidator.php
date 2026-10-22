<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Validation\Constraint;

use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks the same population the login by sms resolves a number against: active customers that are
 * not guests. A number that only a guest, an inactive or a deleted account carries does not lock
 * anybody out and is therefore no conflict.
 */
#[Package('checkout')]
class CustomerPhoneNumberUniqueValidator extends ConstraintValidator
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(private readonly EntityRepository $customerRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerPhoneNumberUnique) {
            throw CustomerException::unexpectedType($constraint, CustomerPhoneNumberUnique::class);
        }

        // Unbinding the number is always allowed, an empty number cannot be taken by anybody.
        if (!\is_scalar($value)) {
            return;
        }

        // mb_trim also strips full width spaces, which customers of the Chinese storefront tend to enter
        $phoneNumber = mb_trim((string) $value);

        if ($phoneNumber === '') {
            return;
        }

        $salesChannelContext = $constraint->getSalesChannelContext();
        $currentCustomerId = $salesChannelContext->getCustomer()?->getId();

        if ($this->findConflictingCustomerId($phoneNumber, $currentCustomerId, $salesChannelContext->getContext()) === null) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            // the code names the cause, the exception prefixes it with VIOLATION:: and reads the
            // name from ERROR_NAMES - the message template already carries that prefix
            ->setCode(CustomerPhoneNumberUnique::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE)
            ->addViolation();
    }

    private function findConflictingCustomerId(string $phoneNumber, ?string $currentCustomerId, Context $context): ?string
    {
        $filters = [
            new EqualsFilter('phoneNumber', $phoneNumber),
            new EqualsFilter('active', true),
            new EqualsFilter('guest', false),
        ];

        // Saving the number a customer already owns is not a conflict with itself.
        if ($currentCustomerId !== null) {
            $filters[] = new NotFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('id', $currentCustomerId),
            ]);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, $filters));
        $criteria->setLimit(1);

        $conflictingCustomerId = $this->customerRepository->searchIds($criteria, $context)->firstId();

        return \is_string($conflictingCustomerId) ? $conflictingCustomerId : null;
    }
}
