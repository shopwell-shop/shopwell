<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Profile;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class AccountProfilePage extends Page
{
    protected SalutationCollection $salutations;

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }
}
