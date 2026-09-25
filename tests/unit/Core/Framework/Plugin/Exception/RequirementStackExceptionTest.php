<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Plugin\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Requirement\Exception\MissingRequirementException;
use Shopwell\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopwell\Core\Framework\Plugin\Requirement\Exception\VersionMismatchException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RequirementStackException::class)]
class RequirementStackExceptionTest extends TestCase
{
    public function testDoesNotConvertInnerExceptions(): void
    {
        $requirement = 'testRequirement';
        $version = 'v1.0';
        $actualVersion = 'v2.0';
        $action = 'install';

        $missingRequirementException = new MissingRequirementException($requirement, $version);
        $versionMismatchException = new VersionMismatchException($requirement, $version, $actualVersion);

        $requirementStackException = new RequirementStackException(
            $action,
            $missingRequirementException,
            $versionMismatchException
        );

        $converted = [];
        foreach ($requirementStackException->getErrors() as $exception) {
            $converted[] = $exception;
        }

        $convertedVersionMismatch = iterator_to_array($versionMismatchException->getErrors())[0];

        static::assertCount(2, $converted);

        static::assertSame('424', $converted[0]['status']);
        static::assertSame('FRAMEWORK__PLUGIN_REQUIREMENT_MISSING', $converted[0]['code']);

        static::assertSame($convertedVersionMismatch, $converted[1]);
    }
}
