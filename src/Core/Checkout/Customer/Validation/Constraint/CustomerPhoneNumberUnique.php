<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Validation\Constraint;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * A phone number may be bound to one active customer only.
 *
 * The login by sms resolves a number to *the* customer that owns it and refuses a number that more
 * than one customer registered, so a number that is shared would lock both of its owners out. The
 * customer that is currently logged in is not a conflict with itself, so the profile page can be
 * saved without unbinding and rebinding the number first.
 */
#[Package('checkout')]
class CustomerPhoneNumberUnique extends Constraint
{
    final public const CUSTOMER_PHONE_NUMBER_NOT_UNIQUE = 'c4d9b2ec-6b19-4b8f-9a0d-2f8f1c5a7d31';

    final public const CUSTOMER_PHONE_NUMBER_NOT_UNIQUE_CODE = 'VIOLATION::PHONE_NUMBER_IS_ALREADY_TAKEN';

    protected const ERROR_NAMES = [
        self::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE => 'PHONE_NUMBER_IS_ALREADY_TAKEN',
    ];

    public string $message = self::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE_CODE;

    protected SalesChannelContext $salesChannelContext;

    /**
     * @internal
     */
    #[HasNamedArguments]
    public function __construct(
        SalesChannelContext $salesChannelContext,
        string $message = self::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE_CODE,
    ) {
        parent::__construct();

        $this->salesChannelContext = $salesChannelContext;
        $this->message = $message;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
