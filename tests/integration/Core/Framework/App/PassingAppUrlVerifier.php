<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App;

use Shopwell\Core\DevOps\Environment\EnvironmentHelper;
use Shopwell\Core\Framework\App\Url\AppUrlVerifier;
use Shopwell\Core\Framework\App\Url\VerificationState;
use Shopwell\Core\Framework\App\Url\VerificationStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class PassingAppUrlVerifier extends AppUrlVerifier
{
    public function __construct()
    {
    }

    public function forceVerify(): VerificationState
    {
        return new VerificationState((string) EnvironmentHelper::getVariable('APP_URL'), VerificationStatus::PASS, 1, new \DateTimeImmutable(), info: null, statusCode: null);
    }

    public function verifyForAdministration(): bool
    {
        return true;
    }
}
