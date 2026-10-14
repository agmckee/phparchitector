<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Configuration for layer violation detection.
 */
final class LayerConfig
{
    /**
     * @param array<string, array<int, string>> $layerHierarchy Map of layer name to allowed dependency layer names
     * @param PatternSeverity $severity The severity level for layer violations
     */
    public function __construct(
        public readonly array $layerHierarchy = [],
        public readonly PatternSeverity $severity = PatternSeverity::High
    ) {}
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'layer_hierarchy' => $this->layerHierarchy,
            'severity' => $this->severity->value,
        ];
    }
}
