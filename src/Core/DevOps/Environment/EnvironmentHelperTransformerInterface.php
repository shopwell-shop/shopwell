<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\Environment;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void;
}
