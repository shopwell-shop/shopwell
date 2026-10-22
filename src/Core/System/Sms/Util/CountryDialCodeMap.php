<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * The dial codes the platform ships with, keyed by the ISO code of the country.
 *
 * The list is deliberately not the full ITU table: a number of a country that is not listed is still
 * a valid number, it simply has no country the routing could use and is treated as GLOBAL. Adding
 * countries is the job of a CountryDialCodeProviderInterface an extension registers, not of a change
 * of this table.
 *
 * @internal
 */
#[Package('framework')]
final class CountryDialCodeMap
{
    /**
     * @var array<string, string>
     */
    public const DIAL_CODES = [
        'AE' => '971',
        'SA' => '966',
        'NG' => '234',
        'HK' => '852',
        'MO' => '853',
        'TW' => '886',
        'CN' => '86',
        'JP' => '81',
        'KR' => '82',
        'SG' => '65',
        'MY' => '60',
        'TH' => '66',
        'VN' => '84',
        'ID' => '62',
        'IN' => '91',
        'GB' => '44',
        'DE' => '49',
        'FR' => '33',
        'IT' => '39',
        'ES' => '34',
        'NL' => '31',
        'AU' => '61',
        'NZ' => '64',
        'BR' => '55',
        'ZA' => '27',
        'EG' => '20',
        'RU' => '7',
        'US' => '1',
    ];
}
