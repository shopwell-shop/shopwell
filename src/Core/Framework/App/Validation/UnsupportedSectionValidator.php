<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\Error\Error;
use Shopwell\Core\Framework\App\Validation\Error\UnsupportedSectionError;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class UnsupportedSectionValidator extends AbstractManifestValidator
{
    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $errors = [];
        foreach ($manifest->unsupportedSections as $section) {
            $errors[] = new UnsupportedSectionError($section);
        }

        return $errors;
    }
}
