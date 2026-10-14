<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Data Transfer Object for class constant information.
 */
final class ConstantInfo
{
    /**
     * @param string $name Constant name
     * @param string $visibility One of 'public', 'protected', 'private'
     */
    public function __construct(
        public readonly string $name,
        public readonly string $visibility = 'public'
    ) {}
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'visibility' => $this->visibility,
        ];
    }
}
