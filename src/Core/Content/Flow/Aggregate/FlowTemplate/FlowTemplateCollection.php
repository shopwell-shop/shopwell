<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Aggregate\FlowTemplate;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<FlowTemplateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class FlowTemplateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'flow_template_collection';
    }

    protected function getExpectedClass(): string
    {
        return FlowTemplateEntity::class;
    }
}
