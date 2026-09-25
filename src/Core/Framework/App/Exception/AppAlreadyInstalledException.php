<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Exception;

use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppAlreadyInstalledException extends AppException
{
}
