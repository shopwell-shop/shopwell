<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalBusinessTypeRegistry::class)]
class ApprovalBusinessTypeRegistryWiringTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->register('filesystem', Filesystem::class);
        (new PhpFileLoader($this->container, new FileLocator(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection')))->load('approval-flow.php');
    }

    public function testBusinessDeclarationsUseOnlyTheSupportedLoaderContractAndTag(): void
    {
        $this->container->register('expense.approval.business_types.v1', ExpenseApprovalBusinessTypeLoader::class)
            ->setArgument(0, '1.0.0')->addTag('approval_flow.business_type_loader');
        $this->container->register('expense.approval.business_types.v2', ExpenseApprovalBusinessTypeLoader::class)
            ->setArgument(0, '2.0.0')->addTag('approval_flow.business_type_loader');
        $this->container->getDefinition(ApprovalBusinessTypeRegistry::class)->setPublic(true);
        $this->container->compile();

        $registry = $this->container->get(ApprovalBusinessTypeRegistry::class);
        static::assertInstanceOf(ApprovalBusinessTypeRegistry::class, $registry);
        static::assertSame(['expense:claim@1.0.0', 'expense:claim@2.0.0'], array_keys($registry->all()));
        static::assertSame('urn:expense:claim', $registry->get('expense:claim', '1.0.0')->formSchemaId);
        static::assertSame('2.0.0', $registry->get('expense:claim', '2.0.0')->version);
    }

    public function testFrameworkAssemblyDoesNotInstallAFictitiousBusinessRequest(): void
    {
        $this->container->getDefinition(ApprovalBusinessTypeRegistry::class)->setPublic(true);
        $this->container->compile();

        $registry = $this->container->get(ApprovalBusinessTypeRegistry::class);
        static::assertInstanceOf(ApprovalBusinessTypeRegistry::class, $registry);
        static::assertSame([], $registry->all());
    }
}

/**
 * A business module contributes declarations without importing internal file adapters.
 *
 * @internal
 */
class ExpenseApprovalBusinessTypeLoader implements ApprovalBusinessTypeLoader
{
    public function __construct(private readonly string $version)
    {
    }

    public function load(): array
    {
        return [new ApprovalBusinessTypeSpecification(
            'expense:claim',
            $this->version,
            'expense.approval.claim',
            'expense:declarations',
            '{"$id":"urn:expense:claim","type":"object","properties":{}}',
        )];
    }
}
