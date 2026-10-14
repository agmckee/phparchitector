<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Pattern;

/**
 * Detects layer violations in dependency graphs.
 *
 * Responsibilities:
 * - Detect layer violations based on namespace conventions
 * - Identify backwards dependencies (higher layers depending on lower layers)
 * - Identify same-layer dependencies (sibling dependencies)
 *
 * Assumes layered architecture: Controllers -> Services -> Repositories
 */
final class LayerViolationDetector
{
    /**
     * Detect layer violations in the dependency graph
     *
     * @param array<string, mixed> $graph The dependency graph with nodes and edges
     * @return array<int, array<string, string>> List of layer violations
     */
    public function detectLayerViolations(array $graph): array
    {
        $violations = [];
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        // Define layer order (higher index = deeper layer)
        $layerHierarchy = [
            'Controllers' => 0,
            'Services' => 1,
            'Repositories' => 2,
            'Models' => 3,
            'Entities' => 3,
        ];

        // Extract layer for each node
        $nodeLayers = [];
        foreach ($nodes as $node) {
            $layer = $this->extractLayer($node['namespace'] ?? '', $node['fqcn'] ?? '', $layerHierarchy);

            if ($layer !== null) {
                $nodeLayers[$node['id']] = [
                    'fqcn' => $node['fqcn'],
                    'layer' => $layer,
                    'layer_index' => $layerHierarchy[$layer],
                ];
            }
        }

        // Check each edge for violations
        foreach ($edges as $edge) {
            $sourceId = $edge['source'];
            $targetId = $edge['target'];

            if (!isset($nodeLayers[$sourceId]) || !isset($nodeLayers[$targetId])) {
                continue;
            }

            $sourceLayer = $nodeLayers[$sourceId];
            $targetLayer = $nodeLayers[$targetId];

            // A violation occurs when a higher layer depends on a lower layer
            // (e.g., Repositories depending on Controllers)
            // OR when same layer dependencies exist (sibling dependencies)
            if ($targetLayer['layer_index'] < $sourceLayer['layer_index']) {
                // This is backwards dependency
                $violations[] = [
                    'source' => $sourceLayer['fqcn'],
                    'source_layer' => $sourceLayer['layer'],
                    'target' => $targetLayer['fqcn'],
                    'target_layer' => $targetLayer['layer'],
                    'type' => 'backwards_dependency',
                ];
            } elseif ($targetLayer['layer_index'] === $sourceLayer['layer_index']) {
                // Same layer dependency
                $violations[] = [
                    'source' => $sourceLayer['fqcn'],
                    'source_layer' => $sourceLayer['layer'],
                    'target' => $targetLayer['fqcn'],
                    'target_layer' => $targetLayer['layer'],
                    'type' => 'same_layer_dependency',
                ];
            }
        }

        return $violations;
    }

    /**
     * Extract the layer from a namespace or FQCN
     *
     * @param string $namespace The namespace
     * @param string $fqcn The fully qualified class name
     * @param array<string, int> $layerHierarchy Layer hierarchy mapping
     * @return string|null The layer name or null if not recognized
     */
    private function extractLayer(string $namespace, string $fqcn, array $layerHierarchy): ?string
    {
        // Extract the last part of the namespace
        $namespaceParts = explode('\\', $namespace);
        $lastPart = end($namespaceParts);

        // Check direct match in hierarchy
        if (isset($layerHierarchy[$lastPart])) {
            return $lastPart;
        }

        // Check for layer keywords in namespace parts
        foreach ($namespaceParts as $part) {
            if (isset($layerHierarchy[$part])) {
                return $part;
            }
        }

        // Check for layer keywords in FQCN
        if (str_contains($namespace, 'Controller') || str_contains($fqcn, 'Controller')) {
            return 'Controllers';
        }
        if (str_contains($namespace, 'Service') || str_contains($fqcn, 'Service')) {
            return 'Services';
        }
        if (str_contains($namespace, 'Repository') || str_contains($fqcn, 'Repository')) {
            return 'Repositories';
        }

        return null;
    }
}
