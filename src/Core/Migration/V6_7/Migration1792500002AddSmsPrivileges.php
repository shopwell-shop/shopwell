<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Hands the privileges of the sms component to the roles that already exist.
 *
 * The mapping in the administration only decides what the role editor offers from now on, a role
 * that was created before the component existed keeps the list it was stored with - the sms screens
 * would stay closed for it. The privileges are appended to the roles that maintain the shop's
 * configuration, which is the audience the component belongs to; a shop that wants to hand out the
 * access more narrowly revokes it in the role editor.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792500002AddSmsPrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'system.system_config' => [
            'sms_gateway:read',
            'sms_gateway:create',
            'sms_gateway:update',
            'sms_gateway:delete',
            'sms_template:read',
            'sms_template:create',
            'sms_template:update',
            'sms_template:delete',
            'sms_template_mapping:read',
            'sms_template_mapping:create',
            'sms_template_mapping:update',
            'sms_template_mapping:delete',
            // the record of what went out is read only, no role may change an attempt that was made
            'sms_delivery:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1792500002;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
