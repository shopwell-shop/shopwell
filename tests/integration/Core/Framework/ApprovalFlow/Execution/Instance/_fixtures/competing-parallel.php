<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ParallelApprovalFixture;

require \dirname(__DIR__, 8) . '/src/Core/TestBootstrap.php';

[$script, $instanceId, $executionId, $taskId, $userId, $commandId, $manifest, $operation, $version] = $argv;
$kernel = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $kernel->get(Connection::class);
$runtime = ParallelApprovalFixture::container($kernel, $manifest);
$service = $runtime->get(ApprovalInstanceService::class);
$source = new AdminApiSource($userId);
$source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
$reviewer = Context::createDefaultContext($source);
$execute = static function () use ($operation, $service, $instanceId, $executionId, $taskId, $commandId, $version, $reviewer) {
    if ($operation === 'decide') {
        return $service->decide($instanceId, $taskId, $commandId, (int) $version, ApprovalOutcome::Approved, '', $reviewer);
    }

    return $service->progress($instanceId, $executionId, $commandId, (int) $version, Context::createDefaultContext());
};
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();
try {
    $parameters = ['instance' => Uuid::fromHexToBytes($instanceId)];
    $connection->fetchFirstColumn('SELECT id FROM approval_flow_execution WHERE instance_id = :instance', $parameters);
    $connection->fetchFirstColumn('SELECT branch.id FROM approval_flow_execution_branch branch INNER JOIN approval_flow_execution_scope scope ON scope.id = branch.scope_id WHERE scope.instance_id = :instance', $parameters);
    echo 'SNAPSHOT', \PHP_EOL;
    fgets(\STDIN);
    try {
        $execute();
        throw new RuntimeException('Parallel command bypassed the instance lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED', \PHP_EOL;
    }
    fgets(\STDIN);
    if ($operation === 'decide') {
        try {
            $execute();
            throw new RuntimeException('A cancelled sibling accepted a late opinion.');
        } catch (ApprovalFlowException $exception) {
            if ($exception->getErrorCode() !== ApprovalFlowException::INVALID_INSTANCE_TRANSITION) {
                throw $exception;
            }
            echo 'CLOSED', \PHP_EOL;
        }
    } else {
        $receipt = $execute();
        $replay = $execute();
        if (get_object_vars($receipt) !== get_object_vars($replay)) {
            throw new RuntimeException('A parallel command did not replay its original receipt: ' . json_encode([$receipt, $replay], \JSON_THROW_ON_ERROR));
        }
        echo 'FINISHED:', $receipt->outcome?->value, ':', $receipt->aggregateVersion, \PHP_EOL;
    }
    $connection->commit();
} finally {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
}
