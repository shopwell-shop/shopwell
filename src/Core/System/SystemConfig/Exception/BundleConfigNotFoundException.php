<?php declare(strict_types=1);

namespace Shopwell\Core\System\SystemConfig\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class BundleConfigNotFoundException extends SystemConfigException
{
    public function __construct(
        string $configPath,
        string $bundleName
    ) {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::BUNDLE_CONFIG_NOT_FOUND,
            'Bundle configuration for path "{{ configPath }}" in bundle "{{ bundleName }}" not found.',
            ['configPath' => $configPath, 'bundleName' => $bundleName]
        );
    }
}
