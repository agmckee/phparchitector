<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer;

use PhpSyntax\Nodes\FileNode;

/**
 * Interface for all analyzers.
 * 
 * Provides a consistent API for analyzing PHP code.
 */
interface AnalyzerInterface
{
    /**
     * Analyze a PHP source string.
     *
     * @param string $source The PHP source code
     * @return array<string, mixed> Extracted information
     */
    public function analyze(string $source): array;
    
    /**
     * Analyze a PHP file from disk.
     *
     * @param string $filePath The path to the PHP file
     * @return array<string, mixed> Extracted information
     */
    public function analyzeFile(string $filePath): array;
    
    /**
     * Analyze all PHP files in a directory.
     *
     * @param string $directory The directory to analyze
     * @return array<int, array<string, mixed>> List of extracted information for each file
     */
    public function analyzeDirectory(string $directory): array;
}
