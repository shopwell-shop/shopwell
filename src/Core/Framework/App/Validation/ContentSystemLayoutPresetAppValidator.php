<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\Error\ContentSystemLayoutPresetSchemaError;
use Shopwell\Core\Framework\App\Validation\Error\Error;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\Loader\ContentSystemLayoutPresetAppLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemLayoutPresetAppValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly ContentSystemLayoutPresetAppLoader $appPresetLoader,
    ) {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, Context $context): array
    {
        $presetsDir = $manifest->getPath() . '/Resources/content-system/presets';
        $appName = $manifest->getMetadata()->getName();

        try {
            $this->appPresetLoader->load($presetsDir, $appName);
        } catch (ContentSystemException $e) {
            return [new ContentSystemLayoutPresetSchemaError(
                $presetsDir,
                $e->getMessage()
            )];
        }

        return [];
    }
}
