<?php declare(strict_types=1);

namespace Shopwell\Core\System\Consent;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('data-services')]
enum ConsentStatus: string
{
    case UNSET = 'unset';
    case ACCEPTED = 'accepted';
    case REVOKED = 'revoked';
    case DECLINED = 'declined';
}
