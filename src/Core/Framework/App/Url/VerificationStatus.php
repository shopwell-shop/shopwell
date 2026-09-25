<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Url;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum VerificationStatus
{
    case PASS;
    case HARD_FAIL;
    case SOFT_FAIL;
}
