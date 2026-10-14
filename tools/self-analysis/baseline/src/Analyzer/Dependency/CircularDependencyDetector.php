<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Dependency;

/**
 * Detects circular dependencies in a dependency graph.
 */
final class CircularDependencyDetector
{
    /**
     * Find circular dependencies in a graph.
     *
     * @param array<string, mixed> $graph The dependency graph with 'nodes' and 'edges'
     * @return array<int, array<int, string>> List of circular dependency cycles
     */
    public function findCircularDependencies(array $graph): array
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        // Build adjacency list
        $adjacency = [];
        foreach ($nodes as $node) {
            $adjacency[$node['id']] = [];
        }

        foreach ($edges as $edge) {
            $adjacency[$edge['source']][] = $edge['target'];
        }

        // Find cycles using DFS
        $visited = array_fill(0, count($nodes), false);
        $recursionStack = array_fill(0, count($nodes), false);
        $cyclePaths = [];

        for ($i = 0; $i < count($nodes); $i++) {
            if (!$visited[$i]) {
                $this->findCyclesDfs(
                    $i,
                    $adjacency,
                    $visited,
                    $recursionStack,
                    $i,
                    [$i],
                    $cyclePaths
                );
            }
        }

        // Convert to FQCN-based results and deduplicate
        $circular = [];
        $seenCycles = [];

        foreach ($cyclePaths as $path) {
            $cycle = [];
            foreach ($path as $nodeId) {
                $cycle[] = $nodes[$nodeId]['fqcn'];
            }

            // Remove duplicate starting node at the end
            // Cycle is stored as [C, A, B, C], we want [C, A, B]
            if (!empty($cycle) && $cycle[0] === $cycle[count($cycle) - 1]) {
                array_pop($cycle);
            }

            // Normalize cycle: rotate to start with smallest FQCN
            $normalized = $this->normalizeCycle($cycle);

            // Deduplicate
            $key = implode('|', $normalized);
            if (!isset($seenCycles[$key])) {
                $seenCycles[$key] = true;
                $circular[] = $cycle;
            }
        }

        return $circular;
    }

    /**
     * Normalize a cycle to start with the smallest FQCN for deduplication.
     *
     * @param array<string> $cycle The cycle to normalize
     * @return array<string> Normalized cycle
     */
    private function normalizeCycle(array $cycle): array
    {
        if (empty($cycle)) {
            return $cycle;
        }

        // Find the smallest FQCN
        $min = min($cycle);
        $minIndex = array_search($min, $cycle, true);

        // Rotate so min is first
        $normalized = array_merge(
            array_slice($cycle, $minIndex),
            array_slice($cycle, 0, $minIndex)
        );

        return $normalized;
    }

    /**
     * DFS helper to find cycles.
     *
     * @param int $node Current node
     * @param array<int, array<int>> $adjacency Adjacency list
     * @param array<int, bool> $visited Visited nodes
     * @param array<int, bool> $recursionStack Recursion stack
     * @param int $start Start node
     * @param array<int> $path Current path
     * @param array<array<int>> $cyclePaths Found cycle paths
     */
    private function findCyclesDfs(
        int $node,
        array $adjacency,
        array &$visited,
        array &$recursionStack,
        int $start,
        array $path,
        array &$cyclePaths
    ): void {
        $visited[$node] = true;
        $recursionStack[$node] = true;

        foreach ($adjacency[$node] as $neighbor) {
            if (!$visited[$neighbor]) {
                $this->findCyclesDfs(
                    $neighbor,
                    $adjacency,
                    $visited,
                    $recursionStack,
                    $start,
                    array_merge($path, [$neighbor]),
                    $cyclePaths
                );
            } elseif ($recursionStack[$neighbor] && $neighbor === $start) {
                $cyclePaths[] = array_merge($path, [$neighbor]);
            }
        }

        $recursionStack[$node] = false;
    }
}
