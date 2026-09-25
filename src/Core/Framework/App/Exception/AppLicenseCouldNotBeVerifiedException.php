<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Exception;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppLicenseCouldNotBeVerifiedException extends AppRegistrationException
{
    public function getErrorCode(): string
    {
        return 'FRAMEWORK__APP_LICENSE_COULD_NOT_BE_VERIFIED';
    }
}
