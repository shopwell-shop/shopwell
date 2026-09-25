<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\Error\AppNameError;
use Shopwell\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppNameValidator extends AbstractManifestValidator
{
    public function validate(Manifest $manifest, ?Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $appName = strtolower(substr($manifest->getPath(), (int) strrpos($manifest->getPath(), '/') + 1));

        if ($appName !== strtolower($manifest->getMetadata()->getName())) {
            $errors->add(new AppNameError($manifest->getMetadata()->getName()));
        }

        return $errors;
    }
}
