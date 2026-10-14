<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Pattern;

use PhpArchitector\Util\CodeMetrics;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\ModifiersNode;
use PhpSyntax\Visibility;
use PhpSyntax\Nodes\Expression\NewNode;

/**
 * Extracts information from class AST nodes.
 *
 * Responsibilities:
 * - Extract class structure (methods, properties, metrics)
 * - Count instantiations in method bodies
 */
final class ClassInfoExtractor
{
    /**
     * Extract class information from a ClassNode
     *
     * @param ClassNode $classNode The class AST node
     * @param string $namespace The namespace of the class
     * @param string $filePath The file path (for context)
     * @return array<string, mixed> Extracted class information
     */
    public function extract(ClassNode $classNode, string $namespace, string $filePath = ''): array
    {
        $className = $classNode->name->text;
        $fqcn = $namespace . ($namespace ? '\\' : '') . $className;

        $classInfo = [
            'fqcn' => $fqcn,
            'namespace' => $namespace,
            'name' => $className,
            'methods' => [],
            'properties' => [],
            'direct_instantiations' => 0,
            'static_methods' => 0,
            'static_properties' => 0,
            'lines_of_code' => 0,
            'constructor_params' => 0,
            'injected_dependencies' => 0,
        ];

        $this->extractClassMembers($classNode, $classInfo);
        $classInfo['lines_of_code'] = $this->countLinesOfCode($classNode);

        return $classInfo;
    }

    /**
     * Extract methods and properties from a class node
     */
    private function extractClassMembers(ClassNode $classNode, array &$classInfo): void
    {
        foreach ($classNode->members->getChildren() as $member) {
            if ($member instanceof MethodNode) {
                $this->extractMethodInfo($member, $classInfo);
            }

            if ($member instanceof PropertyNode) {
                $this->extractPropertyInfo($member, $classInfo);
            }
        }
    }

    /**
     * Extract information from a method node
     */
    private function extractMethodInfo(MethodNode $methodNode, array &$classInfo): void
    {
        $visibility = $this->getVisibility($methodNode->modifiers);
        $methodInfo = [
            'name' => $methodNode->name->text,
            'is_static' => $methodNode->modifiers->static ?? false,
            'is_public' => $visibility === 'public',
        ];

        $classInfo['methods'][] = $methodInfo;

        if ($methodNode->modifiers->static ?? false) {
            $classInfo['static_methods']++;
        }

        // Count constructor parameters for DI detection
        if ($methodNode->name->text === '__construct') {
            $classInfo['constructor_params'] = count($methodNode->parameters->getItems());

            // Check for type-hinted parameters (dependency injection)
            foreach ($methodNode->parameters->getItems() as $param) {
                if ($param->type !== null) {
                    $classInfo['injected_dependencies']++;
                }
            }
        }

        // Check for direct instantiation in method body
        if ($methodNode->body !== null) {
            $classInfo['direct_instantiations'] += $this->countInstantiations($methodNode->body);
        }
    }

    /**
     * Extract information from a property node
     */
    private function extractPropertyInfo(PropertyNode $propertyNode, array &$classInfo): void
    {
        $items = $propertyNode->items->getChildren();
        $propertyName = $items[0]->name?->text ?? '(anonymous)';

        $propertyInfo = [
            'name' => $propertyName,
            'is_static' => $propertyNode->modifiers->static ?? false,
        ];

        $classInfo['properties'][] = $propertyInfo;

        if ($propertyNode->modifiers->static ?? false) {
            $classInfo['static_properties']++;
        }
    }

    /**
     * Count instantiations in a node
     */
    private function countInstantiations(Node $node): int
    {
        $count = 0;

        if ($node instanceof NewNode) {
            return 1;
        }

        foreach ($node->getChildren() as $child) {
            if ($child instanceof Node) {
                $count += $this->countInstantiations($child);
            }
        }

        return $count;
    }

    /**
     * Count lines of code in a class.
     */
    private function countLinesOfCode(ClassNode $classNode): int
    {
        return CodeMetrics::countLinesOfCode($classNode);
    }

    /**
     * Get visibility from modifiers
     */
    private function getVisibility(ModifiersNode $modifiers): string
    {
        if ($modifiers->visibility === null) {
            return 'public';
        }

        return match ($modifiers->visibility) {
            Visibility::Public => 'public',
            Visibility::Protected => 'protected',
            Visibility::Private => 'private',
        };
    }
}
