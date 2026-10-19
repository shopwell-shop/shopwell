<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Wallet;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class AccountWalletPage extends Page
{
    protected ArrayStruct $wallet;

    protected int $currentPage = 1;

    protected int $limit = 25;

    /**
     * @var array<string, string>
     */
    protected array $currencyIsoCodes = [];

    public function getWallet(): ArrayStruct
    {
        return $this->wallet;
    }

    public function setWallet(ArrayStruct $wallet): void
    {
        $this->wallet = $wallet;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(int $currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return array<string, string>
     */
    public function getCurrencyIsoCodes(): array
    {
        return $this->currencyIsoCodes;
    }

    /**
     * @param array<string, string> $currencyIsoCodes
     */
    public function setCurrencyIsoCodes(array $currencyIsoCodes): void
    {
        $this->currencyIsoCodes = $currencyIsoCodes;
    }
}
