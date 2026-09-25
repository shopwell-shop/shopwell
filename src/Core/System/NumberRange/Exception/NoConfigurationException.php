<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\NumberRange\NumberRangeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use NumberRangeException::incrementStorageNotFound() instead
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NoConfigurationException extends NumberRangeException
{
    public function __construct(
        string $entityName,
        ?string $salesChannelId = null
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', NumberRangeException::class)
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::NO_CONFIGURATION_FOR_ENTITY,
            'No number range configuration found for entity "{{ entity }}" with sales channel "{{ salesChannelId }}".',
            ['entity' => $entityName, 'salesChannelId' => $salesChannelId]
        );
    }
}
