<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Robots;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Storefront\Page\Robots\Struct\DomainRuleCollection;
use Shopwell\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;

/**
 * @codeCoverageIgnore Simple DTO with no business logic, tested indirectly through RobotsPageLoaderTest
 */
#[Package('discovery')]
class RobotsPage extends Struct
{
    protected DomainRuleCollection $domainRules;

    /**
     * @var list<string>
     */
    protected array $sitemaps;

    /**
     * @var list<RobotsUserAgentBlock>
     */
    protected array $globalUserAgentBlocks = [];

    public function getDomainRules(): DomainRuleCollection
    {
        return $this->domainRules;
    }

    public function setDomainRules(DomainRuleCollection $domainRules): void
    {
        $this->domainRules = $domainRules;
    }

    /**
     * @return list<string>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param list<string> $sitemaps
     */
    public function setSitemaps(array $sitemaps): void
    {
        $this->sitemaps = $sitemaps;
    }

    /**
     * @return list<RobotsUserAgentBlock>
     */
    public function getGlobalUserAgentBlocks(): array
    {
        return $this->globalUserAgentBlocks;
    }

    /**
     * @param list<RobotsUserAgentBlock> $blocks
     */
    public function setGlobalUserAgentBlocks(array $blocks): void
    {
        $this->globalUserAgentBlocks = $blocks;
    }
}
