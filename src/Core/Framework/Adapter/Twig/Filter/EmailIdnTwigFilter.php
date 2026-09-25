<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Twig\Filter;

use Shopwell\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopwell\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
#[Package('checkout')]
class EmailIdnTwigFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('decodeIdnEmail', EmailIdnConverter::decode(...)),
            new TwigFilter('encodeIdnEmail', EmailIdnConverter::encode(...)),
        ];
    }
}
