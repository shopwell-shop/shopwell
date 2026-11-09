<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\Hydration\DataLoader;

use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\DictionaryTypeDefinition;
use Shopwell\Core\System\Dictionary\SalesChannel\AbstractDictionaryRoute;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<DictionaryEntryCollection>
 */
#[Package('discovery')]
class FormGendersDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'form_genders';

    public function __construct(private readonly AbstractDictionaryRoute $dictionaryRoute)
    {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        $dictionaryRequest = new Request(attributes: ['technicalName' => DictionaryTypeDefinition::TYPE_GENDER]);
        $entries = $this->dictionaryRoute->load($dictionaryRequest, $context, new Criteria())->getEntries();

        return ContentDataLoaderResult::cachedExternally($entries);
    }
}
