<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Profile;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class AccountProfilePage extends Page
{
    protected DictionaryEntryCollection $genders;

    public function getGenders(): DictionaryEntryCollection
    {
        return $this->genders;
    }

    public function setGenders(DictionaryEntryCollection $genders): void
    {
        $this->genders = $genders;
    }
}
