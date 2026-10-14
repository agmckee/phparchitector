<?php declare(strict_types=1);

namespace PhpArchitector\Model\Graph;

use PhpArchitector\Model\DependencyType;

/**
 * Represents an edge (dependency) in the dependency graph.
 */
final class Edge
{
    /**
     * @param int $sourceId The source node ID
     * @param int $targetId The target node ID
     * @param DependencyType $type The type of dependency
     */
    public function __construct(
        public readonly int $sourceId,
        public readonly int $targetId,
        public readonly DependencyType $type
    ) {}
    
    /**
     * Get a unique identifier for this edge.
     */
    public function getId(): string
    {
        return $this->sourceId . '->' . $this->targetId . ':' . $this->type->value;
    }
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->sourceId,
            'target' => $this->targetId,
            'type' => $this->type->value,
        ];
    }
}
