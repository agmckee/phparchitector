<?php declare(strict_types=1);

namespace PhpArchitector\Util;

use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\Statement\UseNode;
use PhpSyntax\Nodes\UseItemNode;

/**
 * Utility class for extracting use statements from AST nodes.
 */
final class UseStatementExtractor
{
    /**
     * Extract all use statements from a file-level statement list.
     *
     * @param iterable<\PhpSyntax\Node> $statements The statements to search
     * @return array<string, string> Map of short name (or alias) to FQCN
     */
    public static function extractFromStatements(iterable $statements): array
    {
        $uses = [];
        
        foreach ($statements as $stmt) {
            if ($stmt instanceof UseNode) {
                $uses = array_merge($uses, self::extractFromUseNode($stmt));
            }
        }
        
        return $uses;
    }
    
    /**
     * Extract use statements from a UseNode.
     *
     * @param UseNode $useNode The use statement node
     * @return array<string, string> Map of short name (or alias) to FQCN
     */
    public static function extractFromUseNode(UseNode $useNode): array
    {
        $uses = [];
        
        foreach ($useNode->items->getItems() as $useItem) {
            $fqcn = self::getFqcnFromUseItem($useItem);
            $shortName = self::getShortNameFromUseItem($useItem);
            $uses[$shortName] = $fqcn;
        }
        
        return $uses;
    }
    
    /**
     * Extract use statements from a namespace node.
     *
     * @param NamespaceNode $namespaceNode The namespace node
     * @return array<string, string> Map of short name (or alias) to FQCN
     */
    public static function extractFromNamespace(NamespaceNode $namespaceNode): array
    {
        $uses = [];
        
        foreach ($namespaceNode->statements->getChildren() as $nsStmt) {
            if ($nsStmt instanceof UseNode) {
                $uses = array_merge($uses, self::extractFromUseNode($nsStmt));
            }
        }
        
        return $uses;
    }
    
    /**
     * Extract all use statements from a file node (both file-level and namespace-level).
     *
     * @param FileNode $fileNode The file AST node
     * @return array<string, string> Map of short name (or alias) to FQCN
     */
    public static function extractFromFile(FileNode $fileNode): array
    {
        $uses = [];
        
        // Extract file-level use statements
        $uses = array_merge($uses, self::extractFromStatements($fileNode->statements->getChildren()));
        
        // Extract namespace-level use statements
        foreach ($fileNode->statements->getChildren() as $stmt) {
            if ($stmt instanceof NamespaceNode) {
                $uses = array_merge($uses, self::extractFromNamespace($stmt));
            }
        }
        
        return $uses;
    }
    
    /**
     * Get the FQCN from a UseItemNode.
     *
     * @param UseItemNode $useItem The use item node
     * @return string The fully qualified class name
     */
    private static function getFqcnFromUseItem(UseItemNode $useItem): string
    {
        // UseItemNode has a $fullName property that handles group imports
        return $useItem->fullName;
    }
    
    /**
     * Get the short name (or alias) from a UseItemNode.
     *
     * @param UseItemNode $useItem The use item node
     * @return string The short name or alias
     */
    private static function getShortNameFromUseItem(UseItemNode $useItem): string
    {
        // If there's an alias, use it
        if ($useItem->alias !== null) {
            return $useItem->alias->text;
        }
        
        // Otherwise, use the last part of the name
        $parts = explode('\\', $useItem->name->text);
        return end($parts);
    }
}
