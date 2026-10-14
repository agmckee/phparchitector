<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer;

/**
 * Interface for dependency analyzers.
 * 
 * Provides a consistent API for analyzing dependencies between PHP classes.
 */
interface DependencyAnalyzerInterface
{
    /**
     * Build a dependency graph from a directory.
     *
     * @param string $directory The directory to analyze
     * @return array<string, mixed> The dependency graph (nodes and edges)
     */
    public function buildGraph(string $directory): array;
    
    /**
     * Get dependencies for a specific file.
     *
     * @param string $filePath The file path
     * @return array<int, array<string, mixed>> List of dependencies
     */
    public function getDependencies(string $filePath): array;
    
    /**
     * Find circular dependencies in a directory or graph.
     *
     * @param string|array<string, mixed> $directoryOrGraph Path to the directory to analyze or a pre-built graph
     * @return array<int, array<int, string>> List of circular dependency cycles
     */
    public function findCircularDependencies(string|array $directoryOrGraph): array;
}
