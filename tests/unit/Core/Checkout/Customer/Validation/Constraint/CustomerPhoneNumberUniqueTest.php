<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerPhoneNumberUnique;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerPhoneNumberUnique::class)]
class CustomerPhoneNumberUniqueTest extends TestCase
{
    public function testItKeepsTheSalesChannelContext(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $constraint = new CustomerPhoneNumberUnique($salesChannelContext);

        static::assertSame($salesChannelContext, $constraint->getSalesChannelContext());
    }

    public function testItReportsTheNumberThatIsAlreadyTaken(): void
    {
        $constraint = new CustomerPhoneNumberUnique(static::createStub(SalesChannelContext::class));

        static::assertSame('VIOLATION::PHONE_NUMBER_IS_ALREADY_TAKEN', $constraint->getMessage());
        static::assertSame('VIOLATION::PHONE_NUMBER_IS_ALREADY_TAKEN', CustomerPhoneNumberUnique::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE_CODE);
    }

    public function testItAcceptsACustomMessage(): void
    {
        $constraint = new CustomerPhoneNumberUnique(
            static::createStub(SalesChannelContext::class),
            'Custom message for a taken number.',
        );

        static::assertSame('Custom message for a taken number.', $constraint->getMessage());
    }
}
