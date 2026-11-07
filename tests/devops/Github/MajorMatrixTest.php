<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Github;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class MajorMatrixTest extends TestCase
{
    public function testPhpunitMajorMatrixHasNoVersionDimension(): void
    {
        $matrix = $this->generateMatrix('generate-phpunit-matrix.php', '', 'true');

        static::assertArrayNotHasKey('major', $matrix);
        static::assertNotCount(0, $matrix['test']);
        static::assertSame(['8.2'], $matrix['php']);
    }

    public function testAcceptanceMatrixHasOneMajorVariant(): void
    {
        static::assertSame([''], $this->generateMatrix('generate-acceptance-matrix.php', '', 'false')['major']);
        static::assertSame(['', 'major'], $this->generateMatrix('generate-acceptance-matrix.php', '', 'true')['major']);
        static::assertSame([''], $this->generateMatrix('generate-acceptance-matrix.php', 'nightly', 'false', 'exclude')['major']);

        $majorOnly = $this->generateMatrix('generate-acceptance-matrix.php', 'nightly', 'false', 'only');
        static::assertSame(['major'], $majorOnly['major']);
        static::assertCount(3, $majorOnly['include']);

        foreach ($majorOnly['include'] as $variant) {
            static::assertSame('major', $variant['major']);
        }
    }

    public function testAcceptanceProfilesKeepEveryPlaywrightVariant(): void
    {
        foreach (['', 'nightly', 'release'] as $profile) {
            foreach (['false', 'true'] as $major) {
                foreach (['', 'exclude', 'only'] as $filter) {
                    $matrix = $this->generateMatrix('generate-acceptance-matrix.php', $profile, $major, $filter);
                    static::assertArrayNotHasKey('no-currents', $matrix);

                    $actual = [];
                    foreach ($matrix['php-version'] as $php) {
                        foreach ($matrix['major'] as $variant) {
                            foreach ($matrix['shard'] as $shard) {
                                $actual[] = ['Platform', $php, $variant, (int) $shard, 3];
                            }
                        }
                    }
                    foreach ($matrix['include'] as $variant) {
                        static::assertArrayNotHasKey('no-currents', $variant);
                        $actual[] = [$variant['name'], $variant['php-version'], $variant['major'] ?? '', (int) $variant['shard'], $variant['shard-count']];
                    }

                    $versions = $profile === '' ? ['8.2'] : ['8.2', '8.4', '8.5'];
                    $variants = ($major === 'true' || $profile === 'nightly') ? ['', 'major'] : [''];
                    $variants = match ($filter) {
                        'exclude' => [''],
                        'only' => ['major'],
                        default => $variants,
                    };
                    $expected = [];
                    foreach ($versions as $php) {
                        foreach ($variants as $variant) {
                            foreach ([1, 2, 3] as $shard) {
                                $expected[] = ['Platform', $php, $variant, $shard, 3];
                            }
                        }
                    }
                    if ($filter !== 'only') {
                        $expected[] = ['Install', $profile === '' ? '8.2' : '8.4', '', 1, 1];
                    }
                    \sort($expected);
                    \sort($actual);
                    static::assertSame($expected, $actual, \implode('/', [$profile, $major, $filter]));
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generateMatrix(string $script, string ...$arguments): array
    {
        $process = new Process([\PHP_BINARY, '.github/bin/' . $script, ...$arguments], \dirname(__DIR__, 3));
        $process->mustRun();

        $matrix = \json_decode($process->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($matrix);

        return $matrix;
    }
}
