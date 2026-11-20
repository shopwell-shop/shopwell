<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestStore;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResult;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalResultHandler;

require \dirname(__DIR__, 7) . '/src/Core/TestBootstrap.php';

$kernel = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $kernel->get(Connection::class);
$context = Context::createDefaultContext();
$request = $kernel->get(ApprovalRequestStore::class)->read($argv[2]);
$entity = $kernel->get('approval_flow_result.repository')->search(new Criteria([$argv[1]]), $context)->first();
if (!$entity instanceof ApprovalResultEntity) {
    throw new RuntimeException('The committed result is missing.');
}
$result = new ApprovalResult($entity->getId(), $entity->getInstanceId(), $entity->getReleaseId(), $entity->getCommandId(), $entity->getAggregateVersion(), $entity->getOutcome(), $entity->getFinishedAt());
$handler = $kernel->get(PaymentTransferApprovalResultHandler::class);
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();
try {
    $connection->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_result');
    echo 'SNAPSHOT' . \PHP_EOL;
    fgets(\STDIN);
    try {
        $handler->handle($result, $request, $context);
        throw new RuntimeException('The competing delivery passed the held transfer lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }
    fgets(\STDIN);
    $handler->handle($result, $request, $context);
    echo 'REPLAYED' . \PHP_EOL;
} finally {
    $connection->rollBack();
}
