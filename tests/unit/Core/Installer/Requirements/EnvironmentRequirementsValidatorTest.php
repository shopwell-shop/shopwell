<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Installer\Requirements;

use Composer\Composer;
use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryManager;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Installer\Requirements\EnvironmentRequirementsValidator;
use Shopwell\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopwell\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopwell\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EnvironmentRequirementsValidator::class)]
class EnvironmentRequirementsValidatorTest extends TestCase
{
    /**
     * @param array<string, string|false> $composerOverrides
     * @param array<string, Link> $requires
     * @param list<SystemCheck> $expectedChecks
     */
    #[DataProvider('composerRequirementsProvider')]
    public function testValidateRequirements(?string $coreComposerName, array $composerOverrides, array $requires, array $expectedChecks): void
    {
        $systemEnvironment = new PlatformRepository([], $composerOverrides);

        $corePackage = new RootPackage($coreComposerName ?? 'shopwell/platform', '1.0.0', '1.0.0');
        $corePackage->setRequires($requires);

        $repoManagerMock = static::createStub(RepositoryManager::class);

        if ($coreComposerName) {
            $repoManagerMock->method('getLocalRepository')->willReturn(
                new InstalledArrayRepository([$corePackage])
            );
        } else {
            $repoManagerMock->method('getLocalRepository')->willReturn(new InstalledArrayRepository());
        }

        $composer = $this->createMock(Composer::class);
        $composer->method('getRepositoryManager')->willReturn($repoManagerMock);

        if ($coreComposerName) {
            $composer->expects($this->never())->method('getPackage');
        } else {
            $composer->expects($this->once())->method('getPackage')->willReturn($corePackage);
        }

        $validator = new EnvironmentRequirementsValidator($composer, $systemEnvironment);

        $checks = new RequirementsCheckCollection();

        static::assertEquals($expectedChecks, $validator->validateRequirements($checks)->getElements());
    }

    public static function composerRequirementsProvider(): \Generator
    {
        $versionParser = new VersionParser();

        yield 'platform repo with satisfied requirement' => [
            'shopwell/platform',
            [
                'php' => '7.4.3',
            ],
            [
                'someRequirement' => new Link(
                    'shopwell/platform',
                    'someRequirement',
                    $versionParser->parseConstraints('>=1.3.0'),
                    Link::TYPE_REQUIRE
                ),
                'php' => new Link(
                    'shopwell/platform',
                    'php',
                    $versionParser->parseConstraints('>=7.4.3'),
                    Link::TYPE_REQUIRE
                ),
            ],
            [
                new SystemCheck(
                    'php',
                    RequirementCheck::STATUS_SUCCESS,
                    '>=7.4.3',
                    '7.4.3'
                ),
            ],
        ];

        yield 'platform repo with not satisfied requirement' => [
            'shopwell/platform',
            [
                'php' => '7.4.2',
            ],
            [
                'someRequirement' => new Link(
                    'shopwell/platform',
                    'someRequirement',
                    $versionParser->parseConstraints('>=1.3.0'),
                    Link::TYPE_REQUIRE
                ),
                'php' => new Link(
                    'shopwell/platform',
                    'php',
                    $versionParser->parseConstraints('>=7.4.3'),
                    Link::TYPE_REQUIRE
                ),
            ],
            [
                new SystemCheck(
                    'php',
                    RequirementCheck::STATUS_ERROR,
                    '>=7.4.3',
                    '7.4.2'
                ),
            ],
        ];

        yield 'platform repo with missing requirement' => [
            'shopwell/platform',
            [
                'composer-runtime-api' => false,
            ],
            [
                'someRequirement' => new Link(
                    'shopwell/platform',
                    'someRequirement',
                    $versionParser->parseConstraints('>=1.3.0'),
                    Link::TYPE_REQUIRE
                ),
                'composer-runtime-api' => new Link(
                    'shopwell/platform',
                    'composer-runtime-api',
                    $versionParser->parseConstraints('^2.0'),
                    Link::TYPE_REQUIRE
                ),
            ],
            [
                new SystemCheck(
                    'composer-runtime-api',
                    RequirementCheck::STATUS_ERROR,
                    '^2.0',
                    '-'
                ),
            ],
        ];

        yield 'core repo with satisfied requirement' => [
            'shopwell/core',
            [
                'php' => '7.4.3',
            ],
            [
                'someRequirement' => new Link(
                    'shopwell/core',
                    'someRequirement',
                    $versionParser->parseConstraints('>=1.3.0'),
                    Link::TYPE_REQUIRE
                ),
                'php' => new Link(
                    'shopwell/core',
                    'php',
                    $versionParser->parseConstraints('>=7.4.3'),
                    Link::TYPE_REQUIRE
                ),
            ],
            [
                new SystemCheck(
                    'php',
                    RequirementCheck::STATUS_SUCCESS,
                    '>=7.4.3',
                    '7.4.3'
                ),
            ],
        ];

        yield 'fallback package with satisfied requirement' => [
            null,
            [
                'php' => '7.4.3',
            ],
            [
                'someRequirement' => new Link(
                    'shopwell/platform',
                    'someRequirement',
                    $versionParser->parseConstraints('>=1.3.0'),
                    Link::TYPE_REQUIRE
                ),
                'php' => new Link(
                    'shopwell/platform',
                    'php',
                    $versionParser->parseConstraints('>=7.4.3'),
                    Link::TYPE_REQUIRE
                ),
            ],
            [
                new SystemCheck(
                    'php',
                    RequirementCheck::STATUS_SUCCESS,
                    '>=7.4.3',
                    '7.4.3'
                ),
            ],
        ];
    }
}
