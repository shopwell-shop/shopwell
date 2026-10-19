<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * The one place that answers which country a dial code belongs to and which dial code a country has.
 *
 * It merges every registered provider once, when the container builds it, so a lookup on the send path
 * is a hash map read and never a sort - the number of a message does not pay for the shape of the
 * data. The prefixes are indexed by their length, so the longest one a number starts with is found
 * with at most a handful of comparisons.
 *
 * A lookup that finds nothing is a normal answer and not an error: the country of a number the data
 * does not list stays unknown, and the routing hands it to a gateway that serves GLOBAL.
 *
 * The extension point is the provider it reads, not the resolver itself.
 *
 * @internal
 */
#[Package('framework')]
final class CountryDialCodeResolver
{
    /**
     * @var array<string, string> ISO code => dial code
     */
    private readonly array $byIso;

    /**
     * @var array<string, string> dial code => ISO code
     */
    private readonly array $byDialCode;

    /**
     * @var list<int> the lengths of the dial codes, longest first
     */
    private readonly array $dialCodeLengths;

    /**
     * @param iterable<CountryDialCodeProviderInterface> $providers the registered providers, a later one wins over an earlier one
     */
    public function __construct(iterable $providers)
    {
        $byIso = [];

        foreach ($providers as $provider) {
            // a later provider overrides a code an earlier one declared, so an extension can correct
            // a country the platform ships with
            $byIso = array_replace($byIso, array_change_key_case($provider->getDialCodes(), \CASE_UPPER));
        }

        $lengths = [];

        foreach ($byIso as $dialCode) {
            $lengths[\strlen($dialCode)] = true;
        }

        $lengths = array_keys($lengths);
        rsort($lengths);

        $this->byIso = $byIso;
        $this->byDialCode = array_flip($byIso);
        $this->dialCodeLengths = $lengths;
    }

    /**
     * The ISO code of a dial code, null when the data does not know it.
     */
    public function isoOfDialCode(string $dialCode): ?string
    {
        return $this->byDialCode[$dialCode] ?? null;
    }

    /**
     * The dial code of an ISO code, null when the data does not know it.
     */
    public function dialCodeOfIso(string $iso): ?string
    {
        return $this->byIso[strtoupper($iso)] ?? null;
    }

    /**
     * The longest dial code the digits start with, null when the data does not know one of them.
     */
    public function matchPrefix(string $digits): ?string
    {
        foreach ($this->dialCodeLengths as $length) {
            if (\strlen($digits) < $length) {
                continue;
            }

            $prefix = substr($digits, 0, $length);

            if (isset($this->byDialCode[$prefix])) {
                return $prefix;
            }
        }

        return null;
    }
}
