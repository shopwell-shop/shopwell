<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;

require \dirname(__DIR__, 8) . '/src/Core/TestBootstrap.php';

$container = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $container->get(Connection::class);
$service = $container->get(ApprovalInstanceService::class);
$instanceId = $argv[1];
$commandId = $argv[2];
$context = Context::createDefaultContext();
$compete = $argv[3] === 'enter-start'
    ? static fn () => $service->enterStart($instanceId, $commandId, 1, $context)
    : static fn () => $service->suspend($instanceId, $commandId, 1, 'Review required.', $context);
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();

try {
    $status = $connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]);
    $count = $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]);
    echo 'SNAPSHOT:' . $status . ':' . $count . \PHP_EOL;
    fgets(\STDIN);
    try {
        $compete();
        throw new RuntimeException('A competing command passed the held instance lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }
    fgets(\STDIN);
    try {
        $result = $compete();
        echo 'RECEIPT:' . $result->status->value . ':' . $result->aggregateVersion . \PHP_EOL;
    } catch (ApprovalFlowException $exception) {
        if ($exception->getErrorCode() !== ApprovalFlowException::INSTANCE_VERSION_CONFLICT) {
            throw $exception;
        }
        echo 'CONFLICT:' . $exception->getParameters()['actualVersion'] . \PHP_EOL;
    }
} finally {
    $connection->rollBack();
}
