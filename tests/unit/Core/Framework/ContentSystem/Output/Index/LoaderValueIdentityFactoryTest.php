<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Output\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Shopwell\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Test\Stub\ContentSystem\StubArrayLoaderConfig;
use Shopwell\Core\Test\Stub\ContentSystem\StubLoaderConfigSerializer;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoaderValueIdentityFactory::class)]
class LoaderValueIdentityFactoryTest extends TestCase
{
    private LoaderValueIdentityFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LoaderValueIdentityFactory(
            new DataLoaderConfigSerializerProvider(new ServiceLocator([
                'entity' => static fn () => new StubLoaderConfigSerializer(),
                'listing' => static fn () => new StubLoaderConfigSerializer(),
            ])),
            new ConfigCanonicalizer(),
            new ValueFingerprinter(),
        );
    }

    public function testConfigCanonicalizesNestedMapsAndUnorderedLists(): void
    {
        $first = new DataRequirement('one', 'entity', new StubArrayLoaderConfig(['ids' => ['b', 'a'], 'nested' => ['z' => 2, 'a' => 1]]));
        $second = new DataRequirement('two', 'entity', new StubArrayLoaderConfig(['nested' => ['a' => 1, 'z' => 2], 'ids' => ['a', 'b']]));

        $firstIdentity = $this->factory->create($first, new LoaderInputs([]), null);
        $secondIdentity = $this->factory->create($second, new LoaderInputs([]), null);

        static::assertSame(Hasher::hash(['ids' => ['a', 'b'], 'nested' => ['a' => 1, 'z' => 2]]), $firstIdentity->configHash);
        static::assertSame($firstIdentity->configHash, $secondIdentity->configHash);
    }

    public function testInputMapsAreSortedAtEveryDepthWithoutSortingLists(): void
    {
        $requirement = new DataRequirement('product', 'entity', new StubArrayLoaderConfig());
        $first = new LoaderInputs(['z' => [['b' => 2, 'a' => 1]], 'ids' => ['second', 'first']]);
        $second = new LoaderInputs(['ids' => ['second', 'first'], 'z' => [['a' => 1, 'b' => 2]]]);

        $firstIdentity = $this->factory->create($requirement, $first, null);
        $secondIdentity = $this->factory->create($requirement, $second, null);

        static::assertSame(Hasher::hash(['ids' => ['second', 'first'], 'z' => [['a' => 1, 'b' => 2]]]), $firstIdentity->inputsHash);
        static::assertSame($firstIdentity->inputsHash, $secondIdentity->inputsHash);
    }

    public function testOrderedInputListsRemainDistinct(): void
    {
        $requirement = new DataRequirement('product', 'entity', new StubArrayLoaderConfig());
        $first = $this->factory->create($requirement, new LoaderInputs(['ids' => ['a', 'b']]), null);
        $second = $this->factory->create($requirement, new LoaderInputs(['ids' => ['b', 'a']]), null);

        static::assertNotSame($first->inputsHash, $second->inputsHash);
        static::assertSame($first->configHash, $second->configHash);
    }

    public function testSourceAndProducedObjectIdentityArePreserved(): void
    {
        $value = new \stdClass();
        $replacement = new \stdClass();
        $config = new StubArrayLoaderConfig();
        $first = $this->factory->create(new DataRequirement('first', 'entity', $config), new LoaderInputs([]), $value);
        $second = $this->factory->create(new DataRequirement('second', 'listing', $config), new LoaderInputs([]), $replacement);

        static::assertSame('entity', $first->source);
        static::assertSame('listing', $second->source);
        static::assertSame((string) spl_object_id($value), $first->producedFingerprint);
        static::assertNotSame($first->producedFingerprint, $second->producedFingerprint);
        static::assertSame($first->configHash, $second->configHash);
    }

    public function testUnregisteredConfigSerializerCannotCreateAnIdentity(): void
    {
        $this->expectExceptionObject(ContentSystemException::configSerializerNotRegistered('unknown'));

        $this->factory->create(new DataRequirement('product', 'unknown', new StubArrayLoaderConfig()), new LoaderInputs([]), null);
    }
}
