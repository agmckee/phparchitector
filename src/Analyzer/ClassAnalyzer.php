<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer;

use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\Member\ClassConstNode;
use PhpSyntax\Nodes\ModifiersNode;
use PhpSyntax\Token;
use PhpSyntax\Visibility;
use PhpArchitector\Util\CodeMetrics;
use PhpArchitector\Util\UseStatementExtractor;
use PhpArchitector\Model\ClassInfo;
use PhpArchitector\Model\MethodInfo;
use PhpArchitector\Model\PropertyInfo;
use PhpArchitector\Model\ConstantInfo;

/**
 * Analyzes PHP class files to extract structural information.
 * 
 * Uses Model DTOs internally for type safety while maintaining
 * backward compatibility with array return types.
 */
class ClassAnalyzer extends AbstractAnalyzer implements AnalyzerInterface
{
    /**
     * Analyze a PHP file and return class information.
     *
     * @param string $source PHP source code
     * @return array<string, mixed> Extracted class information (for backward compatibility)
     */
    public function analyze(string $source): array
    {
        $fileNode = $this->parse($source);
        $classInfo = $this->extractClassInfoFromFile($fileNode, '');
        return $classInfo->toArray();
    }

    /**
     * Analyze a file from disk.
     *
     * @param string $filePath Path to PHP file
     * @return array<string, mixed> Extracted class information (for backward compatibility)
     */
    public function analyzeFile(string $filePath): array
    {
        $source = file_get_contents($filePath);
        return $this->analyze($source);
    }

    /**
     * Analyze a directory of PHP files.
     *
     * @param string $directory Path to the directory to analyze
     * @return array<string, array<string, mixed>> Analysis results for all files
     */
    public function analyzeDirectory(string $directory): array
    {
        $files = $this->findPhpFiles($directory);
        $results = [];

        foreach ($files as $file) {
            $results[$file] = $this->analyzeFile($file);
        }

        return $results;
    }

    /**
     * Analyze a PHP file and return class information as a DTO.
     *
     * @param string $source PHP source code
     * @return ClassInfo Extracted class information as DTO
     */
    public function analyzeAsDto(string $source): ClassInfo
    {
        $fileNode = $this->parse($source);
        return $this->extractClassInfoFromFile($fileNode, '');
    }

    /**
     * Analyze a file from disk and return class information as a DTO.
     *
     * @param string $filePath Path to PHP file
     * @return ClassInfo Extracted class information as DTO
     */
    public function analyzeFileAsDto(string $filePath): ClassInfo
    {
        $source = file_get_contents($filePath);
        return $this->analyzeAsDto($source);
    }

    /**
     * Extract class information from a FileNode as a DTO.
     *
     * @param FileNode $fileNode The parsed file AST
     * @param string $filePath Path to the file (for context)
     * @return ClassInfo The class information DTO
     */
    private function extractClassInfoFromFile(FileNode $fileNode, string $filePath): ClassInfo
    {
        // Extract all use statements using the utility
        $useStatements = UseStatementExtractor::extractFromFile($fileNode);
        
        $classNode = null;
        $namespace = null;
        
        // Find the class and extract its information
        foreach ($fileNode->statements->getChildren() as $stmt) {
            if ($stmt instanceof NamespaceNode) {
                $namespace = $stmt->name->text;
                
                foreach ($stmt->statements->getChildren() as $namespaceStmt) {
                    if ($namespaceStmt instanceof ClassNode) {
                        $classNode = $namespaceStmt;
                        break 2; // Break out of both loops
                    }
                }
            } elseif ($stmt instanceof ClassNode) {
                $classNode = $stmt;
                break;
            }
        }
        
        if ($classNode === null) {
            // Return empty ClassInfo if no class found
            return new ClassInfo(
                namespace: null,
                name: '',
                fqcn: null,
                extends: null,
                implements: [],
                methods: [],
                properties: [],
                constants: [],
                dependencies: array_values($useStatements),
                linesOfCode: 0,
                filePath: $filePath
            );
        }
        
        $className = $classNode->name->text;
        $fqcn = $namespace ? $namespace . '\\' . $className : $className;
        
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
        
        // Extract methods, properties, and constants from members
        $methods = $this->extractMethodsFromClass($classNode);
        $properties = $this->extractPropertiesFromClass($classNode);
        $constants = $this->extractConstantsFromClass($classNode);
        
        // Count lines of code using utility
        $linesOfCode = CodeMetrics::countLinesOfCode($classNode);
        
        return new ClassInfo(
            namespace: $namespace,
            name: $className,
            fqcn: $fqcn,
            extends: $extends,
            implements: $implements,
            methods: $methods,
            properties: $properties,
            constants: $constants,
            dependencies: array_values($useStatements),
            linesOfCode: $linesOfCode,
            filePath: $filePath
        );
    }
    
