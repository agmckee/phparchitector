<?php declare(strict_types=1);

namespace PhpArchitector\Util;

/**
 * Utility class for working with Fully Qualified Class Names (FQCNs).
 */
final class FqcnUtils
{
    /**
     * Extract the class name (last segment) from a FQCN.
     *
     * @param string $fqcn The fully qualified class name (e.g., 'App\Controller\UserController')
     * @return string The class name (e.g., 'UserController')
     */
    public static function extractClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
    
    /**
     * Extract the namespace from a FQCN.
     *
     * @param string $fqcn The fully qualified class name
     * @return string The namespace (e.g., 'App\Controller')
     */
    public static function extractNamespace(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        array_pop($parts);
        return implode('\\', $parts);
    }
    
    /**
     * Check if a string is a valid FQCN (contains at least one backslash).
     *
     * @param string $fqcn The string to check
     * @return bool True if it's a valid FQCN
     */
    public static function isFullyQualified(string $fqcn): bool
    {
        return str_contains($fqcn, '\\');
    }
    
    /**
     * Convert a relative class name to FQCN given a namespace.
     *
     * @param string $className The class name (e.g., 'UserController')
     * @param string $namespace The namespace (e.g., 'App\Controller')
     * @return string The FQCN (e.g., 'App\Controller\UserController')
     */
    public static function toFqcn(string $className, string $namespace): string
    {
        if (empty($namespace)) {
            return $className;
        }
        return $namespace . '\\' . $className;
    }
}
