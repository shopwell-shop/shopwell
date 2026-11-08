<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\Framework\App\Validation\Error\AppNameError;
use Shopwell\Core\Framework\App\Validation\Error\Error;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppNameValidator extends AbstractManifestValidator
{
    public function __construct(private readonly SourceResolver $sourceResolver)
    {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $directory = strtolower(basename($this->sourceResolver->filesystemForManifest($manifest)->location));

        if ($directory === strtolower($manifest->getMetadata()->getName())) {
            return [];
        }

        return [new AppNameError($manifest->getMetadata()->getName())];
    }
}
