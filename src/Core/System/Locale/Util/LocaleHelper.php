<?php declare(strict_types=1);

namespace Shopwell\Core\System\Locale\Util;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;

#[Package('discovery')]
class LocaleHelper
{
    private const LEGACY_REGION_CODES = [
        'CS',
    ];

    public static function isLocale(string $locale): bool
    {
        // intl refuses a locale it cannot parse with a ValueError, the intl stubs of phpstan do not
        // declare that throw, so the catch below looks dead to the analysis while it is reached at
        // runtime: new \NumberFormatter('xx_YY', \NumberFormatter::CURRENCY) throws on PHP 8.5
        try {
            new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        } catch (\ValueError) { // @phpstan-ignore catch.neverThrown
            return false;
        }

        $canonicalized = \Locale::canonicalize($locale);

        if ($canonicalized === null) {
            return false;
        }

        $parsed = \Locale::parseLocale($canonicalized);

        return isset($parsed['language'], $parsed['region'])
            && Languages::exists($parsed['language'])
            && (
                Countries::exists($parsed['region'])
                || \in_array($parsed['region'], LocaleHelper::LEGACY_REGION_CODES, true)
            );
    }
}
