<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Turns a declarative StyleOptionValueType into the Symfony constraints a single per-breakpoint value
 * must satisfy. NotBlank applies to every type except boolean, where false is a legitimate value.
 *
 * @internal
 */
#[Package('framework')]
final class StyleOptionConstraintDeriver
{
    /**
     * @return list<Constraint>
     */
    public function derive(StyleOptionValueType $valueType): array
    {
        $builder = new ConstraintBuilder();

        $this->applyTypeConstraint($builder, $valueType->type());

        if ($valueType->type() !== StyleOptionValueType::TYPE_BOOLEAN) {
            $builder->isNotBlank();
        }

        $range = $valueType->range();
        if ($range !== null) {
            $builder->addConstraint(new Range(min: $range['min'] ?? null, max: $range['max'] ?? null));
        }

        // maxLength() already supplies the default cap for an unbounded string or number, so a numeric string
        // cannot be stored unbounded.
        $maxLength = $valueType->maxLength();
        if ($maxLength !== null) {
            $builder->isLengthLessThanOrEqual($maxLength);
        }

        $enum = $valueType->enum();
        if ($enum !== null) {
            $builder->addConstraint(new Choice(choices: $enum, strict: true));
        }

        return array_values($builder->getConstraints());
    }

    private function applyTypeConstraint(ConstraintBuilder $builder, string $type): void
    {
        match ($type) {
            StyleOptionValueType::TYPE_BOOLEAN => $builder->isBool(),
            StyleOptionValueType::TYPE_INTEGER => $builder->isInt(),
            StyleOptionValueType::TYPE_NUMBER => $builder->isNumeric(),
            StyleOptionValueType::TYPE_STRING => $builder->isString(),
            default => throw ContentSystemException::unsupportedStyleValueType($type),
        };
    }
}
