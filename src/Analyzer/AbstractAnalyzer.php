<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer;

use PhpSyntax\Parser;
use PhpSyntax\Nodes\FileNode;
use PhpArchitector\Util\FileSystemUtils;

/**
 * Base class for all analyzers with shared functionality.
 * 
 * Provides common utilities for parsing PHP code and traversing file systems.
 */
abstract class AbstractAnalyzer
{
    /**
     * The parser instance for parsing PHP code.
     */
    protected Parser $parser;
    
    /**
     * @param Parser|null $parser Optional parser instance (for dependency injection)
     */
    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? new Parser();
    }
    
    /**
     * Parse a PHP source string into a FileNode.
     *
     * @param string $source The PHP source code
     * @return FileNode The parsed file node
     */
    protected function parse(string $source): FileNode
    {
        return $this->parser->parse($source);
    }
    
    /**
     * Parse a PHP file from disk into a FileNode.
     *
     * @param string $filePath The path to the PHP file
     * @return FileNode The parsed file node
     */
    protected function parseFile(string $filePath): FileNode
    {
        $source = file_get_contents($filePath);
        return $this->parse($source);
    }
    
    /**
     * Find all PHP files in a directory recursively.
     *
     * @param string $directory The directory to search
     * @return array<int, string> List of PHP file paths
     */
    protected function findPhpFiles(string $directory): array
    {
        return FileSystemUtils::findPhpFiles($directory);
    }
    
    /**
     * Get the parser instance.
     *
     * @return Parser The parser
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }
}
