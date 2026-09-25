<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Validation\Constraint;

use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\Constraint\Uuid as UuidConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

#[Package('framework')]
class UuidValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UuidConstraint) {
            throw FrameworkException::unexpectedType($constraint, UuidConstraint::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            $this->context->buildViolation(UuidConstraint::INVALID_TYPE_MESSAGE)
                ->addViolation();

            return;
        }

        if (!Uuid::isValid($value)) {
            $this->context->buildViolation(UuidConstraint::INVALID_MESSAGE)
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }
}
