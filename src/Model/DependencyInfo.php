<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Data Transfer Object for dependency information.
 */
final class DependencyInfo
{
    /**
     * @param string $sourceFqcn The source class FQCN
     * @param string $targetFqcn The target class FQCN
     * @param DependencyType $type The type of dependency
     * @param string|null $context Additional context (e.g., method name, property name)
     */
    public function __construct(
        public readonly string $sourceFqcn,
        public readonly string $targetFqcn,
        public readonly DependencyType $type,
        public readonly ?string $context = null
    ) {}
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_fqcn' => $this->sourceFqcn,
            'target_fqcn' => $this->targetFqcn,
            'type' => $this->type->value,
            'context' => $this->context,
        ];
    }
}
