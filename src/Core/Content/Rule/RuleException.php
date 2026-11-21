<?php

declare(strict_types=1);

namespace Shopwell\Core\Content\Rule;

use Shopwell\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class RuleException extends HttpException
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public static function unsupportedCommandType(WriteCommand $command): HttpException
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.8.0.0'));

        return new UnsupportedCommandTypeException($command);
    }
}
