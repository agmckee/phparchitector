<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Pattern;

/**
 * Analyzes individual classes for patterns and anti-patterns.
 *
 * This class acts as an orchestrator that uses:
 * - ClassInfoExtractor: Extract class information from AST
 * - PatternDetector: Detect design patterns
 * - AntiPatternDetector: Detect anti-patterns
 *
 * Responsibilities:
 * - Analyze a single class for patterns and anti-patterns
 */
final class ClassPatternAnalyzer
{
    private ClassInfoExtractor $extractor;
    private DesignPatternDetector $patternDetector;
    private AntiPatternDetector $antiPatternDetector;

    public function __construct(
        ClassInfoExtractor $extractor = null,
        DesignPatternDetector $patternDetector = null,
        AntiPatternDetector $antiPatternDetector = null
    ) {
        $this->extractor = $extractor ?? new ClassInfoExtractor();
        $this->patternDetector = $patternDetector ?? new DesignPatternDetector();
        $this->antiPatternDetector = $antiPatternDetector ?? new AntiPatternDetector();
    }

    /**
     * Set custom pattern configuration
     */
    public function setPatternConfig(array $config): void
    {
        $this->patternDetector->setPatternConfig($config);
        $this->antiPatternDetector->setConfig($config);
    }

    /**
     * Get pattern configuration
     *
     * @return array<string, array> Pattern definitions with thresholds
     */
    public function getPatternConfig(): array
    {
        return array_merge(
            $this->patternDetector->getPatternConfig(),
            $this->antiPatternDetector->getConfig()
        );
    }

    /**
     * Analyze a single class for patterns and anti-patterns
     *
     * @param \PhpSyntax\Nodes\Statement\ClassNode $classNode The class AST node
     * @param string $namespace The namespace of the class
     * @param string $filePath The file path (for context)
     * @return array<string, array> Findings with 'patterns' and 'anti_patterns'
     */
    public function analyzeClass(\PhpSyntax\Nodes\Statement\ClassNode $classNode, string $namespace, string $filePath = ''): array
    {
        $findings = [
            'patterns' => [],
            'anti_patterns' => [],
        ];

        // Extract class information
        $classInfo = $this->extractor->extract($classNode, $namespace, $filePath);

        // Detect patterns
        $findings['patterns'] = $this->patternDetector->detect($classInfo);

        // Detect anti-patterns
        $findings['anti_patterns'] = $this->antiPatternDetector->detect($classInfo);

        return $findings;
    }
}
