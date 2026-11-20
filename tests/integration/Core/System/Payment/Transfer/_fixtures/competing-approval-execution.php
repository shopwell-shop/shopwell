<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;

require \dirname(__DIR__, 7) . '/src/Core/TestBootstrap.php';

$kernel = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $kernel->get(Connection::class);
$booker = $kernel->get(PaymentTransferResultBooker::class);
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();
try {
    $transfer = $kernel->get(PaymentTransferService::class)->getById($argv[1]);
    $connection->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_result');
    echo 'SNAPSHOT' . \PHP_EOL;
    fgets(\STDIN);
    try {
        $booker->claim($transfer);
        throw new RuntimeException('The competing payout claim passed the held transfer lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }
    fgets(\STDIN);
    try {
        $booker->claim($transfer);
        throw new RuntimeException('Changed payout facts were authorized from an older snapshot.');
    } catch (PaymentTransferApprovalException $exception) {
        if ($exception->getParameter('reason') !== 'changed_transfer') {
            throw $exception;
        }
        echo 'REJECTED' . \PHP_EOL;
    }
} finally {
    $connection->rollBack();
}
