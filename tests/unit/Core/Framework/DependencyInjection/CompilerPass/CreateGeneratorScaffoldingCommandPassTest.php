<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\CreateGeneratorScaffoldingCommandPass;
use Shopwell\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateGeneratorScaffoldingCommandPass::class)]
class CreateGeneratorScaffoldingCommandPassTest extends TestCase
{
    public function testItAddsMakerCommandForTaggedScaffoldingService(): void
    {
        $builder = new ContainerBuilder();
        $builder->setDefinition(
            DemoScaffoldingGenerator::class,
            (new Definition(DemoScaffoldingGenerator::class))
                ->addTag('shopwell.scaffold.generator')
        );

        $pass = new CreateGeneratorScaffoldingCommandPass();
        $pass->process($builder);

        static::assertTrue($builder->hasDefinition('make.auto_command.demo_scaffolding_generator'));
    }

    public function testItDoesNotAddMakerCommandForDeprecatedScaffoldingService(): void
    {
        $builder = new ContainerBuilder();
        $builder->setDefinition(
            DemoScaffoldingGenerator::class,
            (new Definition(DemoScaffoldingGenerator::class))
                ->addTag('shopwell.scaffold.generator')
                ->setDeprecated('test', '1.0.0', '"%service_id%')
        );

        $pass = new CreateGeneratorScaffoldingCommandPass();
        $pass->process($builder);

        static::assertFalse($builder->hasDefinition('make.auto_command.demo_scaffolding_generator'));
    }

    public function testItThrowsWhenTaggedScaffoldingGeneratorDoesNotImplementScaffoldGenerator(): void
    {
        $builder = new ContainerBuilder();
        $builder->setDefinition(
            ProductDefinition::class,
            (new Definition(ProductDefinition::class))
                ->addTag('shopwell.scaffold.generator')
        );

        $pass = new CreateGeneratorScaffoldingCommandPass();

        static::expectExceptionObject(
            DependencyInjectionException::taggedServiceHasWrongType(ProductDefinition::class, 'shopwell.scaffold.generator', ScaffoldingGenerator::class)
        );
        $pass->process($builder);
    }
}

/**
 * @internal
 */
class DemoScaffoldingGenerator implements ScaffoldingGenerator
{
    public function hasCommandOption(): bool
    {
        return true;
    }

    public function getCommandOptionName(): string
    {
        return '';
    }

    public function getCommandOptionDescription(): string
    {
        return '';
    }

    public function addScaffoldConfig(PluginScaffoldConfiguration $config, InputInterface $input, SymfonyStyle $io): void
    {
    }

    public function generateStubs(PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void
    {
    }
}
