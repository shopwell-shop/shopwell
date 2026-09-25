<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Update\Services\UpdateHtaccess;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateHtaccess::class)]
class UpdateHtaccessTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                'Shopwell\Core\Framework\Update\Event\UpdatePostFinishEvent' => 'update',
            ],
            UpdateHtaccess::getSubscribedEvents()
        );
    }

    #[DataProvider('getCombinations')]
    public function testCombination(string $currentEnv, ?string $newEnv, string $expected): void
    {
        $fs = sys_get_temp_dir() . '/' . uniqid(__METHOD__, true) . '/';
        mkdir($fs);

        file_put_contents($fs . '.env', $currentEnv);

        if ($newEnv) {
            file_put_contents($fs . '.env.dist', $newEnv);
        }

        $updater = new UpdateHtaccess($fs . '.env');
        $updater->update();

        static::assertSame($expected, file_get_contents($fs . '.env'));
    }

    /**
     * @return iterable<array-key, array{string, ?string, string}>
     */
    public static function getCombinations(): iterable
    {
        // Dist file missing
        yield [
            'Test',
            null,
            'Test',
        ];

        // User has removed marker
        yield [
            'Test',
            '# BEGIN Shopwell
Test
# END Shopwell',
            'Test',
        ];

        // Update marker
        yield [
            '# BEGIN Shopwell
OLD
# END Shopwell',
            '# BEGIN Shopwell
NEW
# END Shopwell',
            '# BEGIN Shopwell
# The directives (lines) between "# BEGIN Shopwell" and "# END Shopwell" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopwell',
        ];

        // Update marker with pre and after lines
        yield [
            'BEFORE
# BEGIN Shopwell
OLD
# END Shopwell
AFTER',
            '# BEGIN Shopwell
NEW
# END Shopwell',
            'BEFORE
# BEGIN Shopwell
# The directives (lines) between "# BEGIN Shopwell" and "# END Shopwell" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopwell
AFTER',
        ];

        // Update containg help text
        yield [
            'BEFORE
# BEGIN Shopwell
# The directives (lines) between "# BEGIN Shopwell" and "# END Shopwell" are dynamically generated. Any changes to the directives between these markers will be overwritten.
OLD
# END Shopwell
AFTER',
            '# BEGIN Shopwell
# The directives (lines) between "# BEGIN Shopwell" and "# END Shopwell" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopwell',
            'BEFORE
# BEGIN Shopwell
# The directives (lines) between "# BEGIN Shopwell" and "# END Shopwell" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopwell
AFTER',
        ];
    }

    public function testReplaceComplete(): void
    {
        $fs = sys_get_temp_dir() . '/' . uniqid(__METHOD__, true) . '/';
        mkdir($fs);

        copy(__DIR__ . '/../_fixtures/htaccess', $fs . '.htaccess');
        $newHtaccess = '# BEGIN Shopwell
# The directives (lines) between "# BEGIN Shopwell" and "# END Shopwell" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopwell';
        file_put_contents($fs . '.htaccess.dist', $newHtaccess);

        $updater = new UpdateHtaccess($fs . '.htaccess');
        $updater->update();

        static::assertSame($newHtaccess, file_get_contents($fs . '.htaccess'));
    }
}
