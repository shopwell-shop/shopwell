<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Twig\Exception;

use Shopwell\Core\Framework\Adapter\AdapterException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class TwigMethodCallNotAllowedException extends AdapterException
{
}
