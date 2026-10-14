<?php declare(strict_types=1);

namespace PhpArchitector\Util;

/**
 * Utility class for filesystem operations.
 */
final class FileSystemUtils
{
    /**
     * Find all PHP files in a directory recursively.
     *
     * @param string $directory The directory to search
     * @return array<int, string> List of PHP file paths
     */
    public static function findPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Check if a path is a directory.
     *
     * @param string $path The path to check
     * @return bool True if it's a directory
     */
    public static function isDirectory(string $path): bool
    {
        return is_dir($path);
    }
    
    /**
     * Get the relative path from a base directory.
     *
     * @param string $path The full path
     * @param string $baseDir The base directory
     * @return string The relative path
     */
    public static function getRelativePath(string $path, string $baseDir): string
    {
        // Normalize paths for comparison
        $normalizedPath = rtrim($path, '/');
        $normalizedBase = rtrim($baseDir, '/');
        
        if (str_starts_with($normalizedPath, $normalizedBase)) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }
        
        return $path;
    }
}
