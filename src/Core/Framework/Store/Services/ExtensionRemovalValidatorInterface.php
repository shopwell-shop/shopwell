<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Services;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\StoreException;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
interface ExtensionRemovalValidatorInterface
{
    /**
     * @throws StoreException when the extension is still in use and must not be removed
     */
    public function validateCanBeRemoved(string $technicalName, string $extensionId, Context $context): void;
}
