<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Data Transfer Object for property information.
 */
final class PropertyInfo
{
    /**
     * @param string $name Property name
     * @param string|null $type Property type FQCN
     * @param string $visibility One of 'public', 'protected', 'private'
     * @param bool $isStatic Whether the property is static
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $type = null,
        public readonly string $visibility = 'public',
        public readonly bool $isStatic = false
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
            'type' => $this->type,
            'visibility' => $this->visibility,
            'is_static' => $this->isStatic,
        ];
    }
}
