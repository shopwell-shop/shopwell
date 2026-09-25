<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\SystemCheck\Check;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum Status implements \JsonSerializable
{
    case OK;
    case UNKNOWN;
    case SKIPPED;
    case WARNING;
    case ERROR;
    case FAILURE;

    public function jsonSerialize(): string
    {
        return $this->name;
    }
}
