<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Validation\Constraint;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Validation\CustomerEmailUniqueCheck;
use Shopwell\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

#[Package('checkout')]
class CustomerEmailUniqueValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CustomerEmailUniqueChecker $customerEmailUniqueChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerEmailUnique) {
            throw CustomerException::unexpectedType($constraint, CustomerEmailUnique::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if ($this->customerEmailUniqueChecker->isUnique(new CustomerEmailUniqueCheck(
            email: (string) $value,
            boundSalesChannelId: $constraint->getSalesChannelContext()->getSalesChannelId(),
        ))) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ email }}', $this->formatValue($value))
            ->setCode(CustomerEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE)
            ->addViolation();
    }
}
