<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * @internal
 */
#[Package('framework')]
class Migration1739198249FixOrderDeliveryStateMachineName extends MigrationStep
{
    use ImportTranslationsTrait;

    private const LOCALE_EN_GB = 'en-GB';
    private const LOCALE_DE_DE = 'de-DE';

    public function getCreationTimestamp(): int
    {
        return 1739198249;
    }

    public function update(Connection $connection): void
    {
        $germanIds = $this->getLanguageIds($connection, self::LOCALE_DE_DE);
        $englishIds = array_unique(array_diff(
            array_merge($this->getLanguageIds($connection, self::LOCALE_EN_GB), [Defaults::LANGUAGE_SYSTEM]),
            $germanIds
        ));

        $stateMachineId = $connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = :technicalName', ['technicalName' => OrderDeliveryStates::STATE_MACHINE]);
        if (!\is_string($stateMachineId) || $stateMachineId === '') {
            return;
        }

        if ($germanIds !== []) {
            $connection->executeStatement('UPDATE state_machine_translation SET name = :name WHERE state_machine_id = :stateMachineId AND language_id IN (:languageIds) AND updated_at IS NULL', [
                'name' => 'Versandstatus',
                'stateMachineId' => $stateMachineId,
                'languageIds' => Uuid::fromHexToBytesList($germanIds),
            ], [
                'name' => ParameterType::STRING,
                'stateMachineId' => ParameterType::BINARY,
                'languageIds' => ArrayParameterType::BINARY,
            ]);
        }

        if ($englishIds !== []) {
            $connection->executeStatement('UPDATE state_machine_translation SET name = :name WHERE state_machine_id = :stateMachineId AND language_id IN (:languageIds) AND updated_at IS NULL', [
                'name' => 'Delivery state',
                'stateMachineId' => $stateMachineId,
                'languageIds' => Uuid::fromHexToBytesList($englishIds),
            ], [
                'name' => ParameterType::STRING,
                'stateMachineId' => ParameterType::BINARY,
                'languageIds' => ArrayParameterType::BINARY,
            ]);
        }
    }
}
