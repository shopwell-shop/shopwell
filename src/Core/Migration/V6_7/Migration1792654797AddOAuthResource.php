<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1792654797AddOAuthResource extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792654797;
    }

    public function update(Connection $connection): void
    {
        // RFC 8707 resource the authorization code or the refresh token's grant is bound to
        $this->addColumn($connection, 'oauth_auth_code', 'resource', 'VARCHAR(2048)');
        $this->addColumn($connection, 'refresh_token', 'resource', 'VARCHAR(2048)');
    }
}
