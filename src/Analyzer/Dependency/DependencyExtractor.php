<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Dependency;

use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\InterfaceNode;
use PhpSyntax\Nodes\Statement\TraitUseNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\Statement\UseNode;
use PhpSyntax\Nodes\Statement\UseItemNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\Expression\NewNode;
use PhpSyntax\Nodes\Expression\StaticCallNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Type\NamedTypeNode;
use PhpSyntax\Nodes\Type\UnionTypeNode;
use PhpSyntax\Nodes\Type\NullableTypeNode;
use PhpSyntax\Nodes\Type\IntersectionTypeNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpArchitector\Util\UseStatementExtractor;
use PhpArchitector\Util\FqcnUtils;

/**
 * Extracts dependencies from PHP AST nodes.
 *
 * Extracts various types of dependencies:
 * - Use statement imports
 * - Type hints (parameters, return types, properties)
 * - Class instantiation (new)
 * - Static method calls
 * - Parent class (extends)
 * - Interface implementation (implements)
 * - Trait usage (use)
 */
final class DependencyExtractor
{
    private DependencyResolver $resolver;

    public function __construct(DependencyResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new DependencyResolver();
    }

    /**
     * Extract all classes and their dependencies from a file.
     * Uses the Visitor pattern via DependencyVisitor for cleaner AST traversal.
     *
     * @param FileNode $fileNode The parsed file AST
     * @return array<string, mixed> File information with classes, use statements, and namespace
     */
    public function extractFromFile(FileNode $fileNode): array
    {
        // Use the Visitor pattern for cleaner AST traversal
        $visitor = new DependencyVisitor($this->resolver);
        $fileInfo = $visitor->extractFromFile($fileNode);
        
        return $fileInfo;
    }

    /**
     * Extract all classes and their dependencies from a file using legacy method.
     * Kept for reference and backward compatibility testing.
     *
     * @param FileNode $fileNode The parsed file AST
     * @return array<string, mixed> File information with classes, use statements, and namespace
     * @deprecated Use extractFromFile() which uses the Visitor pattern
     */
    public function extractFromFileLegacy(FileNode $fileNode): array
    {
        $result = [
            'uses' => [],
            'namespace' => null,
            'classes' => [],
        ];

        $currentNamespace = null;

        // Extract all use statements using the utility
        $useStatements = UseStatementExtractor::extractFromFile($fileNode);
        $result['uses'] = $useStatements;

        foreach ($fileNode->statements->getChildren() as $stmt) {
            if ($stmt instanceof NamespaceNode) {
                $currentNamespace = $stmt->name->text;
                $result['namespace'] = $currentNamespace;

                // Collect namespace-level use statements
                $namespaceUses = UseStatementExtractor::extractFromNamespace($stmt);
                $result['uses'] = array_merge($result['uses'], $namespaceUses);

                // Extract classes and interfaces inside namespace
                foreach ($stmt->statements->getChildren() as $namespaceStmt) {
                    if ($namespaceStmt instanceof ClassNode) {
                        $result['classes'][] = $this->extractClassWithDeps(
                            $namespaceStmt,
                            $currentNamespace,
                            $result['uses']
                        );
                    }
                    if ($namespaceStmt instanceof InterfaceNode) {
                        $result['classes'][] = $this->extractInterfaceWithDeps(
                            $namespaceStmt,
                            $currentNamespace,
                            $result['uses']
                        );
                    }
                }
            }

            // Top-level class (no namespace)
            if ($stmt instanceof ClassNode && $currentNamespace === null) {
                $result['classes'][] = $this->extractClassWithDeps($stmt, '', $result['uses']);
            }
            if ($stmt instanceof InterfaceNode && $currentNamespace === null) {
                $result['classes'][] = $this->extractInterfaceWithDeps($stmt, '', $result['uses']);
            }
        }

        return $result;
    }

    /**
     * Extract class with its dependencies.
     *
     * @param ClassNode $classNode The class AST node
     * @param string $namespace The namespace
     * @param array<string, string> $useStatements Map of short names to FQCNs
     * @return array<string, mixed> Class information with dependencies
     */
    private function extractClassWithDeps(ClassNode $classNode, string $namespace, array $useStatements): array
    {
        $fqcn = $namespace . ($namespace ? '\\' : '') . $classNode->name->text;

        $dependencies = [];

        // Add extends dependency
        if ($classNode->extends !== null) {
            $dep = $this->resolver->resolve(
                $classNode->extends->text,
                $useStatements,
                $namespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'extends'];
            }
        }

        // Add implements dependencies
        if ($classNode->implements !== null) {
            foreach ($classNode->implements->getItems() as $interface) {
                $dep = $this->resolver->resolve(
                    $interface->text,
                    $useStatements,
                    $namespace
                );
                if ($dep !== null) {
                    $dependencies[] = ['fqcn' => $dep, 'type' => 'implements'];
                }
            }
        }

        // Extract from members (methods and properties)
        foreach ($classNode->members->getChildren() as $member) {
            $memberDeps = $this->extractMemberDependencies($member, $useStatements, $namespace);
            $dependencies = array_merge($dependencies, $memberDeps);
        }

