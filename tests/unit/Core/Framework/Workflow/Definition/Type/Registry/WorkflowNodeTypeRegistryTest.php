<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Definition\Type\Registry;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\WorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowNodeTypeRegistry::class)]
class WorkflowNodeTypeRegistryTest extends TestCase
{
    public function testDiscoversVersionedTypesFromIndependentProviders(): void
    {
        $original = new WorkflowNodeTypeSpecification('example:task', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        $upgraded = new WorkflowNodeTypeSpecification('example:task', 2, WorkflowNodeKind::ACTIVITY, ['in'], ['done']);
        $first = static::createStub(WorkflowNodeTypeLoader::class);
        $first->method('load')->willReturn([$original]);
        $second = static::createStub(WorkflowNodeTypeLoader::class);
        $second->method('load')->willReturn([$upgraded]);

        $registry = new WorkflowNodeTypeRegistry([$first, $second]);

        static::assertSame([$original, $upgraded], $registry->all());
        static::assertSame($original, $registry->find('example:task', 1));
        static::assertSame($upgraded, $registry->find('example:task', 2));
        static::assertNull($registry->find('example:task', 3));
        static::assertNull($registry->find('missing:task', 1));
    }

    public function testDuplicateTypeVersionCannotOverrideAnotherProvider(): void
    {
        $type = new WorkflowNodeTypeSpecification('example:task', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        $provider = static::createStub(WorkflowNodeTypeLoader::class);
        $provider->method('load')->willReturn([$type, $type]);

        $this->expectExceptionObject(WorkflowException::duplicateNodeType('example:task', 1));
        new WorkflowNodeTypeRegistry([$provider]);
    }

    public function testEmptyRegistryIsValid(): void
    {
        static::assertSame([], (new WorkflowNodeTypeRegistry([]))->all());
    }

    public function testContainerDiscoversAnAdditionalProviderWithoutRegistryChanges(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(\dirname(__DIR__, 8) . '/src/Core/Framework/DependencyInjection/workflow.php');
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register(ExtensionDispatcher::class)
            ->setArguments([new Reference('event_dispatcher')]);
        $container->register(Connection::class)->setSynthetic(true);
        $container->register('workflow_draft.repository', EntityRepository::class)->setSynthetic(true);
        $container->register('workflow_release.repository', EntityRepository::class)->setSynthetic(true);
        $container->register('acl_role.repository', EntityRepository::class)->setSynthetic(true);
        $container->register('admin_menu_item.repository', EntityRepository::class)->setSynthetic(true);
        $container->register(CoreMenuSynchronizer::class)->setSynthetic(true);
        $container->register(Filesystem::class);
        $container->register(ClockInterface::class)->setSynthetic(true);
        $container->register(StateMachineRegistry::class)->setSynthetic(true);
        foreach (['workflow_execution_definition', 'workflow_instance', 'workflow_task', 'workflow_task_history', 'workflow_log', 'workflow_copy', 'workflow_node_execution', 'user', 'user_organization_unit'] as $entity) {
            $container->register($entity . '.repository', EntityRepository::class)->setSynthetic(true);
        }
        $container->register('validator', ValidatorInterface::class)->setFactory([Validation::class, 'createValidator']);
        $container->register(ExampleWorkflowNodeTypeLoader::class)->addTag('workflow.node_type_loader');
        $container->getDefinition(WorkflowNodeTypeRegistry::class)->setPublic(true);
        $container->compile();

        $registry = $container->get(WorkflowNodeTypeRegistry::class);

        static::assertInstanceOf(WorkflowNodeTypeRegistry::class, $registry);
        static::assertNotNull($registry->find('core:start', 1));
        static::assertNotNull($registry->find('example:task', 1));
    }
}

/**
 * @internal
 */
class ExampleWorkflowNodeTypeLoader implements WorkflowNodeTypeLoader
{
    public function load(): iterable
    {
        yield new WorkflowNodeTypeSpecification('example:task', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
    }
}
