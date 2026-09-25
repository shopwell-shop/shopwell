<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Struct\TranslationFile;
use Shopwell\Core\System\Snippet\Struct\TranslationFileCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationFileCollection::class)]
class TranslationFileCollectionTest extends TestCase
{
    public function testAddKeysTheFileByItsFullPath(): void
    {
        $file = new TranslationFile('messages.en-GB.json', '/snippets', 'messages', 'en-GB', 'en');
        $collection = new TranslationFileCollection();

        $collection->add($file);

        static::assertSame($file, $collection->get('/snippets/messages.en-GB.json'));
    }
}
