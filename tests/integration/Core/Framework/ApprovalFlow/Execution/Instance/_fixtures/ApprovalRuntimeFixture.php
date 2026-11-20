<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Assembles actual approval services against the test kernel's DAL and transaction.
 *
 * @internal
 */
final class ApprovalRuntimeFixture
{
    /**
     * @param (\Closure(ContainerBuilder): void)|null $configure
     */
    public static function container(ContainerInterface $kernel, string $manifest, ?\Closure $configure = null): ContainerBuilder
    {
        $runtime = PublicationFixture::container($manifest);
        $services = [
            'event_dispatcher' => $kernel->get('event_dispatcher'),
            ApprovalDraftStore::class => $kernel->get(ApprovalDraftStore::class),
            ApprovalRequestStore::class => $kernel->get(ApprovalRequestStore::class),
            ApprovalBusinessTypeRegistry::class => $kernel->get(ApprovalBusinessTypeRegistry::class),
            ApprovalBindingWriteGuard::class => $kernel->get(ApprovalBindingWriteGuard::class),
            Connection::class => $kernel->get(Connection::class),
            ClockInterface::class => $kernel->get(ClockInterface::class),
            ApprovalReleaseStore::class => $kernel->get(ApprovalReleaseStore::class),
            ApprovalInstanceWriteGuard::class => $kernel->get(ApprovalInstanceWriteGuard::class),
        ];
        $definitions = $kernel->get(DefinitionInstanceRegistry::class);
        foreach (['approval_flow_binding', 'approval_flow_binding_head', 'approval_flow_instance', 'approval_flow_operation', 'approval_flow_execution', 'approval_flow_task', 'approval_flow_result'] as $entityName) {
            $services[$entityName . '.repository'] = $definitions->getRepository($entityName);
        }
        foreach ($services as $id => $service) {
            $runtime->register($id)->setSynthetic(true)->setPublic(true);
        }
        $runtime->getDefinition(ApprovalDraftService::class)->setPublic(true);
        $runtime->getDefinition(ApprovalBindingService::class)->setPublic(true);
        $runtime->getDefinition(ApprovalRequestService::class)->setPublic(true);
        $runtime->getDefinition(ApprovalReleaseLoader::class)->setPublic(true);
        $runtime->getDefinition(ApprovalInstanceService::class)->setPublic(true);
        $runtime->getDefinition(ApprovalReleaseService::class)->setPublic(true);
        if ($configure !== null) {
            $configure($runtime);
        }
        $runtime->compile();
        foreach ($services as $id => $service) {
            $runtime->set($id, $service);
        }

        return $runtime;
    }

    public static function publish(ContainerInterface $kernel, ContainerBuilder $runtime, string $graph): string
    {
        $context = Context::createDefaultContext();
        $draft = $kernel->get(ApprovalDraftService::class)->create('Approval runtime fixture', $graph, $context);
        $compiled = $runtime->get(ApprovalReleaseCompiler::class)->compile($draft->graph);

        return $runtime->get(ApprovalReleaseService::class)->publish($draft->definitionId, $draft->revision, $compiled->hash, $context)->id;
    }
}
