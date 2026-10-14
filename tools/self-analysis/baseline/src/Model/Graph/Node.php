<?php declare(strict_types=1);

namespace PhpArchitector\Model\Graph;

/**
 * Represents a node in the dependency graph.
 */
final class Node
{
    /**
     * @param string $fqcn The fully qualified class name
     * @param string $filePath The file path
     * @param string $type The node type (class, interface, trait, external)
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $filePath,
        public readonly string $type = 'class'
    ) {}
    
    /**
     * Get a unique identifier for this node.
     */
    public function getId(): string
    {
        return $this->fqcn;
    }
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fqcn' => $this->fqcn,
            'file' => $this->filePath,
            'type' => $this->type,
        ];
    }
}
