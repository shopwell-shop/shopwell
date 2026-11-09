<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Validation\StructuredPropertyType;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StructuredPropertyType::class)]
class StructuredPropertyTypeTest extends TestCase
{
    public function testCanBeRegisteredAsAClassConstraint(): void
    {
        $constraint = new StructuredPropertyType();
        $metadata = new ClassMetadata(Constraint::class);
        $metadata->addConstraint($constraint);

        static::assertSame([$constraint], $metadata->getConstraints());
    }

    public function testRejectsPropertyPlacement(): void
    {
        $this->expectExceptionObject(new ConstraintDefinitionException('The constraint "' . StructuredPropertyType::class . '" cannot be put on properties or getters.'));

        (new ClassMetadata(Constraint::class))->addPropertyConstraint('groups', new StructuredPropertyType());
    }
}
