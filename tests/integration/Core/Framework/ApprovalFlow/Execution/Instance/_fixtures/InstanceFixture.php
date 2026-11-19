<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Inserts runtime facts for command-boundary tests; this is not an instance activation path.
 *
 * @internal
 */
final class InstanceFixture
{
    public static function publish(ContainerInterface $container): string
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/approval-instance-' . bin2hex(random_bytes(8));
        $filesystem->mkdir($directory);
        try {
            $path = PublicationFixture::writeManifest($filesystem, $directory);
            $compiler = PublicationFixture::releaseCompiler(
                $container->get(NodePlanCompiler::class),
                $container->get(NodeCompilerRegistry::class),
                $container->get(NodeExecutorRegistry::class),
                $filesystem,
                $path,
            );
            $context = Context::createDefaultContext();
            $draft = $container->get(ApprovalDraftService::class)->create('Instance command fixture', GraphFixture::json(), $context);
            $compiled = $compiler->compile($draft->graph);
            $service = new ApprovalReleaseService($container->get(ApprovalReleaseStore::class), $compiler);

            return $service->publish($draft->definitionId, $draft->revision, $compiled->hash, $context)->id;
        } finally {
            $filesystem->remove($directory);
        }
    }

    public static function create(Connection $connection, string $releaseId, ApprovalInstanceStatus $status): string
    {
        $id = Uuid::randomHex();
        $connection->insert('approval_flow_instance', [
            'id' => Uuid::fromHexToBytes($id),
            'release_id' => Uuid::fromHexToBytes($releaseId),
            'status' => $status->value,
            'aggregate_version' => 1,
            'created_at' => '2026-11-19 00:00:00.000',
        ]);

        return $id;
    }
}
