<?php declare(strict_types=1);

namespace PhpArchitector\Model\Graph;

/**
 * Represents a dependency graph.
 */
final class DependencyGraph
{
    /**
     * @param array<int, Node> $nodes Map of node ID to Node
     * @param array<int, Edge> $edges List of edges
     */
    public function __construct(
        private array $nodes = [],
        private array $edges = []
    ) {}
    
    /**
     * Add a node to the graph.
     */
    public function addNode(Node $node): void
    {
        $this->nodes[$node->getId()] = $node;
    }
    
    /**
     * Add an edge to the graph.
     */
    public function addEdge(Edge $edge): void
    {
        $this->edges[] = $edge;
    }
    
    /**
     * Check if a node exists in the graph.
     */
    public function hasNode(string $fqcn): bool
    {
        return isset($this->nodes[$fqcn]);
    }
    
    /**
     * Get a node by FQCN.
     */
    public function getNode(string $fqcn): ?Node
    {
        return $this->nodes[$fqcn] ?? null;
    }
    
    /**
     * Get all nodes in the graph.
     *
     * @return array<int, Node>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
    
    /**
     * Get all edges in the graph.
     *
     * @return array<int, Edge>
     */
    public function getEdges(): array
    {
        return $this->edges;
    }
    
    /**
     * Get all dependencies (outgoing edges) for a node.
     *
     * @param string $fqcn The node FQCN
     * @return array<int, Edge> List of edges originating from this node
     */
    public function getDependencies(string $fqcn): array
    {
        $node = $this->getNode($fqcn);
        if ($node === null) {
            return [];
        }
        
        $dependencies = [];
        $nodeId = $node->getId();
        
        foreach ($this->edges as $edge) {
            // Convert FQCN to node ID for comparison
            $sourceNode = $this->getNode($edge->sourceId);
            if ($sourceNode !== null && $sourceNode->getId() === $nodeId) {
                $dependencies[] = $edge;
            }
        }
        
        return $dependencies;
    }
    
    /**
     * Get all dependents (incoming edges) for a node.
     *
     * @param string $fqcn The node FQCN
     * @return array<int, Edge> List of edges pointing to this node
     */
    public function getDependents(string $fqcn): array
    {
        $node = $this->getNode($fqcn);
        if ($node === null) {
            return [];
        }
        
        $dependents = [];
        $nodeId = $node->getId();
        
        foreach ($this->edges as $edge) {
            // Convert FQCN to node ID for comparison
            $targetNode = $this->getNode($edge->targetId);
            if ($targetNode !== null && $targetNode->getId() === $nodeId) {
                $dependents[] = $edge;
            }
        }
        
        return $dependents;
    }
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodes' => array_map(fn($n) => $n->toArray(), $this->nodes),
            'edges' => array_map(fn($e) => $e->toArray(), $this->edges),
        ];
    }
}
