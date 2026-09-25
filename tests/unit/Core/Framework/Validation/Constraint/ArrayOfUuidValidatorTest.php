<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopwell\Core\Framework\Validation\Constraint\ArrayOfUuidValidator;
use Shopwell\Core\Framework\Validation\Constraint\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ArrayOfUuidValidator::class)]
class ArrayOfUuidValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionBecauseConstraintHasWrongClass(): void
    {
        $wrongConstraint = new Uuid();
        $this->expectExceptionObject(FrameworkException::unexpectedType($wrongConstraint, ArrayOfUuid::class));
        $validator = new ArrayOfUuidValidator();
        $validator->validate([], $wrongConstraint);
    }
}
