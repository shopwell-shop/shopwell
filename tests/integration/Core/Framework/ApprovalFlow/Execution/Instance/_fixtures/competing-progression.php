<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;

require \dirname(__DIR__, 8) . '/src/Core/TestBootstrap.php';

[$script, $instanceId, $executionId, $commandId, $manifest, $stage, $version] = $argv;
$kernel = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $kernel->get(Connection::class);
$runtime = ApprovalRuntimeFixture::container($kernel, $manifest);
$service = $runtime->get(ApprovalInstanceService::class);
$context = Context::createDefaultContext();
$execute = static function (int $expectedVersion) use ($service, $instanceId, $executionId, $commandId, $context, $stage): ApprovalInstanceCommandResult {
    if ($stage === 'progress') {
        return $service->progress($instanceId, $executionId, $commandId, $expectedVersion, $context);
    }
    if ($stage === 'end') {
        return $service->enterEnd($instanceId, $executionId, $commandId, $expectedVersion, $context);
    }

    return $service->advanceApproval($instanceId, $executionId, $commandId, $expectedVersion, $context);
};
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();

try {
    $status = $connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]);
    // Also establish the older execution snapshot: the competing successor must not be derived from it.
    $connection->fetchFirstColumn('SELECT id FROM approval_flow_execution WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]);
    echo 'SNAPSHOT:' . $status . \PHP_EOL;
    fgets(\STDIN);
    try {
        $execute((int) $version);
        throw new RuntimeException('Progression bypassed the instance lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }
    fgets(\STDIN);
    try {
        $receipt = $execute((int) $version);
        echo 'RECEIPT:' . $receipt->status->value . ':' . ($receipt->outcome?->value ?? 'none') . ':' . $receipt->aggregateVersion . \PHP_EOL;
    } catch (ApprovalFlowException $exception) {
        if ($exception->getErrorCode() !== ApprovalFlowException::INSTANCE_VERSION_CONFLICT) {
            throw $exception;
        }
        try {
            $execute((int) $version + 1);
            throw new RuntimeException('A new command expanded or completed the execution twice.');
        } catch (ApprovalFlowException $exception) {
            $expected = ($stage === 'end' || $stage === 'progress') ? ApprovalFlowException::INVALID_INSTANCE_TRANSITION : ApprovalFlowException::INVALID_NODE_PROGRESSION;
            if ($exception->getErrorCode() !== $expected) {
                throw $exception;
            }
            echo 'GUARDED' . \PHP_EOL;
        }
    }
    $connection->commit();
} finally {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
}
