<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Dependency;

use PhpSyntax\Node as SyntaxNode;
use PhpSyntax\Token;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\InterfaceNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\Statement\UseNode;
use PhpSyntax\Nodes\Statement\UseItemNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\Expression\NewNode;
use PhpSyntax\Nodes\Expression\StaticCallNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Type\NamedTypeNode;
use PhpSyntax\Traverser;
use PhpSyntax\TraverseAction;
use PhpArchitector\Util\AstVisitor;
use PhpArchitector\Util\UseStatementExtractor;

/**
 * Visitor for extracting dependencies from AST nodes.
 * 
 * Uses the Visitor pattern to traverse the AST and collect dependencies.
 */
final class DependencyVisitor extends AstVisitor
{
    private DependencyResolver $resolver;
    private string $namespace = '';
    private array $useStatements = [];
    private string $currentNamespace = '';
    
    /** @var array<int, array{fqcn: string, type: string}> */
    private array $dependencies = [];
    
    /** @var array<string, array{fqcn: string, type: string, namespace: string, name: string, dependencies: array}> */
    private array $classes = [];

    public function __construct(DependencyResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new DependencyResolver();
    }

    /**
     * Extract dependencies from a file node.
     *
     * @param FileNode $fileNode The parsed file AST
     * @return array<string, mixed> File information with classes and dependencies
     */
    public function extractFromFile(FileNode $fileNode): array
    {
        $this->namespace = '';
        $this->currentNamespace = '';
        $this->useStatements = [];
        $this->dependencies = [];
        $this->classes = [];

        // First, extract use statements
        $this->useStatements = UseStatementExtractor::extractFromFile($fileNode);

        // Then traverse to collect dependencies
        $this->traverse($fileNode);

        return [
            'uses' => $this->useStatements,
            'namespace' => $this->namespace,
            'classes' => array_values($this->classes),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function enter(SyntaxNode|Token $node): TraverseAction|array|string|int|float|bool|\stdClass|null
    {
        // Track namespace
        if ($node instanceof NamespaceNode) {
            $this->currentNamespace = $node->name->text;
            $this->namespace = $this->currentNamespace;
            return null;
        }

        // Collect classes and their dependencies
        if ($node instanceof ClassNode) {
            $this->processClass($node);
            return TraverseAction::SkipChildren; // Skip children, we process them manually
        }

        if ($node instanceof InterfaceNode) {
            $this->processInterface($node);
            return TraverseAction::SkipChildren;
        }

        return null;
    }

    /**
     * Process a class node to extract its dependencies.
     *
     * @param ClassNode $classNode The class AST node
     */
    private function processClass(ClassNode $classNode): void
    {
        $className = $classNode->name->text;
        $fqcn = $this->currentNamespace . ($this->currentNamespace ? '\\' : '') . $className;

        $dependencies = [];

        // Add extends dependency
        if ($classNode->extends !== null) {
            $dep = $this->resolver->resolve(
                $classNode->extends->text,
                $this->useStatements,
                $this->currentNamespace
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
                    $this->useStatements,
                    $this->currentNamespace
                );
                if ($dep !== null) {
                    $dependencies[] = ['fqcn' => $dep, 'type' => 'implements'];
                }
            }
        }

        // Process members for type hints, instantiations, etc.
        $this->processMembers($classNode->members->getChildren(), $dependencies);

        $this->classes[$fqcn] = [
            'fqcn' => $fqcn,
            'type' => 'class',
            'namespace' => $this->currentNamespace,
            'name' => $className,
            'dependencies' => $dependencies,
        ];
    }

    /**
     * Process an interface node to extract its dependencies.
     *
     * @param InterfaceNode $interfaceNode The interface AST node
     */
    private function processInterface(InterfaceNode $interfaceNode): void
    {
        $interfaceName = $interfaceNode->name->text;
        $fqcn = $this->currentNamespace . ($this->currentNamespace ? '\\' : '') . $interfaceName;

        $dependencies = [];

        // Add extends dependencies
        if ($interfaceNode->extends !== null) {
            foreach ($interfaceNode->extends->getItems() as $parent) {
                $dep = $this->resolver->resolve(
                    $parent->text,
                    $this->useStatements,
                    $this->currentNamespace
                );
                if ($dep !== null) {
                    $dependencies[] = ['fqcn' => $dep, 'type' => 'extends'];
                }
            }
        }

        $this->classes[$fqcn] = [
            'fqcn' => $fqcn,
            'type' => 'interface',
            'namespace' => $this->currentNamespace,
            'name' => $interfaceName,
            'dependencies' => $dependencies,
        ];
    }

    /**
     * Process class members to extract dependencies.
     *
     * @param array<Node> $members The member nodes
     * @param array<int, array{fqcn: string, type: string}> $dependencies Accumulator for dependencies
     */
    private function processMembers(array $members, array &$dependencies): void
    {
        foreach ($members as $member) {
            if ($member instanceof MethodNode) {
                $this->processMethod($member, $dependencies);
            } elseif ($member instanceof PropertyNode) {
                $this->processProperty($member, $dependencies);
            }
        }
    }

    /**
     * Process a method node to extract dependencies from type hints and body.
     *
     * @param MethodNode $methodNode The method AST node
     * @param array<int, array{fqcn: string, type: string}> $dependencies Accumulator for dependencies
     */
    private function processMethod(MethodNode $methodNode, array &$dependencies): void
    {
        // Return type
        if ($methodNode->returnType !== null) {
            $this->processTypeNode($methodNode->returnType, $dependencies);
        }

        // Parameters
        foreach ($methodNode->parameters->getItems() as $param) {
            if ($param->type !== null) {
                $this->processTypeNode($param->type, $dependencies);
            }
        }

        // Method body for instantiation and static calls
        if ($methodNode->body !== null) {
            $this->processNodeForDependencies($methodNode->body, $dependencies);
        }
    }

    /**
     * Process a property node to extract dependencies from type hints.
     *
     * @param PropertyNode $propertyNode The property AST node
     * @param array<int, array{fqcn: string, type: string}> $dependencies Accumulator for dependencies
     */
    private function processProperty(PropertyNode $propertyNode, array &$dependencies): void
    {
        if ($propertyNode->type !== null) {
            $this->processTypeNode($propertyNode->type, $dependencies);
        }
    }

    /**
     * Process a type node to extract dependencies.
     *
     * @param Node $typeNode The type AST node
     * @param array<int, array{fqcn: string, type: string}> $dependencies Accumulator for dependencies
     */
    private function processTypeNode(SyntaxNode $typeNode, array &$dependencies): void
    {
        if ($typeNode instanceof NamedTypeNode) {
            $dep = $this->resolver->resolve(
                $typeNode->name->text,
                $this->useStatements,
                $this->currentNamespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'type_hint'];
            }
        } elseif ($typeNode instanceof NameNode) {
            $dep = $this->resolver->resolve(
                $typeNode->text,
                $this->useStatements,
                $this->currentNamespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'type_hint'];
            }
        }
        // Note: UnionTypeNode, NullableTypeNode, IntersectionTypeNode handled by DependencyResolver
    }

    /**
     * Process a node for instantiation and static call dependencies.
     *
     * @param Node $node The AST node
     * @param array<int, array{fqcn: string, type: string}> $dependencies Accumulator for dependencies
     */
    private function processNodeForDependencies(SyntaxNode $node, array &$dependencies): void
    {
        if ($node instanceof NewNode && $node->class instanceof NameNode) {
            $dep = $this->resolver->resolve(
                $node->class->text,
                $this->useStatements,
                $this->currentNamespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'instantiation'];
            }
        }

        if ($node instanceof StaticCallNode && $node->class instanceof NameNode) {
            $dep = $this->resolver->resolve(
                $node->class->text,
                $this->useStatements,
                $this->currentNamespace
            );
            if ($dep !== null) {
                $dependencies[] = ['fqcn' => $dep, 'type' => 'static_call'];
            }
        }

        // Recurse into child nodes
        foreach ($node->getChildren() as $child) {
            if ($child instanceof Node) {
                $this->processNodeForDependencies($child, $dependencies);
            }
        }
    }

    /**
     * Get the collected dependencies.
     *
     * @return array<int, array{fqcn: string, type: string}>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Get the collected classes.
     *
     * @return array<string, array{fqcn: string, type: string, namespace: string, name: string, dependencies: array}>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }
}
