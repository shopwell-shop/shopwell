<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopwell\Core\Framework\Validation\Constraint\Uuid;
use Shopwell\Core\Framework\Validation\Constraint\UuidValidator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UuidValidator::class)]
class UuidValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionBecauseConstraintHasWrongClass(): void
    {
        $wrongConstraint = new ArrayOfUuid();
        $this->expectExceptionObject(FrameworkException::unexpectedType($wrongConstraint, Uuid::class));
        $validator = new UuidValidator();
        $validator->validate([], $wrongConstraint);
    }
}
