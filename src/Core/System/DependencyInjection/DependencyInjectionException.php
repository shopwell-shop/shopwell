<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Shopwell\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class DependencyInjectionException extends HttpException
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public const NUMBER_RANGE_REDIS_NOT_CONFIGURED = 'SYSTEM__NUMBER_RANGE_REDIS_NOT_CONFIGURED';

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public static function redisNotConfiguredForNumberRangeIncrementer(): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'),
        );

        return new self(
            500,
            self::NUMBER_RANGE_REDIS_NOT_CONFIGURED,
            'Parameter "shopware.number_range.config.connection" is required for redis storage'
        );
    }

    public static function definitionNotFound(string $entity): DefinitionNotFoundException
    {
        return new DefinitionNotFoundException($entity);
    }
}
