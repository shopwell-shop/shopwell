<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopwell\Core\Framework\App\Validation\ContentSystemBindingSpecificationAppValidator;
use Shopwell\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopwell\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemBindingSpecificationAppValidator::class)]
class ContentSystemBindingSpecificationAppValidatorTest extends TestCase
{
    #[TestDox('turns a canonicalization failure in a binding load into a schema error instead of throwing')]
    public function testCanonicalizationFailureBecomesSchemaError(): void
    {
        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromTypeDirectory')->willThrowException(
            ContentSystemException::bindingSpecificationCanonicalizationFailed('bad', 'unexpected shape')
        );

        $errors = $this->validator($loader)->validate($this->manifest(), Context::createDefaultContext());

        static::assertCount(1, $errors);
        $error = $errors[0];
        static::assertInstanceOf(ContentSystemBindingSpecificationSchemaError::class, $error);
        static::assertStringContainsString('unexpected shape', $error->getMessage());
    }

    #[TestDox('falls back to an empty type overlay when the app types fail to load, without adding an error or throwing')]
    public function testMalformedAppTypesFallBackToEmptyOverlay(): void
    {
        $typeLoader = static::createStub(YamlTypeLoader::class);
        $typeLoader->method('loadOverlayFromDirectory')->willThrowException(
            ContentSystemException::elementTypeLoadFailed('type.yaml', 'broken')
        );

        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromTypeDirectory')->willReturn([]);

        // Malformed app types are the element-type validator's error to report; this validator must not add one
        // and must not let the type-load failure escape.
        $errors = $this->validator($loader, $typeLoader)->validate($this->manifest(), Context::createDefaultContext());

        static::assertCount(0, $errors);
    }

    private function validator(YamlBindingSpecificationLoader $loader, ?YamlTypeLoader $typeLoader = null): ContentSystemBindingSpecificationAppValidator
    {
        $typeLoader ??= static::createStub(YamlTypeLoader::class);

        return new ContentSystemBindingSpecificationAppValidator($loader, $typeLoader);
    }

    private function manifest(): Manifest
    {
        $metadata = static::createStub(Metadata::class);
        $metadata->method('getName')->willReturn('DemoApp');

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);
        $manifest->method('getPath')->willReturn('/app');

        return $manifest;
    }
}
