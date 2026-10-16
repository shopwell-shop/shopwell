<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterPickerNotFoundException;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterSorterNotFoundException;
use Shopwell\Core\Checkout\Promotion\Exception\DiscountCalculatorNotFoundException;
use Shopwell\Core\Checkout\Promotion\Exception\InvalidScopeDefinitionException;
use Shopwell\Core\Checkout\Promotion\Exception\PriceNotFoundException;
use Shopwell\Core\Content\Cms\Exception\PageNotFoundException;
use Shopwell\Core\Content\Flow\Exception\CustomTriggerByNameNotFoundException;
use Shopwell\Core\Content\ImportExport\Exception\LogNotWritableException;
use Shopwell\Core\Content\ImportExport\Exception\MappingException;
use Shopwell\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopwell\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopwell\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorNotFoundException;
use Shopwell\Core\Framework\Api\ApiDefinition\ApiTypeNotFoundException;
use Shopwell\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopwell\Core\Framework\Api\Controller\Exception\AuthThrottledException;
use Shopwell\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InvalidPriceFieldTypeException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Feature\FeatureException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\KernelPluginLoaderException;
use Shopwell\Core\Framework\Plugin\Exception\PluginComposerRemoveException;
use Shopwell\Core\Framework\Plugin\Exception\PluginComposerRequireException;
use Shopwell\Core\Framework\Plugin\Exception\PluginNotAZipFileException;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopwell\Core\Framework\ShopwellHttpException;
use Shopwell\Core\Framework\Store\Exception\LicenseDomainVerificationException;
use Shopwell\Core\Framework\Util\Exception\ComparatorException;
use Shopwell\Core\System\Currency\CurrencyDefinition;
use Shopwell\Storefront\Framework\Media\Exception\MediaValidatorMissingException;
use Shopwell\Storefront\Theme\Exception\InvalidThemeBundleException;
use Shopwell\Storefront\Theme\Exception\ThemeAssignmentException;

/**
 * Exercises the removal contract across legacy exception classes, independently of domain callers.
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedExceptionCompatibilityTest extends TestCase
{
    /**
     * @param class-string<ShopwellHttpException> $class
     * @param list<mixed> $arguments
     */
    #[DataProvider('exceptionProvider')]
    public function testLegacyConstructionAndMetadataRemainUsable(string $class, array $arguments): void
    {
        $exception = Feature::fake([], static fn () => new $class(...$arguments));

        // Error formatting must remain safe even if a legacy object survives a flag-state change.
        Feature::fake(['v6.8.0.0'], static function () use ($exception): void {
            static::assertNotSame('', $exception->getErrorCode());
            static::assertGreaterThanOrEqual(400, $exception->getStatusCode());
            static::assertNotSame([], iterator_to_array($exception->getErrors()));
            if ($exception instanceof UnsupportedValueException) {
                static::assertSame('type', $exception->getType());
                static::assertSame('class', $exception->getClass());
            }
        });
    }

    public function testExistingExceptionMetadataRemainsCallableInMajorMode(): void
    {
        $exceptions = Feature::fake([], static fn () => [
            new LogNotWritableException(['id']),
            new MappingException(),
            new SalesChannelNotFoundException('id'),
            new AuthThrottledException(10),
            new ThemeAssignmentException('theme', [], [], []),
        ]);

        Feature::fake(['v6.8.0.0'], static function () use ($exceptions): void {
            foreach ($exceptions as $exception) {
                static::assertNotSame('', $exception->getErrorCode());
                static::assertGreaterThanOrEqual(400, $exception->getStatusCode());
                static::assertNotSame([], iterator_to_array($exception->getErrors()));
            }

            static::assertSame(10, $exceptions[3]->getWaitTime());
            static::assertSame([], $exceptions[4]->getAssignedSalesChannels());
        });
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testComparatorFactoryThrowsInMajorMode(): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(ComparatorException::class, ComparatorException::class . '::operatorNotSupported', 'v6.8.0.0')));

        Feature::fake(['v6.8.0.0'], static fn () => ComparatorException::operatorNotSupported('invalid'));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     *
     * @param class-string<ShopwellHttpException> $class
     * @param list<mixed> $arguments
     */
    #[DataProvider('exceptionProvider')]
    public function testConstructionThrowsInMajorMode(string $class, array $arguments): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage($class, 'v6.8.0.0')));

        Feature::fake(['v6.8.0.0'], static fn () => new $class(...$arguments));
    }

    /**
     * @return iterable<string, array{class-string<ShopwellHttpException>, list<mixed>}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'missing media validator' => [MediaValidatorMissingException::class, ['image']];
        yield 'invalid theme bundle' => [InvalidThemeBundleException::class, ['theme']];
        yield 'license verification' => [LicenseDomainVerificationException::class, ['domain']];
        yield 'invalid sync operation' => [InvalidSyncOperationException::class, ['invalid']];
        yield 'missing API type' => [ApiTypeNotFoundException::class, ['type']];
        yield 'missing API generator' => [ApiDefinitionGeneratorNotFoundException::class, ['format']];
        yield 'invalid context source' => [InvalidContextSourceUserException::class, ['source']];
        yield 'plugin require failure' => [PluginComposerRequireException::class, ['plugin', 'package', 'output']];
        yield 'plugin removal failure' => [PluginComposerRemoveException::class, ['plugin', 'package', 'output']];
        yield 'plugin loader failure' => [KernelPluginLoaderException::class, ['plugin', 'reason']];
        yield 'plugin is not a ZIP' => [PluginNotAZipFileException::class, ['mime']];
        yield 'unsupported rule value' => [UnsupportedValueException::class, ['type', 'class']];
        yield 'missing association' => [AssociationNotFoundException::class, ['association']];
        yield 'invalid price type' => [InvalidPriceFieldTypeException::class, ['type']];
        yield 'invalid sorting direction' => [InvalidSortingDirectionException::class, ['direction']];
        yield 'unmapped field' => [UnmappedFieldException::class, ['field', new CurrencyDefinition()]];
        yield 'comparator direct construction' => [ComparatorException::class, [400, 'code', 'message']];
        yield 'missing payment method' => [PaymentMethodNotAvailableException::class, ['id']];
        yield 'missing discount calculator' => [DiscountCalculatorNotFoundException::class, ['type']];
        yield 'missing price' => [PriceNotFoundException::class, [new LineItem('id', 'custom')]];
        yield 'invalid discount scope' => [InvalidScopeDefinitionException::class, ['scope']];
        yield 'missing filter picker' => [FilterPickerNotFoundException::class, ['key']];
        yield 'missing filter sorter' => [FilterSorterNotFoundException::class, ['key']];
        yield 'missing flow trigger' => [CustomTriggerByNameNotFoundException::class, ['event']];
        yield 'missing CMS page' => [PageNotFoundException::class, ['id']];
        yield 'inactive review' => [ReviewNotActiveExeption::class, []];
    }
}
