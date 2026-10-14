<?php declare(strict_types=1);

namespace PhpArchitector\Util;

use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyNode;

/**
 * Utility class for calculating code metrics.
 */
final class CodeMetrics
{
    /**
     * Count the lines of code in a class node.
     *
     * @param ClassNode $classNode The class AST node
     * @return int The number of lines
     */
    public static function countLinesOfCode(ClassNode $classNode): int
    {
        $tokens = $classNode->getTokens();
        $lines = 0;
        
        foreach ($tokens as $token) {
            if (strpos($token->text, "\n") !== false) {
                $lines += substr_count($token->text, "\n");
            }
        }
        
        return max(1, $lines);
    }
    
    /**
     * Count the number of methods in a class.
     *
     * @param ClassNode $classNode The class AST node
     * @return int The number of methods
     */
    public static function countMethods(ClassNode $classNode): int
    {
        $count = 0;
        foreach ($classNode->members->getChildren() as $member) {
            if ($member instanceof MethodNode) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Count the number of properties in a class.
     *
     * @param ClassNode $classNode The class AST node
     * @return int The number of properties
     */
    public static function countProperties(ClassNode $classNode): int
    {
        $count = 0;
        foreach ($classNode->members->getChildren() as $member) {
            if ($member instanceof PropertyNode) {
                // PropertyNode may have multiple declarations
                $declarations = $member->declarations?->getItems();
                if ($declarations !== null) {
                    $count += count($declarations);
                } else {
                    $count++;
                }
            }
        }
        return $count;
    }
}
