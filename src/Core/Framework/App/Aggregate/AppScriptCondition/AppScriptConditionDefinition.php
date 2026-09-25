<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppScriptCondition;

use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationDefinition;
use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class AppScriptConditionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_script_condition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppScriptConditionCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppScriptConditionEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.10.3';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of app\'s script condition.'),
            (new StringField('identifier', 'identifier'))->addFlags(new Required())->setDescription('It is a unique identity of an AppScriptCondition.'),
            new TranslatedField('name'),
            (new BoolField('active', 'active'))->addFlags(new Required())->setDescription('When boolean value is `true`, defined app script conditions are available for selection.'),
            (new StringField('group', 'group'))->setDescription('Categorizes script conditions within a specific group.'),
            (new LongTextField('script', 'script'))->addFlags(new AllowHtml(false))->setDescription('Internal field.'),
            (new BlobField('constraints', 'constraints'))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('config', 'config'))->setDescription('Specifies detailed information about the component.'),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new CascadeDelete(), new Required())->setDescription('Unique identity of app.'),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
            (new OneToManyAssociationField('ruleConditions', RuleConditionDefinition::class, 'script_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new TranslationsAssociationField(AppScriptConditionTranslationDefinition::class, 'app_script_condition_id'))->addFlags(new Required()),
        ]);
    }
}
