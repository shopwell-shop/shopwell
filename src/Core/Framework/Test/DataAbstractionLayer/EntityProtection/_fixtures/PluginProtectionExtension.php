<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopwell\Core\Framework\Plugin\PluginDefinition;

/**
 * @internal
 */
class PluginProtectionExtension extends EntityExtension
{
    public function extendProtections(EntityProtectionCollection $protections): void
    {
        $protections->add(new ReadProtection(Context::SYSTEM_SCOPE, Context::USER_SCOPE));
        $protections->add(new WriteProtection(Context::SYSTEM_SCOPE, Context::USER_SCOPE));
    }

    public function getEntityName(): string
    {
        return PluginDefinition::ENTITY_NAME;
    }
}
