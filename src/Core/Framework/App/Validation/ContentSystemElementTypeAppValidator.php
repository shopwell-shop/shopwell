<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\Error\ContentSystemElementTypeSchemaError;
use Shopwell\Core\Framework\App\Validation\Error\Error;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemElementTypeAppValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly YamlTypeLoader $loader,
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
        $typesDir = $manifest->getPath() . '/Resources/content-system/types';
        $appName = $manifest->getMetadata()->getName();

        try {
            $this->loader->loadFromDirectory($typesDir, 'app:' . $appName, $appName);
        } catch (ContentSystemException $e) {
            return [new ContentSystemElementTypeSchemaError(
                $typesDir,
                $e->getMessage()
            )];
        }

        return [];
    }
}
