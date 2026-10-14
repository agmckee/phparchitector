<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer;

use PhpArchitector\Analyzer\Dependency\DependencyExtractor;
use PhpArchitector\Analyzer\Dependency\DependencyGraphBuilder;
use PhpArchitector\Analyzer\Dependency\CircularDependencyDetector;
use PhpArchitector\Analyzer\Dependency\DependencyResolver;

/**
 * Analyzes dependencies between PHP classes.
 *
 * This class acts as an orchestrator that uses specialized classes:
 * - DependencyExtractor: Extract dependencies from AST nodes
 * - DependencyResolver: Resolve class names to FQCNs
 * - DependencyGraphBuilder: Build dependency graphs from files
 * - CircularDependencyDetector: Detect circular dependencies
 * 
 * Detects various types of dependencies:
 * - Use statement imports
 * - Type hints (parameters, return types, properties)
 * - Class instantiation (new)
 * - Static method calls
 * - Parent class (extends)
 * - Interface implementation (implements)
 * - Trait usage (use)
 */
class DependencyAnalyzer extends AbstractAnalyzer implements DependencyAnalyzerInterface
{
    private DependencyExtractor $extractor;
    private DependencyGraphBuilder $graphBuilder;
    private CircularDependencyDetector $circularDetector;
    private DependencyResolver $resolver;

    public function __construct(
        DependencyExtractor $extractor = null,
        DependencyGraphBuilder $graphBuilder = null,
        CircularDependencyDetector $circularDetector = null,
        DependencyResolver $resolver = null
    ) {
        parent::__construct();
        $this->extractor = $extractor ?? new DependencyExtractor();
        $this->graphBuilder = $graphBuilder ?? new DependencyGraphBuilder($this->extractor);
        $this->circularDetector = $circularDetector ?? new CircularDependencyDetector();
        $this->resolver = $resolver ?? new DependencyResolver();
    }

    /**
     * Analyze PHP source code and return dependency information.
     *
     * @param string $source PHP source code
     * @return array<string, mixed> Dependency information
     */
    public function analyze(string $source): array
    {
        $fileNode = $this->parse($source);
        $fileInfo = $this->extractor->extractFromFile($fileNode);

        if (empty($fileInfo['classes'])) {
            return ['fqcn' => null, 'dependencies' => []];
        }

        // For simplicity, return the first class found
        $classInfo = $fileInfo['classes'][0];

        return [
            'fqcn' => $classInfo['fqcn'],
            'type' => $classInfo['type'],
            'namespace' => $classInfo['namespace'],
            'name' => $classInfo['name'],
            'dependencies' => $classInfo['dependencies'],
        ];
    }

    /**
     * Analyze a single file and return its dependencies.
     *
     * @param string $filePath Path to the PHP file
     * @return array<string, mixed> Dependency information
     */
    public function analyzeFile(string $filePath): array
    {
        $source = file_get_contents($filePath);
        return $this->analyze($source);
    }

    /**
     * Analyze a directory and build a dependency graph.
     *
     * @param string $directory Path to the directory to analyze
     * @return array<string, mixed> The dependency graph with 'nodes' and 'edges'
     */
    public function analyzeDirectory(string $directory): array
    {
        return $this->graphBuilder->buildFromDirectory($directory);
    }

    /**
     * Build a dependency graph for a directory.
     *
     * Alias for analyzeDirectory to satisfy DependencyAnalyzerInterface.
     *
     * @param string $directory Path to the directory to analyze
     * @return array<string, mixed> The dependency graph with 'nodes' and 'edges'
     */
    public function buildGraph(string $directory): array
    {
        return $this->analyzeDirectory($directory);
    }

    /**
     * Get dependencies for a single file.
     *
     * Alias for analyzeFile to satisfy DependencyAnalyzerInterface.
     *
     * @param string $filePath Path to the PHP file
     * @return array<string, mixed> List of dependencies
     */
    public function getDependencies(string $filePath): array
    {
        return $this->analyzeFile($filePath);
    }

    /**
     * Find circular dependencies in a directory or graph.
     *
     * @param string|array<string, mixed> $directoryOrGraph Path to the directory to analyze or a pre-built graph
     * @return array<int, array<int, string>> List of circular dependency cycles
     */
    public function findCircularDependencies(string|array $directoryOrGraph): array
    {
        // If it's a string, it's a directory - build the graph first
        if (is_string($directoryOrGraph)) {
            $graph = $this->analyzeDirectory($directoryOrGraph);
            return $this->circularDetector->findCircularDependencies($graph);
        }

        // Otherwise, it's already a graph array
        return $this->circularDetector->findCircularDependencies($directoryOrGraph);
    }
}
