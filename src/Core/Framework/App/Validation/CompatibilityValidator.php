<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation;

use Composer\Semver\VersionParser;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\Error\Error;
use Shopwell\Core\Framework\App\Validation\Error\IncompatibleAppError;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class CompatibilityValidator extends AbstractManifestValidator
{
    public function __construct(private readonly string $shopwellVersion)
    {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $versionParser = new VersionParser();
        if ($manifest->getMetadata()->getCompatibility()->matches($versionParser->parseConstraints($this->shopwellVersion))) {
            return [];
        }

        return [new IncompatibleAppError($manifest->getMetadata()->getName())];
    }
}
