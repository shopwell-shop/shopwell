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

require \dirname(__DIR__, 8) . '/src/Core/TestBootstrap.php';

$container = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $container->get(Connection::class);
$service = $container->get(ApprovalInstanceService::class);
[$script, $instanceId, $taskId, $userId] = $argv;
$commandId = Uuid::randomHex();
$source = new AdminApiSource($userId);
$source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
$context = Context::createDefaultContext($source);
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();

try {
    $status = $connection->fetchOne('SELECT status FROM approval_flow_task WHERE id = :id', ['id' => Uuid::fromHexToBytes($taskId)]);
    echo 'SNAPSHOT:' . $status . \PHP_EOL;
    fgets(\STDIN);
    try {
        $service->decide($instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, 'Second reviewer.', $context);
        throw new RuntimeException('The second decision bypassed the instance lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }
    fgets(\STDIN);
    try {
        $service->decide($instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, 'Second reviewer.', $context);
        throw new RuntimeException('The second decision accepted the old instance version.');
    } catch (ApprovalFlowException $exception) {
        if ($exception->getErrorCode() !== ApprovalFlowException::INSTANCE_VERSION_CONFLICT) {
            throw $exception;
        }
    }
    try {
        $result = $service->decide($instanceId, $taskId, $commandId, 4, ApprovalOutcome::Approved, 'Second reviewer.', $context);
        echo 'DECIDED:' . $result->aggregateVersion . \PHP_EOL;
    } catch (ApprovalFlowException $exception) {
        if ($exception->getErrorCode() !== ApprovalFlowException::TASK_NOT_PENDING) {
            throw $exception;
        }
        echo 'CANCELLED' . \PHP_EOL;
    }
    $connection->commit();
} finally {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
}
