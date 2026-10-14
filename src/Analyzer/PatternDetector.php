<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer;

use PhpArchitector\Analyzer\Pattern\ClassPatternAnalyzer;
use PhpArchitector\Analyzer\Pattern\DirectoryPatternAnalyzer;
use PhpArchitector\Analyzer\Pattern\LayerViolationDetector;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\ClassNode;

/**
 * PatternDetector identifies architectural patterns and anti-patterns in PHP code.
 *
 * This class acts as an orchestrator that uses specialized classes:
 * - ClassPatternAnalyzer: Analyze individual classes for patterns
 * - DirectoryPatternAnalyzer: Analyze directory-level patterns
 * - LayerViolationDetector: Detect layer violations
 *
 * Patterns detected:
 * - Layered architecture (Controllers -> Services -> Repositories)
 * - Dependency Injection (Constructor injection)
 * - Service Locator pattern
 * - Repository pattern
 * - Factory pattern
 * - Singleton pattern
 *
 * Anti-patterns detected:
 * - God Classes (too many methods/properties)
 * - Circular dependencies (using DependencyAnalyzer)
 * - Tight coupling (direct instantiation)
 * - Static method overuse
 * - Layer violations
 */
class PatternDetector extends AbstractAnalyzer
{
    private DependencyAnalyzer $dependencyAnalyzer;
    private DirectoryPatternAnalyzer $directoryPatternAnalyzer;

    /**
     * @param DependencyAnalyzer|null $dependencyAnalyzer Dependency analyzer instance
     * @param ClassPatternAnalyzer|null $classPatternAnalyzer Class pattern analyzer instance
     * @param DirectoryPatternAnalyzer|null $directoryPatternAnalyzer Directory pattern analyzer instance
     * @param LayerViolationDetector|null $layerViolationDetector Layer violation detector instance
     */
    public function __construct(
        ?DependencyAnalyzer $dependencyAnalyzer = null,
        ?ClassPatternAnalyzer $classPatternAnalyzer = null,
        ?DirectoryPatternAnalyzer $directoryPatternAnalyzer = null,
        ?LayerViolationDetector $layerViolationDetector = null
    ) {
        parent::__construct();
        $this->dependencyAnalyzer = $dependencyAnalyzer ?? new DependencyAnalyzer();

        // Create directory pattern analyzer with shared dependencies
        $this->directoryPatternAnalyzer = $directoryPatternAnalyzer ?? new DirectoryPatternAnalyzer(
            $classPatternAnalyzer ?? new ClassPatternAnalyzer(),
            $layerViolationDetector ?? new LayerViolationDetector()
        );
    }

    /**
     * Set custom pattern configuration
     *
     * @param array<string, array> $config Pattern configuration
     */
    public function setPatternConfig(array $config): void
    {
        $this->directoryPatternAnalyzer->setPatternConfig($config);
    }

    /**
     * Analyze a directory for patterns and anti-patterns
     *
     * @param string $directory Path to the directory to analyze
     * @return array<string, mixed> Pattern detection results
     */
    public function analyzeDirectory(string $directory): array
    {
        // First, analyze all files for class-level patterns
        $findings = $this->directoryPatternAnalyzer->analyzeDirectory($directory);

        // Then, detect directory-level patterns (layer violations, circular dependencies)
        $directoryFindings = $this->detectDirectoryLevelPatterns($directory);

        $findings['patterns'] = array_merge($findings['patterns'], $directoryFindings['patterns']);
        $findings['anti_patterns'] = array_merge($findings['anti_patterns'], $directoryFindings['anti_patterns']);

        return $findings;
    }

    /**
     * Analyze a single file
     *
     * @param FileNode $fileNode The parsed file AST
     * @param string $filePath Path to the file (optional, for context)
     * @return array<string, array> Pattern detection results
     */
    public function analyzeFile(FileNode $fileNode, string $filePath = ''): array
    {
        return $this->directoryPatternAnalyzer->analyzeFile($fileNode, $filePath);
    }

    /**
     * Analyze a single class for patterns and anti-patterns
     *
     * @param ClassNode $classNode The class AST node
     * @param string $namespace The namespace of the class
     * @param string $filePath The file path (optional, for context)
     * @return array<string, array> Pattern detection results
     */
    public function analyzeClass(ClassNode $classNode, string $namespace, string $filePath = ''): array
    {
        // Create a temporary class pattern analyzer for single class analysis
        $classAnalyzer = new ClassPatternAnalyzer();
        return $classAnalyzer->analyzeClass($classNode, $namespace, $filePath);
    }

    /**
     * Detect patterns at directory level (layer violations, circular dependencies)
     *
     * @param string $directory Path to the directory to analyze
     * @return array<string, array> Directory-level findings
     */
    private function detectDirectoryLevelPatterns(string $directory): array
    {
        $findings = [
            'patterns' => [],
            'anti_patterns' => [],
        ];

        // Build dependency graph
        $graph = $this->dependencyAnalyzer->analyzeDirectory($directory);

        // Detect layer violations
        $config = $this->directoryPatternAnalyzer->getPatternConfig();
        $layerViolations = $this->directoryPatternAnalyzer->detectDirectoryLevelPatterns($graph, $config);

        $findings['patterns'] = array_merge($findings['patterns'], $layerViolations['patterns']);
        $findings['anti_patterns'] = array_merge($findings['anti_patterns'], $layerViolations['anti_patterns']);

        // Detect circular dependencies
        $circularDeps = $this->dependencyAnalyzer->findCircularDependencies($directory);
        foreach ($circularDeps as $cycle) {
            $findings['anti_patterns'][] = [
                'type' => 'circular_dependency',
                'classes' => $cycle,
                'message' => sprintf(
                    'Circular dependency detected: %s',
                    implode(' -> ', $cycle)
                ),
                'severity' => 'high',
                'data' => [
                    'cycle' => $cycle,
                ],
            ];
        }

        return $findings;
    }

    /**
     * Get pattern configuration
     *
     * @return array<string, array> Pattern definitions with thresholds
     */
    public function getPatternConfig(): array
    {
        return $this->directoryPatternAnalyzer->getPatternConfig();
    }
}
