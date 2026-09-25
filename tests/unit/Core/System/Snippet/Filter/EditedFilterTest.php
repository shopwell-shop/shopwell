<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Filter\EditedFilter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(EditedFilter::class)]
class EditedFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('edited', (new EditedFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new EditedFilter())->supports('edited'));
        static::assertFalse((new EditedFilter())->supports(''));
        static::assertFalse((new EditedFilter())->supports('test'));
    }

    public function testFilterOnlyCustomSnippets(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'id' => '1',
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'id' => null,
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'id' => '2',
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'id' => null,
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'id' => '1',
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '2.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '2.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'firstSetId',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'id' => '2',
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '1.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $result = (new EditedFilter())->filter($snippets, true);

        static::assertSame($expected, $result);
    }

    public function testFilterDoesntIncludeAddedSnippets(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'id' => '1',
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'id' => null,
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'id' => '2',
                        'author' => 'user/admin',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'id' => null,
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'id' => '1',
                        'author' => 'shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '1.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $result = (new EditedFilter())->filter($snippets, true);

        static::assertSame($expected, $result);
    }
}
