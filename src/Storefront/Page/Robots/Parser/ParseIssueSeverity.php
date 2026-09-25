<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Robots\Parser;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore Simple enum with no logic - covered by ParseIssue and ParsedRobots tests
 */
#[Package('discovery')]
enum ParseIssueSeverity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
}
