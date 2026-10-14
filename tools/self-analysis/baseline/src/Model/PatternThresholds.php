<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Thresholds for a specific pattern type.
 */
final class PatternThresholds
{
    /**
     * @param int $methodCount Minimum number of methods to trigger
     * @param int $propertyCount Minimum number of properties to trigger
     * @param int $lineCount Minimum number of lines to trigger
     * @param PatternSeverity $severity The severity level for this pattern
     */
    public function __construct(
        public readonly int $methodCount = 20,
        public readonly int $propertyCount = 15,
        public readonly int $lineCount = 500,
        public readonly PatternSeverity $severity = PatternSeverity::Medium
    ) {}
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method_count' => $this->methodCount,
            'property_count' => $this->propertyCount,
            'line_count' => $this->lineCount,
            'severity' => $this->severity->value,
        ];
    }
}
