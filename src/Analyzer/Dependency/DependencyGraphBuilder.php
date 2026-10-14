<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Dependency;

use PhpArchitector\Util\FileSystemUtils;
use PhpSyntax\Nodes\FileNode;

/**
 * Builds a dependency graph from PHP files.
 */
final class DependencyGraphBuilder
{
    private DependencyExtractor $extractor;

    public function __construct(DependencyExtractor $extractor = null)
    {
        $this->extractor = $extractor ?? new DependencyExtractor();
    }

    /**
     * Build a dependency graph from a directory.
     *
     * @param string $directory Path to the directory to analyze
     * @param string|null $baseNamespace Base namespace for the directory (optional)
     * @return array<string, mixed> The dependency graph with 'nodes' and 'edges'
     */
    public function buildFromDirectory(string $directory, ?string $baseNamespace = null): array
    {
        $files = FileSystemUtils::findPhpFiles($directory);
        $graph = ['nodes' => [], 'edges' => []];
        $nodeIndex = [];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            $fileNode = (new \PhpSyntax\Parser())->parse($source);

            $fileInfo = $this->extractor->extractFromFile($fileNode);

            if (empty($fileInfo['classes'])) {
                continue;
            }

            foreach ($fileInfo['classes'] as $classInfo) {
                $fqcn = $classInfo['fqcn'];

                if (!isset($nodeIndex[$fqcn])) {
                    $nodeIndex[$fqcn] = count($graph['nodes']);
                    $graph['nodes'][] = [
                        'id' => $nodeIndex[$fqcn],
                        'fqcn' => $fqcn,
                        'file' => $file,
                        'type' => $classInfo['type'],
                        'namespace' => $classInfo['namespace'],
                        'name' => $classInfo['name'],
                    ];
                }

                foreach ($classInfo['dependencies'] as $dep) {
                    $targetFqcn = $dep['fqcn'];

                    if (!isset($nodeIndex[$targetFqcn])) {
                        $nodeIndex[$targetFqcn] = count($graph['nodes']);
                        $graph['nodes'][] = [
                            'id' => $nodeIndex[$targetFqcn],
                            'fqcn' => $targetFqcn,
                            'type' => 'external',
                            'namespace' => $this->extractNamespaceFromFqcn($targetFqcn),
                            'name' => $this->extractClassNameFromFqcn($targetFqcn),
                        ];
                    }

                    $graph['edges'][] = [
                        'source' => $nodeIndex[$fqcn],
                        'target' => $nodeIndex[$targetFqcn],
                        'type' => $dep['type'],
                    ];
                }
            }
        }

        return $graph;
    }

    /**
     * Build a dependency graph from a single file.
     *
     * @param string $filePath Path to the PHP file
     * @return array<string, mixed> The dependency graph with 'nodes' and 'edges'
     */
    public function buildFromFile(string $filePath): array
    {
        $source = file_get_contents($filePath);
        $fileNode = (new \PhpSyntax\Parser())->parse($source);

        $fileInfo = $this->extractor->extractFromFile($fileNode);

        $graph = ['nodes' => [], 'edges' => []];
        $nodeIndex = [];

        if (empty($fileInfo['classes'])) {
            return $graph;
        }

        foreach ($fileInfo['classes'] as $classInfo) {
            $fqcn = $classInfo['fqcn'];

            if (!isset($nodeIndex[$fqcn])) {
                $nodeIndex[$fqcn] = count($graph['nodes']);
                $graph['nodes'][] = [
                    'id' => $nodeIndex[$fqcn],
                    'fqcn' => $fqcn,
                    'file' => $filePath,
                    'type' => $classInfo['type'],
                    'namespace' => $classInfo['namespace'],
                    'name' => $classInfo['name'],
                ];
            }

            foreach ($classInfo['dependencies'] as $dep) {
                $targetFqcn = $dep['fqcn'];

                if (!isset($nodeIndex[$targetFqcn])) {
                    $nodeIndex[$targetFqcn] = count($graph['nodes']);
                    $graph['nodes'][] = [
                        'id' => $nodeIndex[$targetFqcn],
                        'fqcn' => $targetFqcn,
                        'type' => 'external',
                        'namespace' => $this->extractNamespaceFromFqcn($targetFqcn),
                        'name' => $this->extractClassNameFromFqcn($targetFqcn),
                    ];
                }

                $graph['edges'][] = [
                    'source' => $nodeIndex[$fqcn],
                    'target' => $nodeIndex[$targetFqcn],
                    'type' => $dep['type'],
                ];
            }
        }

        return $graph;
    }

    /**
     * Extract namespace from FQCN.
     *
     * @param string $fqcn The FQCN
     * @return string The namespace
     */
    private function extractNamespaceFromFqcn(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        if ($pos === false) {
            return '';
        }
        return substr($fqcn, 0, $pos);
    }

    /**
     * Extract class name from FQCN.
     *
     * @param string $fqcn The FQCN
     * @return string The class name
     */
    private function extractClassNameFromFqcn(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        if ($pos === false) {
            return $fqcn;
        }
        return substr($fqcn, $pos + 1);
    }
}
