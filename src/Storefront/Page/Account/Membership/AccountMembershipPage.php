<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Membership;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Storefront\Page\Page;

/**
 * What the customer can buy and what they already hold: the plans, how they can be paid, the running
 * membership and the recent orders.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AccountMembershipPage extends Page
{
    protected ArrayStruct $membership;

    public function getMembership(): ArrayStruct
    {
        return $this->membership;
    }

    public function setMembership(ArrayStruct $membership): void
    {
        $this->membership = $membership;
    }
}
