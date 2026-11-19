<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;

require \dirname(__DIR__, 7) . '/src/Core/TestBootstrap.php';

$container = KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $container->get(Connection::class);
$compiler = PublicationFixture::releaseCompiler(
    $container->get(NodePlanCompiler::class),
    $container->get(NodeCompilerRegistry::class),
    $container->get(NodeExecutorRegistry::class),
    new Filesystem(),
    $argv[3],
);
$publisher = new ApprovalReleaseService($container->get(ApprovalReleaseStore::class), $compiler);
$definitionId = $argv[1];
$expectedHash = $argv[2];
$context = Context::createDefaultContext();
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->beginTransaction();

try {
    $count = $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_release WHERE definition_id = :id', ['id' => Uuid::fromHexToBytes($definitionId)]);
    echo 'SNAPSHOT:' . $count . \PHP_EOL;
    fgets(\STDIN);
    try {
        $publisher->publish($definitionId, 1, $expectedHash, $context);
        throw new RuntimeException('A competing publisher passed the held row lock.');
    } catch (LockWaitTimeoutException) {
        echo 'BLOCKED' . \PHP_EOL;
    }
    fgets(\STDIN);
    $release = $publisher->publish($definitionId, 1, $expectedHash, $context);
    echo 'REUSED:' . $release->id . \PHP_EOL;
} finally {
    $connection->rollBack();
}
