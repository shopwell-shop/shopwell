<?php

declare(strict_types=1);

namespace Shopwell\Core\Content\Rule;

use Shopwell\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class RuleException extends HttpException
{
    public static function unsupportedCommandType(WriteCommand $command): HttpException
    {
        return new UnsupportedCommandTypeException($command);
    }
}
