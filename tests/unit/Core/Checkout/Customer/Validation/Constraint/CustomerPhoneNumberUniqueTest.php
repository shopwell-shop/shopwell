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

        // the default message is the snippet key the storefront translates, that is the CODE constant
        static::assertSame('VIOLATION::PHONE_NUMBER_IS_ALREADY_TAKEN', $constraint->getMessage());
    }

    public function testItNamesTheCauseWithoutTheViolationPrefix(): void
    {
        $constraint = new CustomerPhoneNumberUnique(static::createStub(SalesChannelContext::class));

        // the violation exception builds the api code as 'VIOLATION::' . getErrorName(), so the name
        // must not carry the prefix the message template already has
        static::assertSame(
            'PHONE_NUMBER_IS_ALREADY_TAKEN',
            $constraint->getErrorName(CustomerPhoneNumberUnique::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE)
        );
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
