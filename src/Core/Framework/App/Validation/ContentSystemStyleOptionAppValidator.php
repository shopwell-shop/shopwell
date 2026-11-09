<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation;

use Shopwell\Core\Framework\App\Lifecycle\Persister\ContentSystemStyleOptionPersister;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\Error\ContentSystemStyleOptionSchemaError;
use Shopwell\Core\Framework\App\Validation\Error\Error;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemStyleOptionAppValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly YamlStyleOptionLoader $loader,
    ) {
    }

    /**
     * Validates schema structure only (syntax, required fields, constraints), not name collisions.
     * Collision detection runs later in the persister when the app is actually installed.
     *
     * @return list<Error>
     */
    public function validate(Manifest $manifest, Context $context): array
    {
        $directory = $manifest->getPath() . '/' . ContentSystemStyleOptionPersister::STYLE_OPTIONS_DIRECTORY;
        $appName = $manifest->getMetadata()->getName();

        try {
            $this->loader->loadDtosFromDirectory($directory, 'app:' . $appName);
        } catch (ContentSystemException $e) {
            return [new ContentSystemStyleOptionSchemaError($directory, $e->getMessage())];
        }

        return [];
    }
}
