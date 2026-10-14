<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Dependency;

use PhpArchitector\Util\FqcnUtils;

/**
 * Resolves class names to fully qualified class names (FQCNs).
 *
 * Handles:
 * - Use statement resolution
 * - Current namespace resolution
 * - Primitive type filtering
 * - Special keyword filtering (self, parent, static, $this)
 */
final class DependencyResolver
{
    /**
     * List of primitive PHP types that should not be resolved as dependencies.
     */
    private const PRIMITIVE_TYPES = [
        'string', 'int', 'integer', 'float', 'double', 'bool', 'boolean',
        'array', 'object', 'mixed', 'void', 'null', 'false', 'true',
        'resource', 'callable', 'iterable', 'countable',
        'self', 'parent', 'static', '$this',
    ];

    /**
     * Resolve a class name to its fully qualified class name.
     *
     * @param string $name The class name to resolve
     * @param array<string, string> $useStatements Map of short names to FQCNs
     * @param string $currentNamespace The current namespace
     * @return string|null The resolved FQCN, or null if not a valid dependency
     */
    public function resolve(string $name, array $useStatements, string $currentNamespace): ?string
    {
        // Skip primitive types
        if ($this->isPrimitiveType($name)) {
            return null;
        }

        // Skip special names
        if (in_array($name, ['self', 'parent', 'static', '$this'], true)) {
            return null;
        }

        // If already FQCN (starts with backslash)
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        // Check use statements first
        $shortName = $this->extractClassName($name);

        // Direct match
        if (isset($useStatements[$name])) {
            return ltrim($useStatements[$name], '\\');
        }

        // Match by short name
        foreach ($useStatements as $alias => $fqcn) {
            if ($this->extractClassName($fqcn) === $shortName) {
                return ltrim($fqcn, '\\');
            }
        }

        // If name contains backslash, it's partially qualified
        if (str_contains($name, '\\')) {
            // Prepend current namespace
            if ($currentNamespace !== '') {
                return $currentNamespace . '\\' . $name;
            }
            return $name;
        }

        // Default to current namespace
        if ($currentNamespace !== '') {
            return $currentNamespace . '\\' . $name;
        }

        return $name;
    }

    /**
     * Check if a name is a primitive PHP type.
     *
     * @param string $name The name to check
     * @return bool True if it's a primitive type
     */
    public function isPrimitiveType(string $name): bool
    {
        return in_array(strtolower($name), self::PRIMITIVE_TYPES, true);
    }

    /**
     * Extract the class name from a FQCN or name.
     *
     * @param string $name The name to extract from
     * @return string The class name
     */
    private function extractClassName(string $name): string
    {
        return FqcnUtils::extractClassName($name);
    }

    /**
     * Extract the namespace from a FQCN.
     *
     * @param string $fqcn The FQCN
     * @return string The namespace
     */
    public function extractNamespace(string $fqcn): string
    {
        return FqcnUtils::extractNamespace($fqcn);
    }

    /**
     * Extract the class name from a FQCN.
     *
     * @param string $fqcn The FQCN
     * @return string The class name
     */
    public function extractClassNameFromFqcn(string $fqcn): string
    {
        return FqcnUtils::extractClassName($fqcn);
    }
}
