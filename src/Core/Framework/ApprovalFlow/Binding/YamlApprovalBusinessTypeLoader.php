<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
class YamlApprovalBusinessTypeLoader implements ApprovalBusinessTypeLoader
{
    private const FIELDS = ['type', 'version', 'labelSnippet', 'formSchema'];

    /**
     * @param list<ApprovalBusinessTypeSourceDirectory> $directories
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
                    $data = Yaml::parse($this->filesystem->readFile($file->getPathname()), Yaml::PARSE_OBJECT_FOR_MAP);
                } catch (ParseException $exception) {
                    throw ApprovalFlowException::invalidBusinessType($source, 'declaration is not valid YAML', $exception);
                } catch (IOExceptionInterface $exception) {
                    throw ApprovalFlowException::invalidBusinessType($source, 'declaration could not be read', $exception);
                }
                $types[] = $this->loadSpecification($data, $directory->path, $source);
            }
        }

        return $types;
    }

    private function loadSpecification(mixed $data, string $directory, string $source): ApprovalBusinessTypeSpecification
    {
        if (!$data instanceof \stdClass) {
            throw ApprovalFlowException::invalidBusinessType($source, 'declaration must be a map');
        }
        $data = get_object_vars($data);
        if (array_diff(array_keys($data), self::FIELDS) !== []) {
            throw ApprovalFlowException::invalidBusinessType($source, 'Unknown declaration field');
        }
        foreach (['type', 'version', 'labelSnippet', 'formSchema'] as $field) {
            if (!isset($data[$field]) || !\is_string($data[$field])) {
                throw ApprovalFlowException::invalidBusinessType($source, $field . ' must be a string');
            }
        }
        $schemaFile = $data['formSchema'];
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*\.json$/D', $schemaFile)) {
            throw ApprovalFlowException::invalidBusinessType($source, 'formSchema must name a JSON file beside the declaration');
        }
        $schemaPath = $this->filesystem->readlink($directory . '/' . $schemaFile, canonicalize: true);
        if ($schemaPath === null) {
            throw ApprovalFlowException::invalidBusinessType($source, 'formSchema file is missing');
        }
        if (\dirname($schemaPath) !== $this->filesystem->readlink($directory, canonicalize: true)) {
            throw ApprovalFlowException::invalidBusinessType($source, 'formSchema must resolve to a JSON file beside the declaration');
        }
        try {
            $schema = $this->filesystem->readFile($schemaPath);
        } catch (IOExceptionInterface $exception) {
            throw ApprovalFlowException::invalidBusinessType($source, 'formSchema could not be read', $exception);
        }

        return new ApprovalBusinessTypeSpecification(
            $data['type'],
            $data['version'],
            $data['labelSnippet'],
            $source,
            $schema,
        );
    }
}
