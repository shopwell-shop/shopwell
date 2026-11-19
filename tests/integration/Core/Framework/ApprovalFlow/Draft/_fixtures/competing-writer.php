<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

require \dirname(__DIR__, 7) . '/src/Core/TestBootstrap.php';

$container = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $container->get(Connection::class);
$service = $container->get('test.approval_flow.draft_service');
\assert($service instanceof ApprovalDraftService);
$definitionId = $argv[1];
$context = Context::createDefaultContext();

$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();

try {
    $draft = $service->read($definitionId, $context);
    echo 'SNAPSHOT:' . $draft->revision . \PHP_EOL;
    fgets(\STDIN);

    try {
        $service->removeConnection($definitionId, 1, 'start-review', confirmRemoval: true, context: $context);
        throw new RuntimeException('A competing writer passed the held row lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }

    fgets(\STDIN);

    try {
        $service->removeConnection($definitionId, 1, 'start-review', confirmRemoval: true, context: $context);
        throw new RuntimeException('A stale snapshot overwrote the committed edit.');
    } catch (ApprovalFlowException $exception) {
        if ($exception->getErrorCode() !== ApprovalFlowException::DRAFT_REVISION_CONFLICT) {
            throw $exception;
        }
        echo 'CONFLICT:' . $exception->getParameters()['actualRevision'] . \PHP_EOL;
    }
} finally {
    $connection->rollBack();
}