    /**
     * Extract methods from a class node.
     *
     * @param ClassNode $classNode The class AST node
     * @return array<int, MethodInfo> List of method DTOs
     */
    private function extractMethodsFromClass(ClassNode $classNode): array
    {
        $methods = [];
        
        foreach ($classNode->members->getChildren() as $member) {
            if ($member instanceof MethodNode) {
                // Extract return type
                $returnType = null;
                if ($member->returnType !== null) {
                    $returnType = $this->extractTypeFromTypeNode($member->returnType);
                }
                
                // Extract parameters
                $parameters = [];
                foreach ($member->parameters->getItems() as $param) {
                    $parameters[] = [
                        'name' => $param->variable->name->text ?? '(anonymous)',
                        'type' => $param->type !== null ? $this->extractTypeFromTypeNode($param->type) : null,
                    ];
                }
                
                $methods[] = new MethodInfo(
                    name: $member->name->text,
                    visibility: $this->getVisibility($member->modifiers),
                    returnType: $returnType,
                    parameters: $parameters,
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
    private function extractPropertiesFromClass(ClassNode $classNode): array
    {
        $properties = [];
        
        foreach ($classNode->members->getChildren() as $member) {
            if ($member instanceof PropertyNode) {
                $propertyType = null;
                if ($member->type !== null) {
                    $propertyType = $this->extractTypeFromTypeNode($member->type);
                }
                
                foreach ($member->items->getChildren() as $property) {
                    $properties[] = new PropertyInfo(
                        name: $property->name?->text ?? '(anonymous)',
                        type: $propertyType,
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
    private function extractConstantsFromClass(ClassNode $classNode): array
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
     * Extract type string from a type node.
     *
     * @param mixed $typeNode The type AST node
     * @return string|null The type as a string, or null if not extractable
     */
    private function extractTypeFromTypeNode($typeNode): ?string
    {
        if ($typeNode === null) {
            return null;
        }

        // Handle NamedTypeNode
        if (method_exists($typeNode, 'name') && property_exists($typeNode, 'name') && $typeNode->name !== null) {
            return $typeNode->name->text ?? null;
        }

        // Handle NameNode
        if (property_exists($typeNode, 'text')) {
            return $typeNode->text ?? null;
        }

        // Handle NullableTypeNode
        if (property_exists($typeNode, 'type') && property_exists($typeNode, 'question')) {
            $innerType = $this->extractTypeFromTypeNode($typeNode->type);
            return $innerType !== null ? '?' . $innerType : null;
        }

        // Handle UnionTypeNode
        if (property_exists($typeNode, 'types') && method_exists($typeNode->types, 'getItems')) {
            $types = [];
            foreach ($typeNode->types->getItems() as $unionType) {
                $type = $this->extractTypeFromTypeNode($unionType);
                if ($type !== null) {
                    $types[] = $type;
                }
            }
            return !empty($types) ? implode('|', $types) : null;
        }

        // Handle IntersectionTypeNode
        if (property_exists($typeNode, 'types') && method_exists($typeNode->types, 'getItems')) {
            $types = [];
            foreach ($typeNode->types->getItems() as $intersectionType) {
                $type = $this->extractTypeFromTypeNode($intersectionType);
                if ($type !== null) {
                    $types[] = $type;
                }
            }
            return !empty($types) ? implode('&', $types) : null;
        }

        return null;
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
