<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * Delivers the dial codes the module knows.
 *
 * It is an extension point: the platform ships one implementation with the codes it is usually sold
 * in, an extension registers another one with the countries it needs. A country none of them lists is
 * no error - the number keeps an unknown country and is routed as GLOBAL.
 */
#[Package('framework')]
interface CountryDialCodeProviderInterface
{
    /**
     * The dial codes the provider knows, keyed by the ISO code of the country and written without the plus.
     *
     * @return array<string, string> e.g. ['CN' => '86']
     */
    public function getDialCodes(): array;
}
