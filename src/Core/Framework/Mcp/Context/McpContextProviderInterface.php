<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Context;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Common interface for MCP context providers across different API scopes.
 *
 * Implementations resolve a Shopwell Context from the current request so that
 * MCP tools can perform DAL operations without knowing whether they are running
 * in an Admin API or Store API context.
 *
 * - Admin API: context is resolved via OAuth bearer token / integration credentials.
 * - Store API: context is resolved via sw-access-key + sw-context-token headers.
 *
 * UCP and other cross-context tools should type-hint against this interface rather
 * than a concrete provider so they can be reused across both API scopes.
 */
#[Package('framework')]
interface McpContextProviderInterface
{
    public function getContext(): Context;
}
