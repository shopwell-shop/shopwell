<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Preset;

use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopwell\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopwell\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopwell\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopwell\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopwell\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopwell\Core\Framework\ContentSystem\Mutation\Op\AttachElements;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemLayoutProvisioner
{
    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly AbstractContentSystemLayoutPresetRegistry $presetRegistry,
        private readonly DraftLayoutDecoder $decoder,
        private readonly MutationPipeline $pipeline,
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly BindingApplicator $bindingApplicator,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly StoredElementCodec $codec,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public function provisionFromPreset(
        string $name,
        string $presetId,
        string $rootSource,
        Context $context,
        ?string $layoutId = null,
        ?string $entityId = null,
        string $version = '1.0.0',
    ): string {
        $definition = $entityId === null ? null : $this->assignmentDefinition($rootSource);
        $preset = $this->presetRegistry->get($presetId);

        $mutation = new AttachElements($this->typeRegistry, $this->decoder->decode($preset->payload), $this->bindingRegistry, $this->bindingApplicator, isNewScaffold: true);
        $result = $this->pipeline->run($mutation, new StoredTree([], $rootSource), $this->rootSourceRegistry->resolveGated($rootSource, $context));

        $layout = [
            'id' => $layoutId ?? Uuid::randomHex(),
            'name' => $name,
            'version' => $version,
            'rootSource' => $rootSource,
            'layout' => array_map($this->codec->encode(...), $result->layout->roots),
        ];

        $this->contentLayoutRepository->upsert([$layout], $context);

        if ($entityId !== null && $definition !== null) {
            $this->definitionRegistry->getRepository($definition->getEntityName())->create([[
                'id' => Uuid::randomHex(),
                $definition->getContentLayoutEntityIdField() => $entityId,
                'salesChannelId' => null,
                'contentLayoutId' => $layout['id'],
            ]], $context);
        }

        return $layout['id'];
    }

    private function assignmentDefinition(string $rootSource): AbstractContentLayoutAssignableDefinition
    {
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if ($definition instanceof AbstractContentLayoutAssignableDefinition && $definition->getContentLayoutEntityType() === $rootSource) {
                return $definition;
            }
        }

        throw ContentSystemException::unknownEntityType($rootSource);
    }
}
