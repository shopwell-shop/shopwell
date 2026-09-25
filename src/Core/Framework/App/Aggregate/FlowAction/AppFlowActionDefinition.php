<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\FlowAction;

use Shopwell\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Shopwell\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationDefinition;
use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class AppFlowActionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_flow_action';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppFlowActionCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppFlowActionEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.10.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of app\'s flow action.'),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required())->setDescription('Unique identity of app.'),
            (new StringField('name', 'name', 255))->addFlags(new Required())->setDescription('Name of app flow action.'),
            new StringField('badge', 'badge', 255),
            (new JsonField('parameters', 'parameters'))->setDescription('Parameters that hold data required for the specific action to be executed within flow.'),
            (new JsonField('config', 'config'))->setDescription('Specifies detailed information about the component.'),
            (new JsonField('headers', 'headers'))->setDescription('Indicates the header value within the context of app flow action.'),
            new ListField('requirements', 'requirements', StringField::class),
            new BlobField('icon', 'iconRaw'),
            (new StringField('icon', 'icon'))->addFlags(new WriteProtected(), new Runtime())->setDescription('Icon to identify app flow action.'),
            new StringField('sw_icon', 'swIcon'),
            (new StringField('url', 'url'))->addFlags(new Required())->setDescription('An URL to app flow action.'),
            new BoolField('delayable', 'delayable'),
            new TranslatedField('label'),
            new TranslatedField('description'),
            new TranslatedField('headline'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(AppFlowActionTranslationDefinition::class, 'app_flow_action_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class, 'id', false),
            (new OneToManyAssociationField('flowSequences', FlowSequenceDefinition::class, 'app_flow_action_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
