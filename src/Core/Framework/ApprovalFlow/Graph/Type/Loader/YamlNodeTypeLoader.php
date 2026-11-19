<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
class YamlNodeTypeLoader implements NodeTypeLoader
{
    private const FIELDS = ['type', 'version', 'labelSnippet', 'configurationSchema', 'draftConfigurationSchema', 'ports'];

    /**
     * @param list<NodeTypeSourceDirectory> $directories
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly array $directories,
    ) {
    }

    public function load(): array
    {
        $types = [];
        foreach ($this->directories as $directory) {
            if (!$this->filesystem->exists($directory->path)) {
                continue;
            }
            $files = (new Finder())->files()->in($directory->path)->depth(0)->name(['*.yaml', '*.yml'])->sortByName();
            foreach ($files as $file) {
                $source = $directory->source . ':' . $file->getFilename();
                try {
                    $data = Yaml::parse($this->filesystem->readFile($file->getPathname()));
                } catch (ParseException $exception) {
                    throw ApprovalFlowException::invalidNodeType($source, 'declaration is not valid YAML', $exception);
                }
                $types[] = $this->loadSpecification($data, $directory->path, $source);
            }
        }

        return $types;
    }

    private function loadSpecification(mixed $data, string $directory, string $source): NodeTypeSpecification
    {
        if (!\is_array($data) || array_is_list($data)) {
            throw ApprovalFlowException::invalidNodeType($source, 'declaration must be a map');
        }
        if (array_diff(array_keys($data), self::FIELDS) !== []) {
            throw ApprovalFlowException::invalidNodeType($source, 'Unknown declaration field');
        }
        foreach (['type', 'version', 'labelSnippet', 'configurationSchema'] as $field) {
            if (!isset($data[$field]) || !\is_string($data[$field]) || trim($data[$field]) === '') {
                throw ApprovalFlowException::invalidNodeType($source, $field . ' must be a nonempty string');
            }
        }
        $schemaFile = $data['configurationSchema'];
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*\.json$/D', $schemaFile)) {
            throw ApprovalFlowException::invalidNodeType($source, 'configurationSchema must name a JSON file beside the declaration');
        }
        if (!$this->filesystem->exists($directory . '/' . $schemaFile)) {
            throw ApprovalFlowException::invalidNodeType($source, 'configurationSchema file is missing');
        }
        $ports = $data['ports'] ?? null;
        if (!\is_array($ports) || array_is_list($ports)) {
            throw ApprovalFlowException::invalidNodeType($source, 'ports must be a named map');
        }
        foreach ($ports as $id => $direction) {
            if (!\is_string($id) || $id === '' || !\in_array($direction, ['input', 'output'], true)) {
                throw ApprovalFlowException::invalidNodeType($source, 'ports must map names to input or output');
            }
        }

        $draftSchema = null;
        if (\array_key_exists('draftConfigurationSchema', $data)) {
            $draftFile = $data['draftConfigurationSchema'];
            if (!\is_string($draftFile) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*\.json$/D', $draftFile)) {
                throw ApprovalFlowException::invalidNodeType($source, 'draftConfigurationSchema must name a JSON file beside the declaration');
            }
            if (!$this->filesystem->exists($directory . '/' . $draftFile)) {
                throw ApprovalFlowException::invalidNodeType($source, 'draftConfigurationSchema file is missing');
            }
            $draftSchema = $this->filesystem->readFile($directory . '/' . $draftFile);
        }

        return new NodeTypeSpecification(
            $data['type'],
            $data['version'],
            $data['labelSnippet'],
            $source,
            $this->filesystem->readFile($directory . '/' . $schemaFile),
            $ports,
            $draftSchema,
        );
    }
}
