<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795147770CreateApprovalFlowRequests extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795147770;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_request` (
                `id` BINARY(16) NOT NULL,
                `business_type` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `business_id` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL,
                `submission_round` INT UNSIGNED NOT NULL,
                `business_revision` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `binding_id` BINARY(16) NOT NULL,
                `instance_id` BINARY(16) NOT NULL,
                `initiator_id` BINARY(16) NOT NULL,
                `form_json` LONGTEXT NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_request.business` (`business_type`, `business_id`, `submission_round`),
                UNIQUE KEY `uniq.approval_flow_request.instance` (`instance_id`),
                CONSTRAINT `fk.approval_flow_request.binding` FOREIGN KEY (`binding_id`) REFERENCES `approval_flow_binding` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_request.instance` FOREIGN KEY (`instance_id`) REFERENCES `approval_flow_instance` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_request.round` CHECK (`submission_round` > 0),
                CONSTRAINT `json.approval_flow_request.form` CHECK (JSON_VALID(`form_json`))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
