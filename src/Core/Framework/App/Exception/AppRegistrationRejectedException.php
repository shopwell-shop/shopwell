<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Exception;

use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppRegistrationRejectedException extends AppRegistrationException
{
    public function getErrorCode(): string
    {
        return AppException::APP_REGISTRATION_REJECTED;
    }
}
