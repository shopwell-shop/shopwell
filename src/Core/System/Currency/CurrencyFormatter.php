<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\Locale\Util\BackwardCompatibleNumberFormatter;
use Symfony\Contracts\Service\ResetInterface;

#[Package('fundamentals@framework')]
class CurrencyFormatter implements ResetInterface
{
    /**
     * @var \NumberFormatter[]
     */
    private array $formatter = [];

    /**
     * @internal
     */
    public function __construct(private readonly LanguageLocaleCodeProvider $languageLocaleProvider)
    {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function formatCurrencyByLanguage(float $price, string $currency, string $languageId, Context $context, ?int $decimals = null): string
    {
        $decimals ??= $context->getRounding()->getDecimals();

        $formatter = $this->getFormatter(
            $this->languageLocaleProvider->getLocaleForLanguageId($languageId)
        );
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

        return (string) $formatter->formatCurrency($price, $currency);
    }

    public function reset(): void
    {
        $this->formatter = [];
    }

    private function getFormatter(string $locale): \NumberFormatter
    {
        if (isset($this->formatter[$locale])) {
            return $this->formatter[$locale];
        }

        return $this->formatter[$locale] = new BackwardCompatibleNumberFormatter($locale, \NumberFormatter::CURRENCY);
    }
}
