<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\Test\Environment\_fixtures;

use Shopwell\Core\DevOps\Environment\EnvironmentHelperTransformerData;
use Shopwell\Core\DevOps\Environment\EnvironmentHelperTransformerInterface;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class EnvironmentHelperTransformer implements EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void
    {
        $data->setValue($data->getValue() !== null ? $data->getValue() . ' bar' : null);
        $data->setDefault($data->getDefault() . ' baz');
    }
}
