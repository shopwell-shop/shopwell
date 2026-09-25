<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * The `config-schema.json` describes the `shopwell.yaml` structure and should follow its changes.
 *
 * @internal
 */
#[Package('framework')]
class ShopwellYamlConfigSchemaHint
{
    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        $shopwellYamlTouched = $files->matches('*/shopwell.yaml')->count() > 0;
        $configSchemaTouched = $files->matches('config-schema.json')->count() > 0;

        if ($shopwellYamlTouched && !$configSchemaTouched) {
            $context->warning('You updated the shopwell.yaml, please consider to update the config-schema.json');
        }
    }
}
