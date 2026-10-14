<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Data Transfer Object for class information.
 */
final class ClassInfo
{
    /**
     * @param string|null $namespace The namespace (e.g., 'App\Controller')
     * @param string $name The class name (e.g., 'UserController')
     * @param string|null $fqcn The fully qualified class name
     * @param string|null $extends The parent class FQCN
     * @param array<int, string> $implements List of interface FQCNs
     * @param array<int, MethodInfo> $methods List of methods
     * @param array<int, PropertyInfo> $properties List of properties
     * @param array<int, ConstantInfo> $constants List of constants
     * @param array<int, string> $dependencies List of dependency FQCNs
     * @param int $linesOfCode Number of lines in the class
     * @param string $filePath The file path
     */
    public function __construct(
        public readonly ?string $namespace,
        public readonly string $name,
        public readonly ?string $fqcn,
        public readonly ?string $extends,
        public readonly array $implements = [],
        public readonly array $methods = [],
        public readonly array $properties = [],
        public readonly array $constants = [],
        public readonly array $dependencies = [],
        public readonly int $linesOfCode = 0,
        public readonly string $filePath = ''
    ) {}
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'namespace' => $this->namespace,
            'class_name' => $this->name,
            'fqcn' => $this->fqcn,
            'extends' => $this->extends,
            'implements' => $this->implements,
            'methods' => array_map(fn($m) => $m->toArray(), $this->methods),
            'properties' => array_map(fn($p) => $p->toArray(), $this->properties),
            'constants' => array_map(fn($c) => $c->toArray(), $this->constants),
            'dependencies' => $this->dependencies,
            'lines_of_code' => $this->linesOfCode,
            'file_path' => $this->filePath,
        ];
    }
}
