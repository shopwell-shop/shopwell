<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ShopId\Fingerprint;

use Shopwell\Core\DevOps\Environment\EnvironmentHelper;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\ShopId\Fingerprint;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class AppUrl implements Fingerprint
{
    final public const IDENTIFIER = 'app_url';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * Changing the APP_URL usually indicates with near certainty that the shop has been permanently moved or has been cloned to a new environment.
     */
    public function getScore(): int
    {
        return 100;
    }

    public function getStamp(): string
    {
        return (string) EnvironmentHelper::getVariable('APP_URL') ?: throw AppException::appUrlNotConfigured();
    }
}
