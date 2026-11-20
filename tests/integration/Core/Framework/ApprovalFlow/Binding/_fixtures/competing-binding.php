<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingStore;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;

require \dirname(__DIR__, 7) . '/src/Core/TestBootstrap.php';

$container = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $container->get(Connection::class);
$store = $container->get(ApprovalBindingStore::class);
$mode = $argv[1];
$type = $argv[2];
$releaseId = $argv[3];
$business = new ApprovalBusinessTypeSpecification($type, '1.0.0', 'transfer', 'test:payment', '{"$id":"payment:transfer","type":"object"}');
$context = Context::createDefaultContext();
$verify = static function (): void {};
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();

try {
    $count = $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_binding WHERE business_type = :type', ['type' => $type]);
    echo 'SNAPSHOT:' . $count . \PHP_EOL;
    fgets(\STDIN);
    try {
        if ($mode === 'save') {
            $store->save($business, $releaseId, 0, $context, static function (): void {});
        } else {
            $store->activate($type, 2, 0, $context, $verify);
        }
        throw new RuntimeException('The competing operation passed the held head lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }
    fgets(\STDIN);
    try {
        if ($mode === 'save') {
            $store->save($business, $releaseId, 0, $context, static function (): void {});
        } else {
            $store->activate($type, 2, 0, $context, $verify);
        }
        throw new RuntimeException('The stale version was accepted.');
    } catch (ApprovalFlowException $exception) {
        echo 'CONFLICT:' . $exception->getErrorCode() . \PHP_EOL;
    }
    if ($mode !== 'save') {
        if ($mode === 'first-head') {
            $runtime = ApprovalRuntimeFixture::container($container, $argv[4]);
            $loader = new class($business) implements ApprovalBusinessTypeLoader {
                public function __construct(private readonly ApprovalBusinessTypeSpecification $specification)
                {
                }

                public function load(): array
                {
                    return [$this->specification];
                }
            };
            $service = new ApprovalBindingService($store, new ApprovalBusinessTypeRegistry([$loader]), $runtime->get(ApprovalReleaseLoader::class), $container->get('event_dispatcher'));
            $head = $service->activate($type, 2, 1, $context);
        } else {
            $head = $store->activate($type, 2, 1, $context, $verify);
        }
        echo 'ACTIVATED:' . $head->getActiveRevision() . ':' . $head->getVersion() . \PHP_EOL;
    }
} finally {
    $connection->rollBack();
}
