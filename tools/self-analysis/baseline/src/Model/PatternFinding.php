<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Data Transfer Object for a pattern finding.
 */
final class PatternFinding
{
    /**
     * @param PatternType $type The pattern type
     * @param PatternSeverity $severity The severity level
     * @param string $description Human-readable description
     * @param string $fqcn The class FQCN where the pattern was found
     * @param string $filePath The file path
     * @param array<string, mixed> $details Additional details about the finding
     */
    public function __construct(
        public readonly PatternType $type,
        public readonly PatternSeverity $severity,
        public readonly string $description,
        public readonly string $fqcn,
        public readonly string $filePath,
        public readonly array $details = []
    ) {}
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'description' => $this->description,
            'fqcn' => $this->fqcn,
            'file_path' => $this->filePath,
            'details' => $this->details,
        ];
    }
}
