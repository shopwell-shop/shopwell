<?php declare(strict_types=1);

namespace Shopwell\Core\System\SystemConfig\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - Will be removed, use SystemConfigException::invalidDomain() instead
 */
class InvalidDomainException extends SystemConfigException
{
    public function __construct(string $domain)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'SystemConfigException::invalidDomain()')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOMAIN,
            'Invalid domain \'{{ domain }}\'',
            ['domain' => $domain]
        );
    }
}
