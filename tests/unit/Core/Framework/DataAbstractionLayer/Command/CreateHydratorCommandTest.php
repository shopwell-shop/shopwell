<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateHydratorCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateHydratorCommand::class)]
class CreateHydratorCommandTest extends TestCase
{
    private string $rootDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/' . uniqid('create-hydrator-command-test', true);
        $this->filesystem = new Filesystem();

        // the command reads the definition source files to add getHydratorClass() when missing
        $fixtureDir = $this->rootDir . '/src/Tests/Unit/Core/Framework/DataAbstractionLayer/Command';
        $this->filesystem->dumpFile($fixtureDir . '/HydratorCommandTestDefinition.php', '<?php // contains getHydratorClass already');
        $this->filesystem->dumpFile($fixtureDir . '/AaaHydratorCommandTestDefinition.php', '<?php // contains getHydratorClass already');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->rootDir);
    }

    public function testExecuteGeneratesPhpServiceDefinitionFile(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [HydratorCommandTestDefinition::class, AaaHydratorCommandTestDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $command = new CreateHydratorCommand($registry, $this->filesystem, $this->rootDir);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['whitelist' => ['hydrator_command_test', 'aaa_hydrator_command_test']]);

        $commandTester->assertCommandIsSuccessful();

        static::assertFileExists($this->rootDir . '/src/Tests/Unit/Core/Framework/DataAbstractionLayer/Command/HydratorCommandTestHydrator.php');
        static::assertFileDoesNotExist($this->rootDir . '/src/Core/Framework/DependencyInjection/hydrator.xml');

        $expected = <<<'EOF'
<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Command\AaaHydratorCommandTestHydrator;
use Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Command\HydratorCommandTestHydrator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(HydratorCommandTestHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(AaaHydratorCommandTestHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);
};

EOF;

        static::assertStringEqualsFile($this->rootDir . '/src/Core/Framework/DependencyInjection/hydrator.php', $expected);
    }
}

/**
 * @internal
 */
class AaaHydratorCommandTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'aaa_hydrator_command_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}

/**
 * @internal
 */
class HydratorCommandTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'hydrator_command_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name'),
        ]);
    }
}
