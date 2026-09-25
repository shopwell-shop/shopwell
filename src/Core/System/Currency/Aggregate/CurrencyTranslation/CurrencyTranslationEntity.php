<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency\Aggregate\CurrencyTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class CurrencyTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $currencyId;

    protected ?string $shortName = null;

    protected ?string $name = null;

    protected ?CurrencyEntity $currency = null;

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }
}
