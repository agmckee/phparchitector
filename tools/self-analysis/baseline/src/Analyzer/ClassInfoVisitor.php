<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer;

use PhpSyntax\Node as SyntaxNode;
use PhpSyntax\Token;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\Member\ClassConstNode;
use PhpSyntax\Nodes\ModifiersNode;
use PhpSyntax\Visibility;
use PhpArchitector\Util\AstVisitor;
use PhpArchitector\Util\UseStatementExtractor;
use PhpArchitector\Util\CodeMetrics;
use PhpArchitector\Util\FqcnUtils;
use PhpArchitector\Model\ClassInfo;
use PhpArchitector\Model\MethodInfo;
use PhpArchitector\Model\PropertyInfo;
use PhpArchitector\Model\ConstantInfo;

/**
 * Visitor for extracting class information from AST nodes.
 * 
 * Uses the Visitor pattern to traverse the AST and collect class metadata.
 * This replaces manual AST traversal with a cleaner, more maintainable approach.
 */
final class ClassInfoVisitor extends AstVisitor
{
    private ?string $namespace = null;
    private ?ClassNode $classNode = null;
    private array $useStatements = [];
    private string $filePath = '';

    /**
     * Extract class information from a file node.
     *
     * @param FileNode $fileNode The parsed file AST
     * @param string $filePath Path to the file (for context)
     * @return ClassInfo The extracted class information
     */
    public function extractFromFile(FileNode $fileNode, string $filePath = ''): ClassInfo
    {
        $this->filePath = $filePath;
        $this->namespace = null;
        $this->classNode = null;
        
        // Extract use statements using utility
        $this->useStatements = UseStatementExtractor::extractFromFile($fileNode);
        
        // Traverse using Visitor pattern
        $this->traverse($fileNode);

        if ($this->classNode === null) {
            return new ClassInfo(
                namespace: null,
                name: '',
                fqcn: null,
                extends: null,
                implements: [],
                methods: [],
                properties: [],
                constants: [],
                dependencies: array_values($this->useStatements),
                linesOfCode: 0,
                filePath: $filePath
            );
        }

        return $this->extractClassInfo($this->classNode);
    }

    /**
     * @inheritDoc
     */
    protected function enter(SyntaxNode|Token $node): TraverseAction|array|string|int|float|bool|\stdClass|null
    {
        if ($node instanceof NamespaceNode) {
            $this->namespace = $node->name->text;
            return null;
        }

        if ($node instanceof ClassNode) {
            $this->classNode = $node;
            // Return SkipChildren to avoid reprocessing
            return TraverseAction::SkipChildren;
        }

        return null;
    }

    /**
     * Extract class information from a class node.
     *
     * @param ClassNode $classNode The class AST node
     * @return ClassInfo The class information
     */
    private function extractClassInfo(ClassNode $classNode): ClassInfo
    {
        $className = $classNode->name->text;
        $fqcn = $this->namespace ? $this->namespace . '\\' . $className : $className;

        // Extract extends
        $extends = null;
        if ($classNode->extends !== null) {
            $extends = $classNode->extends->text;
        }

        // Extract implements
        $implements = [];
        if ($classNode->implements !== null) {
            foreach ($classNode->implements->getItems() as $interface) {
                $implements[] = $interface->text;
            }
        }

        // Extract methods
        $methods = $this->extractMethods($classNode);
        
        // Extract properties
        $properties = $this->extractProperties($classNode);
        
        // Extract constants
        $constants = $this->extractConstants($classNode);

        // Count lines of code
        $linesOfCode = CodeMetrics::countLinesOfCode($classNode);

        return new ClassInfo(
            namespace: $this->namespace,
            name: $className,
            fqcn: $fqcn,
            extends: $extends,
            implements: $implements,
            methods: $methods,
            properties: $properties,
            constants: $constants,
            dependencies: array_values($this->useStatements),
            linesOfCode: $linesOfCode,
            filePath: $this->filePath
        );
    }

    /**
     * Extract methods from a class node.
     *
     * @param ClassNode $classNode The class AST node
     * @return array<int, MethodInfo> List of method DTOs
     */
    private function extractMethods(ClassNode $classNode): array
    {
        $methods = [];
        
        foreach ($classNode->members->getChildren() as $member) {
            if ($member instanceof MethodNode) {
                $methods[] = new MethodInfo(
                    name: $member->name->text,
                    visibility: $this->getVisibility($member->modifiers),
                    returnType: null, // TODO: Extract return type
                    parameters: [], // TODO: Extract parameters
                    isStatic: $member->modifiers->static,
                    isAbstract: $member->modifiers->abstract,
                    isFinal: $member->modifiers->final
                );
            }
        }
        
        return $methods;
    }

    /**
     * Extract properties from a class node.
     *
     * @param ClassNode $classNode The class AST node
     * @return array<int, PropertyInfo> List of property DTOs
     */
    private function extractProperties(ClassNode $classNode): array
    {
        $properties = [];
        
        foreach ($classNode->members->getChildren() as $member) {
            if ($member instanceof PropertyNode) {
                foreach ($member->items->getChildren() as $property) {
                    $properties[] = new PropertyInfo(
                        name: $property->name?->text ?? '(anonymous)',
                        type: null, // TODO: Extract type
                        visibility: $this->getVisibility($member->modifiers),
                        isStatic: $member->modifiers->static
                    );
                }
            }
        }
        
        return $properties;
    }

    /**
     * Extract constants from a class node.
     *
     * @param ClassNode $classNode The class AST node
     * @return array<int, ConstantInfo> List of constant DTOs
     */
    private function extractConstants(ClassNode $classNode): array
    {
        $constants = [];
        
        foreach ($classNode->members->getChildren() as $member) {
            if ($member instanceof ClassConstNode) {
                foreach ($member->items->getChildren() as $constant) {
                    $constants[] = new ConstantInfo(
                        name: $constant->name->text,
                        visibility: $this->getVisibility($member->modifiers)
                    );
                }
            }
        }
        
        return $constants;
    }

    /**
     * Get visibility from modifiers.
     *
     * @param ModifiersNode $modifiers The modifiers node
     * @return string One of 'public', 'protected', 'private'
     */
    private function getVisibility(ModifiersNode $modifiers): string
    {
        return match ($modifiers->visibility) {
            Visibility::Public => 'public',
            Visibility::Protected => 'protected',
            Visibility::Private => 'private',
        };
    }
}
