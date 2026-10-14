<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Pattern;

/**
 * Detects anti-patterns in class information.
 *
 * Responsibilities:
 * - Detect God Class anti-pattern
 * - Detect Tight Coupling anti-pattern
 * - Detect Static Overuse anti-pattern
 */
final class AntiPatternDetector
{
    /** @var array<string, array> Anti-pattern configuration */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'god_class' => [
                'method_threshold' => 20,
                'property_threshold' => 15,
                'line_threshold' => 500,
                'severity' => 'high',
            ],
            'tight_coupling' => [
                'direct_instantiation_threshold' => 3,
                'severity' => 'medium',
            ],
            'static_overuse' => [
                'static_method_threshold' => 5,
                'static_property_threshold' => 3,
                'severity' => 'medium',
            ],
        ], $config);
    }

    /**
     * Set custom configuration
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get configuration
     *
     * @return array<string, array> Configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Detect anti-patterns in a class
     *
     * @param array<string, mixed> $classInfo Class information
     * @return array<int, array<string, mixed>> Detected anti-patterns
     */
    public function detect(array $classInfo): array
    {
        $antiPatterns = [];

        // 1. God Class anti-pattern
        $methodThreshold = $this->config['god_class']['method_threshold'];
        $propertyThreshold = $this->config['god_class']['property_threshold'];
        $lineThreshold = $this->config['god_class']['line_threshold'];

        $methodCount = count($classInfo['methods']);
        $propertyCount = count($classInfo['properties']);
        $loc = $classInfo['lines_of_code'];

        if ($methodCount >= $methodThreshold ||
            $propertyCount >= $propertyThreshold ||
            $loc >= $lineThreshold) {

            $reasons = [];
            if ($methodCount >= $methodThreshold) {
                $reasons[] = sprintf('%d methods (threshold: %d)', $methodCount, $methodThreshold);
            }
            if ($propertyCount >= $propertyThreshold) {
                $reasons[] = sprintf('%d properties (threshold: %d)', $propertyCount, $propertyThreshold);
            }
            if ($loc >= $lineThreshold) {
                $reasons[] = sprintf('%d lines of code (threshold: %d)', $loc, $lineThreshold);
            }

            $antiPatterns[] = [
                'type' => 'god_class',
                'class' => $classInfo['fqcn'],
                'message' => sprintf(
                    'Class %s may be a God Class: %s',
                    $classInfo['fqcn'],
                    implode(', ', $reasons)
                ),
                'severity' => $this->config['god_class']['severity'] ?? 'high',
                'data' => [
                    'method_count' => $methodCount,
                    'property_count' => $propertyCount,
                    'lines_of_code' => $loc,
                    'thresholds' => [
                        'methods' => $methodThreshold,
                        'properties' => $propertyThreshold,
                        'lines' => $lineThreshold,
                    ],
                ],
            ];
        }

        // 2. Tight Coupling anti-pattern (direct instantiation)
        $instantiationThreshold = $this->config['tight_coupling']['direct_instantiation_threshold'];

        if ($classInfo['direct_instantiations'] >= $instantiationThreshold) {
            $antiPatterns[] = [
                'type' => 'tight_coupling',
                'class' => $classInfo['fqcn'],
                'message' => sprintf(
                    'Class %s has tight coupling with %d direct instantiations',
                    $classInfo['fqcn'],
                    $classInfo['direct_instantiations']
                ),
                'severity' => $this->config['tight_coupling']['severity'] ?? 'medium',
                'data' => [
                    'direct_instantiations' => $classInfo['direct_instantiations'],
                    'threshold' => $instantiationThreshold,
                ],
            ];
        }

        // 3. Static Method Overuse anti-pattern
        $staticMethodThreshold = $this->config['static_overuse']['static_method_threshold'];
        $staticPropertyThreshold = $this->config['static_overuse']['static_property_threshold'];

        if ($classInfo['static_methods'] >= $staticMethodThreshold ||
            $classInfo['static_properties'] >= $staticPropertyThreshold) {

            $reasons = [];
            if ($classInfo['static_methods'] >= $staticMethodThreshold) {
                $reasons[] = sprintf('%d static methods (threshold: %d)', $classInfo['static_methods'], $staticMethodThreshold);
            }
            if ($classInfo['static_properties'] >= $staticPropertyThreshold) {
                $reasons[] = sprintf('%d static properties (threshold: %d)', $classInfo['static_properties'], $staticPropertyThreshold);
            }

            $antiPatterns[] = [
                'type' => 'static_overuse',
                'class' => $classInfo['fqcn'],
                'message' => sprintf(
                    'Class %s overuses static members: %s',
                    $classInfo['fqcn'],
                    implode(', ', $reasons)
                ),
                'severity' => $this->config['static_overuse']['severity'] ?? 'medium',
                'data' => [
                    'static_methods' => $classInfo['static_methods'],
                    'static_properties' => $classInfo['static_properties'],
                ],
            ];
        }

        return $antiPatterns;
    }
}
