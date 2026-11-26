<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestService;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestStore;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;

require \dirname(__DIR__, 7) . '/src/Core/TestBootstrap.php';

$container = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $container->get(Connection::class);
$runtime = ApprovalRuntimeFixture::container($container, $argv[3]);
$service = new PaymentTransferApprovalRequestService(
    $container->get(PaymentTransferApprovalRequestStore::class),
    $runtime->get(ApprovalRequestService::class),
    $container->get(PaymentTransferPresenter::class),
);
$transferId = $argv[1];
$source = new AdminApiSource($argv[2]);
$source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
$context = Context::createDefaultContext($source);
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();
$submit = static function () use ($service, $transferId, $context): void {
    $request = $service->submit($transferId, $context);
    echo 'RESULT:' . json_encode([
        'id' => $request->getId(),
        'bindingId' => $request->getBindingId(),
        'form' => json_decode($request->getFormJson(), associative: true, flags: \JSON_THROW_ON_ERROR),
    ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE) . \PHP_EOL;
};

try {
    // Establish an old repeatable-read snapshot before the other connection changes any facts.
    $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_request');
    echo 'SNAPSHOT' . \PHP_EOL;
    fgets(\STDIN);
    try {
        $submit();
        throw new RuntimeException('The competing submission passed the held transfer lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }
    fgets(\STDIN);
    $submit();
} finally {
    $connection->rollBack();
}
