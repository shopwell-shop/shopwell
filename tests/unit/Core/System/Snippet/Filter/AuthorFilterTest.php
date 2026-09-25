<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Filter\AuthorFilter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AuthorFilter::class)]
class AuthorFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('author', (new AuthorFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new AuthorFilter())->supports('author'));
        static::assertFalse((new AuthorFilter())->supports(''));
        static::assertFalse((new AuthorFilter())->supports('test'));
    }

    public function testFilter(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'author' => 'Anonymous',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
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
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
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
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
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

        $result = (new AuthorFilter())->filter($snippets, ['Shopwell']);

        static::assertSame($expected, $result);
    }

    public function testFilterDoesntRemoveSnippetInOtherSet(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '1_bar',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.baz' => [
                        'value' => '1_baz',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.bas' => [
                        'value' => '1_bas',
                        'author' => 'Anonymous',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '2_bar',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '1_bar',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.baz' => [
                        'value' => '1_baz',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '2_bar',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $result = (new AuthorFilter())->filter($snippets, ['Shopwell']);

        static::assertSame($expected, $result);
    }

    public function testFilterWithMultipleAuthors(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '1_bar',
                        'author' => 'Test',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.baz' => [
                        'value' => '1_baz',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.bas' => [
                        'value' => '1_bas',
                        'author' => 'Anonymous',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '2_bar',
                        'author' => 'Test',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '1_bar',
                        'author' => 'Test',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.baz' => [
                        'value' => '1_baz',
                        'author' => 'Shopwell',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '2_bar',
                        'author' => 'Test',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    'foo.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $result = (new AuthorFilter())->filter($snippets, ['Shopwell', 'Test']);

        static::assertSame($expected, $result);
    }
}
