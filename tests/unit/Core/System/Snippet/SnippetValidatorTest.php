<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Files\SnippetFileCollection;
use Shopwell\Core\System\Snippet\SnippetFileHandler;
use Shopwell\Core\System\Snippet\SnippetValidator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetValidator::class)]
class SnippetValidatorTest extends TestCase
{
    public function testValidateShouldFindMissingSnippets(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $firstPath = 'storefront.zh.json';
        $secondPath = 'storefront.en.json';
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$firstPath]);
        $snippetFileHandler->method('findStorefrontSnippetFiles')
            ->willReturn([$secondPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static function ($path) use ($firstPath) {
                if ($path === $firstPath) {
                    return ['chinese' => 'exampleChinese'];
                }

                return ['english' => 'exampleEnglish'];
            });

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $invalidData = $snippetValidator->getValidation();
        $missingSnippets = $invalidData->missingSnippets->getElements();
        static::assertCount(2, $missingSnippets);

        $missingSnippetEnGB = $missingSnippets[1];
        static::assertSame('chinese', $missingSnippetEnGB->getKeyPath());
        static::assertSame('exampleChinese', $missingSnippetEnGB->getAvailableTranslation());

        $missingSnippetZhCn = $missingSnippets[0];
        static::assertSame('english', $missingSnippetZhCn->getKeyPath());
        static::assertSame('exampleEnglish', $missingSnippetZhCn->getAvailableTranslation());

        $invalidPluralization = $invalidData->invalidPluralization;
        static::assertCount(0, $invalidPluralization);
    }

    public function testValidateShouldNotFindAnyMissingSnippets(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $firstPath = 'storefront.zh.json';
        $secondPath = 'storefront.en.json';
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$firstPath]);
        $snippetFileHandler->method('findStorefrontSnippetFiles')
            ->willReturn([$secondPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn () => ['foo' => 'bar']);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $invalidData = $snippetValidator->getValidation();

        static::assertCount(0, $invalidData->missingSnippets);
    }

    public function testValidateShouldFindInvalidPluralization(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $path = 'storefront.en.json';
        $snippetFileHandler->method('findStorefrontSnippetFiles')
            ->willReturn([$path]);

        $expectedInvalidSnippets = [
            'noIndexes' => 'Singular | Plural',
            'noFallbackRange' => '{1}Singular | Plural',
            'noOneIndex' => '{0} Singular | [0,Inf[ Plural',
            'wrongPluralRangeSnippetFixable' => '{1} Singular |]1,Inf[ Plural',
            'wrongPluralRangeSnippetDupeFixable' => '{1} Singular DUPE |]1,Inf[ Plural DUPE',
        ];

        $actualSnippets = [
            'noPluralization' => 'Something',
            'somethingValid' => '{1} Singular |[0,Inf[ Plural',
            'somethingValidWith0' => '{0} Zero case | {1} Singular |[0,Inf[ Plural',
            ...$expectedInvalidSnippets,
        ];

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn () => $actualSnippets);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $invalidData = $snippetValidator->getValidation();
        $invalidPluralization = $invalidData->invalidPluralization;

        static::assertCount(5, $invalidPluralization);
        static::assertFalse($invalidPluralization->has('somethingValid'));
        static::assertFalse($invalidPluralization->has('somethingValidWith0'));

        foreach ($expectedInvalidSnippets as $expectedKey => $expectedValue) {
            static::assertTrue($invalidPluralization->has($expectedKey), "Missing expected key: $expectedKey");

            $invalidSnippet = $invalidPluralization->get($expectedKey);
            static::assertSame($expectedValue, $invalidSnippet->snippetValue, "Invalid pluralization for key: $expectedKey");
            static::assertSame($path, $invalidSnippet->path, "Invalid path for key: $expectedKey");
            static::assertSame(\str_contains($expectedKey, 'Fixable'), $invalidSnippet->isFixable);
        }
    }
}
