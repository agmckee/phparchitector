<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Pattern;

use PhpArchitector\Util\FileSystemUtils;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;

/**
 * Analyzes directories for patterns and anti-patterns.
 *
 * Responsibilities:
 * - Analyze all PHP files in a directory
 * - Analyze individual files
 * - Detect directory-level patterns (circular dependencies, layer violations)
 */
final class DirectoryPatternAnalyzer
{
    private ClassPatternAnalyzer $classPatternAnalyzer;
    private LayerViolationDetector $layerViolationDetector;

    public function __construct(
        ClassPatternAnalyzer $classPatternAnalyzer = null,
        LayerViolationDetector $layerViolationDetector = null
    ) {
        $this->classPatternAnalyzer = $classPatternAnalyzer ?? new ClassPatternAnalyzer();
        $this->layerViolationDetector = $layerViolationDetector ?? new LayerViolationDetector();
    }

    /**
     * Set custom pattern configuration on the class analyzer
     */
    public function setPatternConfig(array $config): void
    {
        $this->classPatternAnalyzer->setPatternConfig($config);
    }

    /**
     * Get pattern configuration from the class analyzer
     *
     * @return array<string, array> Pattern definitions with thresholds
     */
    public function getPatternConfig(): array
    {
        return $this->classPatternAnalyzer->getPatternConfig();
    }

    /**
     * Analyze a directory for patterns and anti-patterns
     *
     * @param string $directory Path to the directory to analyze
     * @return array<string, mixed> Pattern detection results
     */
    public function analyzeDirectory(string $directory): array
    {
        $files = FileSystemUtils::findPhpFiles($directory);
        $findings = [
            'patterns' => [],
            'anti_patterns' => [],
            'files_analyzed' => count($files),
        ];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            $fileNode = (new \PhpSyntax\Parser())->parse($source);

            $fileFindings = $this->analyzeFile($fileNode, $file);

            $findings['patterns'] = array_merge($findings['patterns'], $fileFindings['patterns']);
            $findings['anti_patterns'] = array_merge($findings['anti_patterns'], $fileFindings['anti_patterns']);
        }

        return $findings;
    }

    /**
     * Analyze a single file
     *
     * @param FileNode $fileNode The parsed file AST
     * @param string $filePath Path to the file (for context)
     * @return array<string, array> Pattern detection results
     */
    public function analyzeFile(FileNode $fileNode, string $filePath = ''): array
    {
        $findings = [
            'patterns' => [],
            'anti_patterns' => [],
        ];

        $currentNamespace = null;

        foreach ($fileNode->statements->getChildren() as $stmt) {
            if ($stmt instanceof NamespaceNode) {
                $currentNamespace = $stmt->name->text;

                foreach ($stmt->statements->getChildren() as $namespaceStmt) {
                    if ($namespaceStmt instanceof ClassNode) {
                        $classFindings = $this->classPatternAnalyzer->analyzeClass(
                            $namespaceStmt,
                            $currentNamespace,
                            $filePath
                        );
                        $findings['patterns'] = array_merge($findings['patterns'], $classFindings['patterns']);
                        $findings['anti_patterns'] = array_merge($findings['anti_patterns'], $classFindings['anti_patterns']);
                    }
                }
            } elseif ($stmt instanceof ClassNode) {
                // Top-level class (no namespace)
                $classFindings = $this->classPatternAnalyzer->analyzeClass($stmt, '', $filePath);
                $findings['patterns'] = array_merge($findings['patterns'], $classFindings['patterns']);
                $findings['anti_patterns'] = array_merge($findings['anti_patterns'], $classFindings['anti_patterns']);
            }
        }

        return $findings;
    }

    /**
     * Detect patterns at directory level (layer violations, circular dependencies)
     *
     * This method is called separately to allow for dependency analysis injection.
     *
     * @param array<string, mixed> $graph The dependency graph
     * @param array<string, array> $patternConfig Pattern configuration
     * @return array<string, array> Directory-level findings
     */
    public function detectDirectoryLevelPatterns(array $graph, array $patternConfig = []): array
    {
        $findings = [
            'patterns' => [],
            'anti_patterns' => [],
        ];

        // Detect layer violations
        $layerViolations = $this->layerViolationDetector->detectLayerViolations($graph);
        foreach ($layerViolations as $violation) {
            $findings['anti_patterns'][] = [
                'type' => 'layer_violation',
                'class' => $violation['source'],
                'message' => sprintf(
                    'Layer violation: %s (in %s) depends on %s (in %s)',
                    $violation['source'],
                    $violation['source_layer'],
                    $violation['target'],
                    $violation['target_layer']
                ),
                'severity' => $patternConfig['layer_violation']['severity'] ?? 'high',
                'data' => $violation,
            ];
        }

        return $findings;
    }
}
