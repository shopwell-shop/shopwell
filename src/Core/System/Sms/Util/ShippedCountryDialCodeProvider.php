<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * The dial codes the platform itself brings, read from the shipped table.
 *
 * @internal
 */
#[Package('framework')]
final class ShippedCountryDialCodeProvider implements CountryDialCodeProviderInterface
{
    public function getDialCodes(): array
    {
        return CountryDialCodeMap::DIAL_CODES;
    }
}
