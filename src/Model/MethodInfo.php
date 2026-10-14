<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Data Transfer Object for method information.
 */
final class MethodInfo
{
    /**
     * @param string $name Method name
     * @param string $visibility One of 'public', 'protected', 'private'
     * @param string|null $returnType Return type FQCN
     * @param array<int, array<string, mixed>> $parameters List of parameters
     * @param bool $isStatic Whether the method is static
     * @param bool $isAbstract Whether the method is abstract
     * @param bool $isFinal Whether the method is final
     */
    public function __construct(
        public readonly string $name,
        public readonly string $visibility = 'public',
        public readonly ?string $returnType = null,
        public readonly array $parameters = [],
        public readonly bool $isStatic = false,
        public readonly bool $isAbstract = false,
        public readonly bool $isFinal = false
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
            'return_type' => $this->returnType,
            'parameters' => $this->parameters,
            'is_static' => $this->isStatic,
            'is_abstract' => $this->isAbstract,
            'is_final' => $this->isFinal,
        ];
    }
}