        return [
            'fqcn' => $fqcn,
            'type' => 'class',
            'namespace' => $namespace,
            'name' => $classNode->name->text,
            'dependencies' => $dependencies,
        ];
    }

    /**
     * Extract interface with its dependencies.
     *
     * @param InterfaceNode $interfaceNode The interface AST node
     * @param string $namespace The namespace
     * @param array<string, string> $useStatements Map of short names to FQCNs
     * @return array<string, mixed> Interface information with dependencies
     */
    private function extractInterfaceWithDeps(
        InterfaceNode $interfaceNode,
        string $namespace,
        array $useStatements
    ): array {
        $fqcn = $namespace . ($namespace ? '\\' : '') . $interfaceNode->name->text;

        $dependencies = [];

        // Add extends dependencies
        if ($interfaceNode->extends !== null) {
            foreach ($interfaceNode->extends->getItems() as $parent) {
                $dep = $this->resolver->resolve(
                    $parent->text,
                    $useStatements,
                    $namespace
                );
                if ($dep !== null) {
                    $dependencies[] = ['fqcn' => $dep, 'type' => 'extends'];
                }
            }
        }

        return [
            'fqcn' => $fqcn,
            'type' => 'interface',
            'namespace' => $namespace,
            'name' => $interfaceNode->name->text,
            'dependencies' => $dependencies,
        ];
    }

    /**
     * Extract dependencies from a class member.
     *
     * @param mixed $member The member AST node
     * @param array<string, string> $useStatements Map of short names to FQCNs
     * @param string $namespace The current namespace
     * @return array<int, array<string, string>> List of dependencies
     */
    private function extractMemberDependencies($member, array $useStatements, string $namespace): array
    {
        $dependencies = [];

        if ($member instanceof MethodNode) {
            // Return type
            if ($member->returnType !== null) {
                $deps = $this->extractTypeDependencies($member->returnType, $useStatements, $namespace);
                $dependencies = array_merge($dependencies, $deps);
            }

            // Parameters
            foreach ($member->parameters->getItems() as $param) {
                if ($param->type !== null) {
                    $deps = $this->extractTypeDependencies($param->type, $useStatements, $namespace);
                    $dependencies = array_merge($dependencies, $deps);
                }
            }

            // Method body for instantiation and static calls
            if ($member->body !== null) {
                $deps = $this->extractExpressionDependencies($member->body, $useStatements, $namespace);
                $dependencies = array_merge($dependencies, $deps);
            }
        }

        if ($member instanceof PropertyNode) {
            if ($member->type !== null) {
                $deps = $this->extractTypeDependencies($member->type, $useStatements, $namespace);
                $dependencies = array_merge($dependencies, $deps);
            }
        }

        return $dependencies;
    }

    /**
     * Extract dependencies from a type node.
     *
     * @param mixed $typeNode The type AST node
     * @param array<string, string> $useStatements Map of short names to FQCNs
     * @param string $namespace The current namespace
     * @return array<int, array<string, string>> List of dependencies
     */
    private function extractTypeDependencies($typeNode, array $useStatements, string $namespace): array
    {
        $dependencies = [];

        // Handle NamedTypeNode (e.g., UserRepository in "private UserRepository $foo")
        if ($typeNode instanceof NamedTypeNode) {
            $dep = $this->resolver->resolve(
                $typeNode->name->text,
                $useStatements,
                $namespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'type_hint'];
            }
        }

        // Handle NameNode (direct name reference)
        if ($typeNode instanceof NameNode) {
            $dep = $this->resolver->resolve(
                $typeNode->text,
                $useStatements,
                $namespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'type_hint'];
            }
        }

        // Handle UnionTypeNode (e.g., Type1|Type2)
        if ($typeNode instanceof UnionTypeNode) {
            foreach ($typeNode->types->getItems() as $unionType) {
                $deps = $this->extractTypeDependencies($unionType, $useStatements, $namespace);
                $dependencies = array_merge($dependencies, $deps);
            }
        }

        // Handle NullableTypeNode (e.g., ?Type)
        if ($typeNode instanceof NullableTypeNode) {
            $deps = $this->extractTypeDependencies($typeNode->type, $useStatements, $namespace);
            $dependencies = array_merge($dependencies, $deps);
        }

        // Handle IntersectionTypeNode (e.g., Type1&Type2)
        if ($typeNode instanceof IntersectionTypeNode) {
            foreach ($typeNode->types->getItems() as $intersectionType) {
                $deps = $this->extractTypeDependencies($intersectionType, $useStatements, $namespace);
                $dependencies = array_merge($dependencies, $deps);
            }
        }

        return $dependencies;
    }

    /**
     * Extract dependencies from expression statements.
     *
     * @param mixed $node The expression AST node
     * @param array<string, string> $useStatements Map of short names to FQCNs
     * @param string $namespace The current namespace
     * @return array<int, array<string, string>> List of dependencies
     */
    private function extractExpressionDependencies(
        $node,
        array $useStatements,
        string $namespace
    ): array {
        $dependencies = [];

        if ($node instanceof NewNode && $node->class instanceof NameNode) {
            $dep = $this->resolver->resolve(
                $node->class->text,
                $useStatements,
                $namespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'instantiation'];
            }
        }

        if ($node instanceof StaticCallNode && $node->class instanceof NameNode) {
            $dep = $this->resolver->resolve(
                $node->class->text,
                $useStatements,
                $namespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'static_call'];
            }
        }

        // Recurse into child nodes
        foreach ($node->getChildren() as $child) {
            if ($child instanceof Node) {
                $deps = $this->extractExpressionDependencies($child, $useStatements, $namespace);
                $dependencies = array_merge($dependencies, $deps);
            }
        }

        return $dependencies;
    }
}
