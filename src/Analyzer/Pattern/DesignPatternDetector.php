<?php declare(strict_types=1);

namespace PhpArchitector\Analyzer\Pattern;

/**
 * Detects design patterns in class information.
 *
 * Responsibilities:
 * - Detect Dependency Injection pattern
 * - Detect Repository pattern
 * - Detect Factory pattern
 * - Detect Singleton pattern
 */
final class DesignPatternDetector
{
    /** @var array<string, array> Pattern configuration */
    private array $patternConfig;

    public function __construct(array $patternConfig = [])
    {
        $this->patternConfig = array_merge([
            'dependency_injection' => [
                'min_injected_dependencies' => 1,
                'severity' => 'low',
            ],
            'repository_pattern' => [
                'naming_pattern' => '/Repository/i',
                'method_pattern' => ['find', 'save', 'delete', 'create', 'update'],
                'severity' => 'low',
            ],
            'factory_pattern' => [
                'naming_pattern' => '/Factory/i',
                'method_pattern' => ['create', 'make', 'build', 'factory'],
                'severity' => 'low',
            ],
            'singleton_pattern' => [
                'method_pattern' => ['getInstance', 'instance'],
                'property_pattern' => ['instance'],
                'severity' => 'low',
            ],
        ], $patternConfig);
    }

    /**
     * Set custom pattern configuration
     */
    public function setPatternConfig(array $config): void
    {
        $this->patternConfig = array_merge($this->patternConfig, $config);
    }

    /**
     * Get pattern configuration
     *
     * @return array<string, array> Pattern definitions with thresholds
     */
    public function getPatternConfig(): array
    {
        return $this->patternConfig;
    }

    /**
     * Detect patterns in a class
     *
     * @param array<string, mixed> $classInfo Class information
     * @return array<int, array<string, mixed>> Detected patterns
     */
    public function detect(array $classInfo): array
    {
        $patterns = [];

        // 1. Dependency Injection pattern
        if ($classInfo['injected_dependencies'] >= ($this->patternConfig['dependency_injection']['min_injected_dependencies'] ?? 1)) {
            $patterns[] = [
                'type' => 'dependency_injection',
                'class' => $classInfo['fqcn'],
                'message' => sprintf(
                    'Class %s uses constructor injection with %d type-hinted parameters',
                    $classInfo['fqcn'],
                    $classInfo['injected_dependencies']
                ),
                'severity' => $this->patternConfig['dependency_injection']['severity'] ?? 'low',
                'data' => [
                    'injected_dependencies' => $classInfo['injected_dependencies'],
                    'constructor_params' => $classInfo['constructor_params'],
                ],
            ];
        }

        // 2. Repository pattern
        if (preg_match($this->patternConfig['repository_pattern']['naming_pattern'], $classInfo['name'])) {
            $hasRepositoryMethods = $this->hasMethodsMatchingPattern(
                $classInfo['methods'],
                $this->patternConfig['repository_pattern']['method_pattern']
            );

            if ($hasRepositoryMethods) {
                $patterns[] = [
                    'type' => 'repository_pattern',
                    'class' => $classInfo['fqcn'],
                    'message' => sprintf('Class %s appears to implement Repository pattern', $classInfo['fqcn']),
                    'severity' => $this->patternConfig['repository_pattern']['severity'] ?? 'low',
                    'data' => [
                        'methods' => array_column($classInfo['methods'], 'name'),
                    ],
                ];
            }
        }

        // 3. Factory pattern
        if (preg_match($this->patternConfig['factory_pattern']['naming_pattern'], $classInfo['name'])) {
            $hasFactoryMethods = $this->hasMethodsMatchingPattern(
                $classInfo['methods'],
                $this->patternConfig['factory_pattern']['method_pattern']
            );

            if ($hasFactoryMethods) {
                $patterns[] = [
                    'type' => 'factory_pattern',
                    'class' => $classInfo['fqcn'],
                    'message' => sprintf('Class %s appears to implement Factory pattern', $classInfo['fqcn']),
                    'severity' => $this->patternConfig['factory_pattern']['severity'] ?? 'low',
                    'data' => [
                        'methods' => array_column($classInfo['methods'], 'name'),
                    ],
                ];
            }
        }

        // 4. Singleton pattern
        $hasGetInstance = false;
        $hasStaticInstanceProperty = false;

        foreach ($classInfo['methods'] as $method) {
            if (in_array(strtolower($method['name']), ['getinstance', 'instance'], true) && $method['is_static']) {
                $hasGetInstance = true;
            }
        }

        foreach ($classInfo['properties'] as $property) {
            if (strtolower($property['name']) === 'instance' && $property['is_static']) {
                $hasStaticInstanceProperty = true;
            }
        }

        if ($hasGetInstance && $hasStaticInstanceProperty) {
            $patterns[] = [
                'type' => 'singleton_pattern',
                'class' => $classInfo['fqcn'],
                'message' => sprintf('Class %s appears to implement Singleton pattern', $classInfo['fqcn']),
                'severity' => $this->patternConfig['singleton_pattern']['severity'] ?? 'low',
                'data' => [
                    'has_getinstance' => true,
                    'has_static_instance' => true,
                ],
            ];
        }

        return $patterns;
    }

    /**
     * Check if class has methods matching any of the given patterns
     *
     * @param array<int, array<string, mixed>> $methods List of methods
     * @param array<int, string> $patterns List of method name patterns to match
     * @return bool True if any method matches
     */
    private function hasMethodsMatchingPattern(array $methods, array $patterns): bool
    {
        $patternMap = array_map('strtolower', $patterns);

        foreach ($methods as $method) {
            if (in_array(strtolower($method['name']), $patternMap, true)) {
                return true;
            }
        }

        return false;
    }
}
