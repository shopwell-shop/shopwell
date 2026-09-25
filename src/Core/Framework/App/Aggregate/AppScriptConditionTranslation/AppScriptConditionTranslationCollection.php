<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppScriptConditionTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppScriptConditionTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppScriptConditionTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_script_condition_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppScriptConditionTranslationEntity::class;
    }
}
