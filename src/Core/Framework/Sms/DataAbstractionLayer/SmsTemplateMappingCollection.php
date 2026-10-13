<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SmsTemplateMappingEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsTemplateMappingCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sms_template_mapping_collection';
    }

    protected function getExpectedClass(): string
    {
        return SmsTemplateMappingEntity::class;
    }
}
