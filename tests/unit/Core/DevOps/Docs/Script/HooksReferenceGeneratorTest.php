<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\Docs\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\Docs\DocsException;
use Shopwell\Core\DevOps\Docs\Script\HooksReferenceGenerator;
use Shopwell\Core\DevOps\Docs\Script\ScriptReferenceDataCollector;
use Shopwell\Core\DevOps\Docs\Script\ServiceReferenceGenerator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HooksReferenceGenerator::class)]
class HooksReferenceGeneratorTest extends TestCase
{
    private Environment&Stub $twig;

    private ContainerInterface&Stub $container;

    private ServiceReferenceGenerator&Stub $serviceReferenceGenerator;

    private HooksReferenceGenerator $generator;

    public static function setUpBeforeClass(): void
    {
        foreach ([
            'SimpleService',
            'SimpleHookServiceFactory',
            'HookServiceFactoryWithNoReturnType',
            'SimpleHook',
            'StoppableHookFixture',
            'DeprecatedHookFixture',
            'HookWithNoDocComment',
            'HookWithNoUseCase',
            'HookWithEmptyUseCase',
            'HookWithInvalidUseCase',
            'HookWithNoSince',
            'HookWithUntypedProperty',
            'HookWithVarAnnotatedProperty',
            'HookWithNoReturnTypeFactory',
            'HookWithService',
            'SimpleResponseFunctionHook',
            'SimpleInterfaceHook',
            'OptionalFunctionHookFixture',
            'InterfaceHookWithOptionalFunction',
        ] as $fixture) {
            require_once __DIR__ . '/_fixtures/hooks/' . $fixture . '.php';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->twig = static::createStub(Environment::class);
        $this->twig->method('render')->willReturn('');
        $this->twig->method('getLoader')->willReturn(static::createStub(LoaderInterface::class));

        $this->container = static::createStub(ContainerInterface::class);
        $this->serviceReferenceGenerator = static::createStub(ServiceReferenceGenerator::class);

        $this->generator = new HooksReferenceGenerator(
            $this->container,
            $this->twig,
            $this->serviceReferenceGenerator
        );
    }

    protected function tearDown(): void
    {
        ScriptReferenceDataCollector::reset();
        parent::tearDown();
    }

    public function testGenerateReturnsExpectedArray(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\SimpleHook::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
        static::assertNotCount(0, $result);
        static::assertArrayHasKey(array_key_first($result), $result);
    }

    public function testGetHookClassesThrowsWhenNoHookClassesFound(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([\Countable::class]);

        $this->expectExceptionObject(DocsException::noHookClassesFound());
        $this->generator->generate();
    }

    public function testGetHookClassesSkipsNonHookClass(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([
            \Countable::class,
            _fixtures\hooks\SimpleHook::class,
        ]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
        static::assertNotCount(0, $result);
    }

    public function testGetHookClassesSkipsFunctionHooks(): void
    {
        // SimpleResponseFunctionHook is a FunctionHook — should be skipped, SimpleHook included
        ScriptReferenceDataCollector::setShopwellClasses([
            _fixtures\hooks\SimpleResponseFunctionHook::class,
            _fixtures\hooks\SimpleHook::class,
        ]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
        static::assertNotCount(0, $result);
    }

    public function testGenerateHandlesMissingDocComment(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\HookWithNoDocComment::class]);

        $this->expectExceptionObject(DocsException::missingPhpDocCommentInHookClass(_fixtures\hooks\HookWithNoDocComment::class));
        $this->generator->generate();
    }

    /**
     * @param class-string<Hook> $hookClass
     */
    #[DataProvider('provideHooksWithMissingUseCase')]
    public function testGenerateThrowsOnMissingOrInvalidUseCase(string $hookClass): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([$hookClass]);

        $this->expectExceptionObject(DocsException::missingUseCaseDescriptionInHookClass($hookClass, HooksReferenceGenerator::ALLOWED_USE_CASES));
        $this->generator->generate();
    }

    /**
     * @return \Generator<string, array{class-string<Hook>}>
     */
    public static function provideHooksWithMissingUseCase(): \Generator
    {
        yield 'no use-case tag' => [_fixtures\hooks\HookWithNoUseCase::class];
        yield 'empty use-case description' => [_fixtures\hooks\HookWithEmptyUseCase::class];
        yield 'invalid use-case value' => [_fixtures\hooks\HookWithInvalidUseCase::class];
    }

    public function testGenerateThrowsOnMissingSinceAnnotation(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\HookWithNoSince::class]);

        $this->expectExceptionObject(DocsException::missingSinceAnnotationInHookClass(_fixtures\hooks\HookWithNoSince::class));
        $this->generator->generate();
    }

    public function testGenerateHandlesStoppableHook(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\StoppableHookFixture::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
    }

    public function testGenerateHandlesDeprecatedHook(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\DeprecatedHookFixture::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
    }

    public function testGenerateThrowsOnUntypedPropertyWithNoVarDoc(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\HookWithUntypedProperty::class]);

        $this->expectExceptionObject(DocsException::untypedPropertyInHookClass('untypedProp', _fixtures\hooks\HookWithUntypedProperty::class));
        $this->generator->generate();
    }

    public function testGenerateThrowsOnPropertyWithDocBlockButNoVarTag(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\HookWithDocBlockButNoVarTag::class]);

        $this->expectExceptionObject(DocsException::untypedPropertyInHookClass('noVarTagProp', _fixtures\hooks\HookWithDocBlockButNoVarTag::class));
        $this->generator->generate();
    }

    public function testGenerateHandlesVarAnnotatedProperty(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\HookWithVarAnnotatedProperty::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
    }

    public function testGenerateHandlesInterfaceHook(): void
    {
        // SimpleInterfaceHook has FUNCTIONS containing SimpleResponseFunctionHook which has FUNCTION_NAME
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\SimpleInterfaceHook::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
    }

    public function testGenerateHandlesInterfaceHookWithOptionalFunction(): void
    {
        // InterfaceHookWithOptionalFunction -> OptionalFunctionHookFixture::willBeRequiredInVersion returns '6.6.0.0'
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\InterfaceHookWithOptionalFunction::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
    }

    public function testBuildAvailableServicesThrowsOnMissingReturnType(): void
    {
        // HookWithNoReturnTypeFactory uses HookServiceFactoryWithNoReturnType whose factory() has no return type
        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\HookWithNoReturnTypeFactory::class]);

        $this->expectExceptionObject(DocsException::missingReturnTypeOnFactoryMethodInHookServiceFactory(
            _fixtures\hooks\HookServiceFactoryWithNoReturnType::class
        ));
        $this->generator->generate();
    }

    public function testBuildAvailableServicesWithValidFactory(): void
    {
        // HookWithService uses SimpleHookServiceFactory
        $factory = new _fixtures\hooks\SimpleHookServiceFactory();
        $this->container->method('get')->willReturn($factory);
        $this->serviceReferenceGenerator->method('getGroupForService')->willReturn('data_loading');

        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\HookWithService::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
    }

    public function testBuildAvailableServicesSkipsNonHookServiceFactory(): void
    {
        // Container returns a non-HookServiceFactory object
        $this->container->method('get')->willReturn(new \stdClass());

        ScriptReferenceDataCollector::setShopwellClasses([_fixtures\hooks\HookWithService::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
    }
}
