<?php declare(strict_types=1);

/**
 * Report Generator
 * 
 * This script generates human-readable markdown reports from the JSON analysis data.
 * It validates the results against the architectural rules defined in validation-rules.php.
 */

// Set up error handling
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Parse command line arguments for overrides
$options = getopt('', ['output:', 'help', 'version']);
if (isset($options['help'])) {
    echo "Usage: php generate-report.php [OPTIONS]\n";
    echo "\nOptions:\n";
    echo "  --output=PATH    Output directory for reports\n";
    echo "  --help          Show this help message\n";
    echo "  --version       Show version\n";
    exit(0);
}

if (isset($options['version'])) {
    echo "Report Generator version 1.0.0\n";
    exit(0);
}

// Load configuration
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die("Error: Configuration file not found at {$configPath}\n");
}

$config = require $configPath;

// Override config from command line
if (isset($options['output'])) {
    $config['output_dir'] = $options['output'];
}

// Load validation rules
$rulesPath = __DIR__ . '/validation-rules.php';
if (!file_exists($rulesPath)) {
    die("Error: Validation rules file not found at {$rulesPath}\n");
}

$validationRules = require $rulesPath;

// Ensure output directory exists
$outputDir = $config['output_dir'];
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        die("Error: Failed to create output directory {$outputDir}\n");
    }
}

// Log function
function logMessage(string $message, int $verbosity, int $configVerbosity): void
{
    if ($verbosity <= $configVerbosity) {
        echo date('[Y-m-d H:i:s]') . ' ' . $message . "\n";
    }
}

/**
 * Load analysis results from JSON files
 */
function loadAnalysisResults(array $config): array
{
    $outputDir = $config['output_dir'];
    $results = [
        'classes' => [],
        'dependencies' => [],
        'patterns' => [],
        'summary' => [],
    ];
    
    // Try to load each analysis file
    $files = [
        'raw_classanalyzer' => 'classes',
        'raw_dependencyanalyzer' => 'dependencies',
        'raw_patterndetector' => 'patterns',
        'analysis_summary' => 'summary',
    ];
    
    foreach ($files as $filename => $key) {
        $filePath = $outputDir . '/' . $filename . '.json';
        if (file_exists($filePath)) {
            try {
                $content = file_get_contents($filePath);
                $results[$key] = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                echo "Warning: Failed to parse {$filename}.json: " . $e->getMessage() . "\n";
                $results[$key] = [];
            }
        }
    }
    
    return $results;
}

/**
 * Normalize class analysis data to a consistent format
 */
function normalizeClassData(array $rawClasses): array
{
    $classes = [];
    
    // Handle different formats
    if (isset($rawClasses['classes'])) {
        // Format: ['classes' => [...], 'files_analyzed' => N]
        foreach ($rawClasses['classes'] as $classInfo) {
            if (is_array($classInfo)) {
                $classes[] = $classInfo;
            }
        }
    } elseif (is_array($rawClasses)) {
        // Format: [filename => classInfo, ...]
        foreach ($rawClasses as $filePath => $classInfo) {
            if (is_array($classInfo) && !isset($classInfo['error'])) {
                // Add file path if not present
                if (!isset($classInfo['filePath'])) {
                    $classInfo['filePath'] = $filePath;
                }
                $classes[] = $classInfo;
            }
        }
    }
    
    return $classes;
}

/**
 * Normalize dependency data to a consistent format
 */
function normalizeDependencyData(array $rawDependencies): array
{
    // Handle different formats
    if (isset($rawDependencies['nodes']) && isset($rawDependencies['edges'])) {
        // Already in graph format
        return $rawDependencies;
    }
    
    // Try to convert other formats
    $graph = ['nodes' => [], 'edges' => []];
    
    if (is_array($rawDependencies)) {
        foreach ($rawDependencies as $source => $deps) {
            if (is_array($deps) && isset($deps['fqcn'])) {
                $sourceFqcn = $deps['fqcn'] ?? $source;
                
                // Add source node if not exists
                $sourceExists = false;
                foreach ($graph['nodes'] as $node) {
                    if ($node['fqcn'] === $sourceFqcn) {
                        $sourceExists = true;
                        break;
                    }
                }
                
                if (!$sourceExists) {
                    $graph['nodes'][] = [
                        'id' => count($graph['nodes']),
                        'fqcn' => $sourceFqcn,
                        'type' => $deps['type'] ?? 'class',
                        'namespace' => $deps['namespace'] ?? '',
                        'name' => $deps['name'] ?? '',
                    ];
                }
                
                // Add dependencies
                foreach ($deps['dependencies'] ?? [] as $dep) {
                    $targetFqcn = $dep['fqcn'] ?? $dep;
                    
                    // Add target node if not exists
                    $targetExists = false;
                    foreach ($graph['nodes'] as $node) {
                        if ($node['fqcn'] === $targetFqcn) {
                            $targetExists = true;
                            break;
                        }
                    }
                    
                    if (!$targetExists) {
                        $graph['nodes'][] = [
                            'id' => count($graph['nodes']),
                            'fqcn' => $targetFqcn,
                            'type' => 'external',
                            'namespace' => '',
                            'name' => $targetFqcn,
                        ];
                    }
                }
            }
        }
    }
    
    return $graph;
}

/**
 * Normalize pattern data to a consistent format
 */
function normalizePatternData(array $rawPatterns): array
{
    $patterns = [
        'patterns' => [],
        'anti_patterns' => [],
        'layer_violations' => [],
    ];
    
    if (isset($rawPatterns['patterns'])) {
        $patterns['patterns'] = $rawPatterns['patterns'];
    }
    
    if (isset($rawPatterns['anti_patterns'])) {
        $patterns['anti_patterns'] = $rawPatterns['anti_patterns'];
    }
    
    if (isset($rawPatterns['layer_violations'])) {
        $patterns['layer_violations'] = $rawPatterns['layer_violations'];
    }
    
    // Handle flat array format
    if (is_array($rawPatterns) && !isset($rawPatterns['patterns'])) {
        foreach ($rawPatterns as $filePath => $filePatterns) {
            if (is_array($filePatterns)) {
                foreach ($filePatterns as $pattern) {
                    if (isset($pattern['type']) && isset($pattern['severity'])) {
                        if (strpos($pattern['severity'], 'violation') !== false) {
                            $patterns['layer_violations'][] = $pattern;
                        } elseif (strpos($pattern['type'], 'Anti') !== false || $pattern['severity'] === 'anti-pattern') {
                            $patterns['anti_patterns'][] = $pattern;
                        } else {
                            $patterns['patterns'][] = $pattern;
                        }
                    }
                }
            }
        }
    }
    
    return $patterns;
}

/**
 * Calculate overall score based on analysis results
 */
function calculateOverallScore(
    array $classes,
    array $dependencies,
    array $patterns,
    array $validationRules
): array {
    $score = 'A+';
    $reasons = [];
    $totalDeductions = 0;
    
    // Check file sizes
    $maxLines = 0;
    $overLimit = 0;
    foreach ($classes as $class) {
        $lines = $class['lines_of_code'] ?? $class['linesOfCode'] ?? 0;
        $maxLines = max($maxLines, $lines);
        
        if ($lines > ($validationRules['file_size']['max_lines'] ?? 1000)) {
            $overLimit++;
        }
    }
    
    if ($overLimit > 0) {
        $totalDeductions += 2;
        $reasons[] = "{$overLimit} file(s) exceed max line limit ({$validationRules['file_size']['max_lines']})";
    }
    
    // Check circular dependencies
    $circularCount = count(findCircularDependencies($dependencies));
    if ($circularCount > 0) {
        $totalDeductions += 3;
        $reasons[] = "{$circularCount} circular dependency(ies) found";
    }
    
    // Check layer violations
    $layerViolations = count($patterns['layer_violations'] ?? []);
    if ($layerViolations > 0) {
        $totalDeductions += 2;
        $reasons[] = "{$layerViolations} layer violation(s) found";
    }
    
    // Check anti-patterns
    $antiPatterns = count($patterns['anti_patterns'] ?? []);
    if ($antiPatterns > 0) {
        $totalDeductions += min($antiPatterns, 2); // Max 2 point deduction
        $reasons[] = "{$antiPatterns} anti-pattern(s) detected";
    }
    
    // Calculate score
    if ($totalDeductions === 0) {
        $score = 'A+';
    } elseif ($totalDeductions <= 1) {
        $score = 'A';
    } elseif ($totalDeductions <= 2) {
        $score = 'A-';
    } elseif ($totalDeductions <= 3) {
        $score = 'B+';
    } elseif ($totalDeductions <= 4) {
        $score = 'B';
    } elseif ($totalDeductions <= 5) {
        $score = 'B-';
    } else {
        $score = 'C+';
    }
    
    return [
        'score' => $score,
        'deductions' => $totalDeductions,
        'reasons' => $reasons,
    ];
}

/**
 * Find circular dependencies in a graph (simplified implementation)
 */
function findCircularDependencies(array $graph): array
{
    $circular = [];
    
    if (empty($graph['nodes']) || empty($graph['edges'])) {
        return $circular;
    }
    
    // Build adjacency list
    $adjacency = [];
    foreach ($graph['nodes'] as $node) {
        $adjacency[$node['fqcn']] = [];
    }
    
    foreach ($graph['edges'] as $edge) {
        if (isset($graph['nodes'][$edge['source']])) {
            $sourceFqcn = $graph['nodes'][$edge['source']]['fqcn'];
            if (isset($graph['nodes'][$edge['target']])) {
                $targetFqcn = $graph['nodes'][$edge['target']]['fqcn'];
                $adjacency[$sourceFqcn][] = $targetFqcn;
            }
        }
    }
    
    // Detect cycles using DFS
    $visited = [];
    $recStack = [];
    $cycles = [];
    
    foreach (array_keys($adjacency) as $node) {
        if (!isset($visited[$node])) {
            detectCycles($node, $adjacency, $visited, $recStack, $cycles);
        }
    }
    
    return $cycles;
}

/**
 * DFS-based cycle detection (helper function for findCircularDependencies)
 */
function detectCycles(
    string $node,
    array &$adjacency,
    array &$visited,
    array &$recStack,
    array &$cycles
): bool {
    $visited[$node] = true;
    $recStack[$node] = true;
    
    foreach ($adjacency[$node] as $neighbor) {
        if (!isset($visited[$neighbor])) {
            if (detectCycles($neighbor, $adjacency, $visited, $recStack, $cycles)) {
                return true;
            }
        } elseif (isset($recStack[$neighbor])) {
            // Cycle detected
            $cycle = [$neighbor, $node];
            $cycles[] = $cycle;
            return true;
        }
    }
    
    $recStack[$node] = false;
    return false;
}

/**
 * Validate architecture against rules
 */
function validateArchitecture(
    array $classes,
    array $dependencies,
    array $patterns,
    array $validationRules
): array {
    $validationResults = [
        'passed' => [],
        'failed' => [],
        'warnings' => [],
    ];
    
    // Check file sizes
    $maxLines = 0;
    $overWarning = 0;
    $overLimit = 0;
    
    foreach ($classes as $class) {
        $lines = $class['lines_of_code'] ?? $class['linesOfCode'] ?? 0;
        $maxLines = max($maxLines, $lines);
        
        if ($lines > ($validationRules['file_size']['warning_threshold'] ?? 500)) {
            $overWarning++;
            $className = $class['fqcn'] ?? $class['class_name'] ?? $class['name'] ?? 'Unknown';
            $validationResults['warnings'][] = "File size warning: {$className} has {$lines} lines (threshold: {$validationRules['file_size']['warning_threshold']})";
        }
        
        if ($lines > ($validationRules['file_size']['max_lines'] ?? 1000)) {
            $overLimit++;
            $className = $class['fqcn'] ?? $class['class_name'] ?? $class['name'] ?? 'Unknown';
            $validationResults['failed'][] = "File size limit exceeded: {$className} has {$lines} lines (max: {$validationRules['file_size']['max_lines']})";
        }
    }
    
    if ($overLimit === 0) {
        $validationResults['passed'][] = "All files under max line limit ({$validationRules['file_size']['max_lines']})";
    }
    
    // Check circular dependencies
    $circular = findCircularDependencies($dependencies);
    if (empty($circular)) {
        $validationResults['passed'][] = "No circular dependencies detected";
    } else {
        foreach ($circular as $cycle) {
            $validationResults['failed'][] = "Circular dependency: " . implode(' -> ', $cycle);
        }
    }
    
    // Check layer violations
    $layerViolations = $patterns['layer_violations'] ?? [];
    if (empty($layerViolations)) {
        $validationResults['passed'][] = "No layer violations detected";
    } else {
        foreach ($layerViolations as $violation) {
            $from = $violation['from'] ?? 'Unknown';
            $to = $violation['to'] ?? 'Unknown';
            $reason = $violation['reason'] ?? '';
            $validationResults['failed'][] = "Layer violation: {$from} -> {$to} ({$reason})";
        }
    }
    
    // Check anti-patterns
    $antiPatterns = $patterns['anti_patterns'] ?? [];
    if (empty($antiPatterns)) {
        $validationResults['passed'][] = "No anti-patterns detected";
    } else {
        foreach ($antiPatterns as $antiPattern) {
            $type = $antiPattern['type'] ?? 'Unknown';
            $severity = $antiPattern['severity'] ?? 'warning';
            $validationResults[$severity === 'critical' ? 'failed' : 'warnings'][] = "Anti-pattern detected: {$type}";
        }
    }
    
    // Check pattern requirements
    $requiredPatterns = $validationRules['required_patterns'] ?? [];
    foreach ($requiredPatterns as $patternName => $patternConfig) {
        // For now, just check if we have any patterns detected
        if (empty($patterns['patterns'])) {
            $validationResults['warnings'][] = "Required pattern not detected: {$patternName}";
        }
    }
    
    // Check layer rules
    $layerRules = $validationRules['layer_rules'] ?? [];
    if (isset($layerRules['forbidden_dependencies'])) {
        foreach ($layerRules['forbidden_dependencies'] as $rule) {
            $fromLayer = $rule['from'] ?? '';
            $toLayer = $rule['to'] ?? '';
            
            // Check if any dependency violates this rule
            $violations = [];
            foreach ($dependencies['edges'] ?? [] as $edge) {
                if (isset($dependencies['nodes'][$edge['source']])) {
                    $sourceNode = $dependencies['nodes'][$edge['source']];
                    $sourceFqcn = $sourceNode['fqcn'] ?? '';
                    
                    if (isset($dependencies['nodes'][$edge['target']])) {
                        $targetNode = $dependencies['nodes'][$edge['target']];
                        $targetFqcn = $targetNode['fqcn'] ?? '';
                        
                        // Extract layer from FQCN
                        $sourceLayer = extractLayerFromFqcn($sourceFqcn, $validationRules);
                        $targetLayer = extractLayerFromFqcn($targetFqcn, $validationRules);
                        
                        if ($sourceLayer === $fromLayer && $targetLayer === $toLayer) {
                            $violations[] = "{$sourceFqcn} -> {$targetFqcn}";
                        }
                    }
                }
            }
            
            if (empty($violations)) {
                $validationResults['passed'][] = "No forbidden dependencies: {$fromLayer} -> {$toLayer}";
            } else {
                foreach ($violations as $violation) {
                    $validationResults['failed'][] = "Forbidden dependency: {$violation} (violates {$fromLayer} -> {$toLayer} rule)";
                }
            }
        }
    }
    
    return $validationResults;
}

/**
 * Extract layer from FQCN based on validation rules
 */
function extractLayerFromFqcn(string $fqcn, array $validationRules): ?string
{
    if (empty($fqcn)) {
        return null;
    }
    
    $layers = $validationRules['layers'] ?? [];
    
    foreach ($layers as $layerName => $layerConfig) {
        $pattern = $layerConfig['namespace_pattern'] ?? '';
        
        if (!empty($pattern) && strpos($fqcn, $pattern) === 0) {
            return $layerName;
        }
    }
    
    return null;
}

/**
 * Generate summary report
 */
function generateSummaryReport(
    array $classes,
    array $dependencies,
    array $patterns,
    array $validationRules
): string {
    $scoreResult = calculateOverallScore($classes, $dependencies, $patterns, $validationRules);
    $validationResults = validateArchitecture($classes, $dependencies, $patterns, $validationRules);
    
    $report = "# Self-Analysis Report: lossless-analysis\n\n";
    $report .= "**Analysis Date:** " . date('Y-m-d H:i:s') . "\n\n";
    
    // Overall Score
    $report .= "## Overall Score: **{$scoreResult['score']}**\n\n";
    
    if (!empty($scoreResult['reasons'])) {
        $report .= "### Score Breakdown\n\n";
        foreach ($scoreResult['reasons'] as $reason) {
            $report .= "- ❌ {$reason}\n";
        }
        $report .= "\n";
    } else {
        $report .= "All checks passed! ✅\n\n";
    }
    
    // File Metrics
    $report .= "## File Metrics\n\n";
    $report .= "- Total classes analyzed: " . count($classes) . "\n";
    
    $totalLines = 0;
    foreach ($classes as $class) {
        $totalLines += $class['lines_of_code'] ?? $class['linesOfCode'] ?? 0;
    }
    $report .= "- Total lines of code: {$totalLines}\n";
    
    if (!empty($classes)) {
        $avgLines = $totalLines / count($classes);
        $report .= "- Average lines per class: " . round($avgLines, 1) . "\n";
    }
    $report .= "\n";
    
    // Dependency Metrics
    $report .= "## Dependency Metrics\n\n";
    $nodeCount = count($dependencies['nodes'] ?? []);
    $edgeCount = count($dependencies['edges'] ?? []);
    $report .= "- Total nodes: {$nodeCount}\n";
    $report .= "- Total dependencies (edges): {$edgeCount}\n";
    
    $circular = findCircularDependencies($dependencies);
    $report .= "- Circular dependencies: " . count($circular) . "\n\n";
    
    // Pattern Metrics
    $report .= "## Pattern Metrics\n\n";
    $patternCount = count($patterns['patterns'] ?? []);
    $antiPatternCount = count($patterns['anti_patterns'] ?? []);
    $layerViolationCount = count($patterns['layer_violations'] ?? []);
    
    $report .= "- Patterns detected: {$patternCount}\n";
    $report .= "- Anti-patterns detected: {$antiPatternCount}\n";
    $report .= "- Layer violations detected: {$layerViolationCount}\n\n";
    
    // Validation Results
    $report .= "## Validation Results\n\n";
    
    $passedCount = count($validationResults['passed']);
    $failedCount = count($validationResults['failed']);
    $warningCount = count($validationResults['warnings']);
    
    $report .= "- ✅ Passed: {$passedCount}\n";
    $report .= "- ❌ Failed: {$failedCount}\n";
    $report .= "- ⚠️  Warnings: {$warningCount}\n\n";
    
    if (!empty($validationResults['passed'])) {
        $report .= "### Passed Checks\n\n";
        foreach ($validationResults['passed'] as $check) {
            $report .= "- ✅ {$check}\n";
        }
        $report .= "\n";
    }
    
    if (!empty($validationResults['failed'])) {
        $report .= "### Failed Checks\n\n";
        foreach ($validationResults['failed'] as $check) {
            $report .= "- ❌ {$check}\n";
        }
        $report .= "\n";
    }
    
    if (!empty($validationResults['warnings'])) {
        $report .= "### Warnings\n\n";
        foreach ($validationResults['warnings'] as $warning) {
            $report .= "- ⚠️  {$warning}\n";
        }
        $report .= "\n";
    }
    
    // Conclusion
    $report .= "---\n\n";
    $report .= "## Conclusion\n\n";
    
    if ($failedCount === 0) {
        $report .= "✅ All architectural validations passed! The codebase adheres to the defined rules.\n\n";
    } else {
        $report .= "⚠️  Some validations failed. Please review the failed checks above.\n\n";
    }
    
    $report .= "Score: **{$scoreResult['score']}**\n";
    
    return $report;
}

/**
 * Generate architecture report
 */
function generateArchitectureReport(array $classes, array $dependencies, string $outputDir): string
{
    $report = "# Architecture Analysis\n\n";
    $report .= "**Analysis Date:** " . date('Y-m-d H:i:s') . "\n\n";
    
    // Layer structure
    $report .= "## Layer Structure\n\n";
    
    $layers = [
        'Util' => [],
        'Model' => [],
        'Analyzer' => [],
        'Other' => [],
    ];
    
    foreach ($classes as $class) {
        $fqcn = $class['fqcn'] ?? $class['namespace'] . '\\' . ($class['class_name'] ?? $class['name'] ?? 'Unknown');
        
        if (strpos($fqcn, 'PhpArchitector\\Util\\') === 0) {
            $layers['Util'][] = $class;
        } elseif (strpos($fqcn, 'PhpArchitector\\Model\\') === 0) {
            $layers['Model'][] = $class;
        } elseif (strpos($fqcn, 'PhpArchitector\\Analyzer\\') === 0) {
            $layers['Analyzer'][] = $class;
        } else {
            $layers['Other'][] = $class;
        }
    }
    
    foreach ($layers as $layerName => $layerClasses) {
        $report .= "### {$layerName} Layer\n\n";
        $report .= "- Class count: " . count($layerClasses) . "\n";
        
        if (!empty($layerClasses)) {
            $totalLines = 0;
            foreach ($layerClasses as $class) {
                $totalLines += $class['lines_of_code'] ?? $class['linesOfCode'] ?? 0;
            }
            $avgLines = $totalLines / count($layerClasses);
            $report .= "- Total lines: {$totalLines}\n";
            $report .= "- Average lines per class: " . round($avgLines, 1) . "\n";
            
            $report .= "\n**Classes:**\n\n";
            foreach ($layerClasses as $class) {
                $className = $class['fqcn'] ?? $class['namespace'] . '\\' . ($class['class_name'] ?? $class['name'] ?? 'Unknown');
                $lines = $class['lines_of_code'] ?? $class['linesOfCode'] ?? 0;
                $report .= "- {$className} ({$lines} lines)\n";
            }
        }
        $report .= "\n";
    }
    
    // Dependency analysis
    $report .= "## Dependency Analysis\n\n";
    $report .= "- Total dependencies: " . count($dependencies['edges'] ?? []) . "\n";
    
    $internalDeps = 0;
    $externalDeps = 0;
    foreach ($dependencies['edges'] ?? [] as $edge) {
        if (isset($dependencies['nodes'][$edge['source']]['type']) && 
            $dependencies['nodes'][$edge['source']]['type'] !== 'external') {
            $internalDeps++;
        } else {
            $externalDeps++;
        }
    }
    
    $report .= "- Internal dependencies: {$internalDeps}\n";
    $report .= "- External dependencies: {$externalDeps}\n\n";
    
    // Top dependents
    $report .= "### Most Depended Upon Classes\n\n";
    $depCounts = [];
    foreach ($dependencies['edges'] ?? [] as $edge) {
        if (isset($dependencies['nodes'][$edge['target']]['fqcn'])) {
            $fqcn = $dependencies['nodes'][$edge['target']]['fqcn'];
            $depCounts[$fqcn] = ($depCounts[$fqcn] ?? 0) + 1;
        }
    }
    
    arsort($depCounts);
    $topDependents = array_slice($depCounts, 0, 10, true);
    
    foreach ($topDependents as $fqcn => $count) {
        $report .= "- {$fqcn}: {$count} dependents\n";
    }
    $report .= "\n";
    
    // Top dependencies
    $report .= "### Classes with Most Dependencies\n\n";
    $sourceCounts = [];
    foreach ($dependencies['edges'] ?? [] as $edge) {
        if (isset($dependencies['nodes'][$edge['source']]['fqcn'])) {
            $fqcn = $dependencies['nodes'][$edge['source']]['fqcn'];
            $sourceCounts[$fqcn] = ($sourceCounts[$fqcn] ?? 0) + 1;
        }
    }
    
    arsort($sourceCounts);
    $topSources = array_slice($sourceCounts, 0, 10, true);
    
    foreach ($topSources as $fqcn => $count) {
        $report .= "- {$fqcn}: {$count} dependencies\n";
    }
    
    return $report;
}

/**
 * Generate dependencies report
 */
function generateDependencyReport(array $dependencies, string $outputDir): string
{
    $report = "# Dependency Graph Analysis\n\n";
    $report .= "**Analysis Date:** " . date('Y-m-d H:i:s') . "\n\n";
    
    $report .= "## Graph Overview\n\n";
    $nodeCount = count($dependencies['nodes'] ?? []);
    $edgeCount = count($dependencies['edges'] ?? []);
    $report .= "- Nodes: {$nodeCount}\n";
    $report .= "- Edges: {$edgeCount}\n\n";
    
    if ($nodeCount > 0) {
        $avgDeps = $edgeCount / $nodeCount;
        $report .= "- Average dependencies per node: " . round($avgDeps, 2) . "\n\n";
    }
    
    $report .= "## Full Dependency Graph\n\n";
    $report .= "```mermaid\n";
    $report .= "graph LR\n\n";
    
    foreach ($dependencies['edges'] ?? [] as $edge) {
        $sourceId = $edge['source'] ?? 0;
        $targetId = $edge['target'] ?? 0;
        
        if (isset($dependencies['nodes'][$sourceId]['fqcn'])) {
            $source = $dependencies['nodes'][$sourceId]['fqcn'];
            $sourceName = str_replace('\\', '_', $source);
        } else {
            $sourceName = "node_{$sourceId}";
        }
        
        if (isset($dependencies['nodes'][$targetId]['fqcn'])) {
            $target = $dependencies['nodes'][$targetId]['fqcn'];
            $targetName = str_replace('\\', '_', $target);
        } else {
            $targetName = "node_{$targetId}";
        }
        
        $report .= "    {$sourceName} --> {$targetName}\n";
    }
    
    $report .= "```\n\n";
    
    // Detailed node information
    $report .= "## Nodes\n\n";
    foreach ($dependencies['nodes'] ?? [] as $node) {
        $fqcn = $node['fqcn'] ?? 'Unknown';
        $type = $node['type'] ?? 'unknown';
        $namespace = $node['namespace'] ?? '';
        $name = $node['name'] ?? '';
        
        $report .= "- **{$fqcn}**\n";
        $report .= "  - Type: {$type}\n";
        if (!empty($namespace)) {
            $report .= "  - Namespace: {$namespace}\n";
        }
        if (!empty($name)) {
            $report .= "  - Name: {$name}\n";
        }
    }
    
    return $report;
}

/**
 * Generate patterns report
 */
function generatePatternReport(array $patterns, string $outputDir): string
{
    $report = "# Pattern Detection Results\n\n";
    $report .= "**Analysis Date:** " . date('Y-m-d H:i:s') . "\n\n";
    
    // Detected patterns
    $report .= "## Detected Patterns\n\n";
    $patternCount = count($patterns['patterns'] ?? []);
    $report .= "Total patterns: {$patternCount}\n\n";
    
    if (!empty($patterns['patterns'])) {
        foreach ($patterns['patterns'] as $pattern) {
            $type = $pattern['type'] ?? 'Unknown';
            $class = $pattern['class'] ?? $pattern['fqcn'] ?? 'Unknown';
            $severity = $pattern['severity'] ?? 'info';
            $description = $pattern['description'] ?? '';
            
            $severityIcon = match ($severity) {
                'critical' => '🔴',
                'high' => '🟠',
                'medium' => '🟡',
                'low' => '🟢',
                default => 'ℹ️',
            };
            
            $report .= "- {$severityIcon} **{$type}** in {$class}\n";
            if (!empty($description)) {
                $report .= "  - {$description}\n";
            }
        }
        $report .= "\n";
    } else {
        $report .= "No patterns detected.\n\n";
    }
    
    // Anti-patterns
    $report .= "## Anti-Patterns\n\n";
    $antiPatternCount = count($patterns['anti_patterns'] ?? []);
    $report .= "Total anti-patterns: {$antiPatternCount}\n\n";
    
    if (!empty($patterns['anti_patterns'])) {
        foreach ($patterns['anti_patterns'] as $antiPattern) {
            $type = $antiPattern['type'] ?? 'Unknown';
            $class = $antiPattern['class'] ?? $antiPattern['fqcn'] ?? 'Unknown';
            $severity = $antiPattern['severity'] ?? 'warning';
            $description = $antiPattern['description'] ?? '';
            
            $severityIcon = match ($severity) {
                'critical' => '🔴',
                'high' => '🟠',
                'medium' => '🟡',
                'low', 'warning' => '⚠️',
                default => 'ℹ️',
            };
            
            $report .= "- {$severityIcon} **{$type}** in {$class}\n";
            if (!empty($description)) {
                $report .= "  - {$description}\n";
            }
        }
        $report .= "\n";
    } else {
        $report .= "No anti-patterns detected.\n\n";
    }
    
    // Layer violations
    $report .= "## Layer Violations\n\n";
    $violationCount = count($patterns['layer_violations'] ?? []);
    $report .= "Total violations: {$violationCount}\n\n";
    
    if (!empty($patterns['layer_violations'])) {
        foreach ($patterns['layer_violations'] as $violation) {
            $from = $violation['from'] ?? 'Unknown';
            $to = $violation['to'] ?? 'Unknown';
            $reason = $violation['reason'] ?? '';
            
            $report .= "- ❌ **{$from} -> {$to}**\n";
            if (!empty($reason)) {
                $report .= "  - Reason: {$reason}\n";
            }
        }
        $report .= "\n";
    } else {
        $report .= "No layer violations detected.\n\n";
    }
    
    return $report;
}

/**
 * Generate metrics report
 */
function generateMetricsReport(array $classes, string $outputDir): string
{
    $report = "# Code Metrics\n\n";
    $report .= "**Analysis Date:** " . date('Y-m-d H:i:s') . "\n\n";
    
    // Class metrics
    $report .= "## Class Metrics\n\n";
    $report .= "| Class | Lines | Methods | Properties | Constants |\n";
    $report .= "|-------|-------|---------|------------|-----------|\n";
    
    $totalLines = 0;
    $totalMethods = 0;
    $totalProperties = 0;
    $totalConstants = 0;
    
    foreach ($classes as $class) {
        $fqcn = $class['fqcn'] ?? $class['namespace'] . '\\' . ($class['class_name'] ?? $class['name'] ?? 'Unknown');
        $lines = $class['lines_of_code'] ?? $class['linesOfCode'] ?? 0;
        $methods = count($class['methods'] ?? []);
        $properties = count($class['properties'] ?? []);
        $constants = count($class['constants'] ?? []);
        
        $totalLines += $lines;
        $totalMethods += $methods;
        $totalProperties += $properties;
        $totalConstants += $constants;
        
        $report .= "| {$fqcn} | {$lines} | {$methods} | {$properties} | {$constants} |\n";
    }
    
    $report .= "\n";
    $report .= "**Totals:**\n";
    $report .= "- Total lines: {$totalLines}\n";
    $report .= "- Total methods: {$totalMethods}\n";
    $report .= "- Total properties: {$totalProperties}\n";
    $report .= "- Total constants: {$totalConstants}\n\n";
    
    if (count($classes) > 0) {
        $avgLines = $totalLines / count($classes);
        $avgMethods = $totalMethods / count($classes);
        $avgProperties = $totalProperties / count($classes);
        
        $report .= "**Averages:**\n";
        $report .= "- Average lines per class: " . round($avgLines, 1) . "\n";
        $report .= "- Average methods per class: " . round($avgMethods, 1) . "\n";
        $report .= "- Average properties per class: " . round($avgProperties, 1) . "\n\n";
    }
    
    // Size distribution
    $report .= "## Size Distribution\n\n";
    
    $sizeRanges = [
        '0-100' => 0,
        '101-200' => 0,
        '201-500' => 0,
        '501-1000' => 0,
        '1001+' => 0,
    ];
    
    foreach ($classes as $class) {
        $lines = $class['lines_of_code'] ?? $class['linesOfCode'] ?? 0;
        
        if ($lines <= 100) {
            $sizeRanges['0-100']++;
        } elseif ($lines <= 200) {
            $sizeRanges['101-200']++;
        } elseif ($lines <= 500) {
            $sizeRanges['201-500']++;
        } elseif ($lines <= 1000) {
            $sizeRanges['501-1000']++;
        } else {
            $sizeRanges['1001+']++;
        }
    }
    
    $report .= "| Size Range | Count |\n";
    $report .= "|------------|-------|\n";
    foreach ($sizeRanges as $range => $count) {
        $report .= "| {$range} lines | {$count} |\n";
    }
    $report .= "\n";
    
    // Method complexity (placeholder)
    $report .= "## Method Metrics\n\n";
    $report .= "*Note: Detailed method complexity metrics will be added in future versions*\n\n";
    
    return $report;
}

// Main execution
try {
    logMessage("\n" . str_repeat('=', 60), 1, $config['verbosity'] ?? 1);
    logMessage("REPORT GENERATOR", 1, $config['verbosity'] ?? 1);
    logMessage(str_repeat('=', 60), 1, $config['verbosity'] ?? 1);
    
    // Load analysis results
    logMessage("\nLoading analysis results...", 1, $config['verbosity'] ?? 1);
    $rawResults = loadAnalysisResults($config);
    
    // Normalize data
    $classes = normalizeClassData($rawResults['classes'] ?? []);
    $dependencies = normalizeDependencyData($rawResults['dependencies'] ?? []);
    $patterns = normalizePatternData($rawResults['patterns'] ?? []);
    
    logMessage("Normalized data: " . count($classes) . " classes, " . count($dependencies['nodes'] ?? []) . " nodes, " . count($patterns['patterns'] ?? []) . " patterns", 1, $config['verbosity'] ?? 1);
    
    // Generate reports
    logMessage("\nGenerating reports...", 1, $config['verbosity'] ?? 1);
    
    // 1. Summary report
    $summaryReport = generateSummaryReport($classes, $dependencies, $patterns, $validationRules);
    $summaryFile = $outputDir . '/summary.md';
    file_put_contents($summaryFile, $summaryReport);
    logMessage("✅ Generated summary.md", 1, $config['verbosity'] ?? 1);
    
    // 2. Architecture report
    $architectureReport = generateArchitectureReport($classes, $dependencies, $outputDir);
    $architectureFile = $outputDir . '/architecture.md';
    file_put_contents($architectureFile, $architectureReport);
    logMessage("✅ Generated architecture.md", 1, $config['verbosity'] ?? 1);
    
    // 3. Dependencies report
    $dependenciesReport = generateDependencyReport($dependencies, $outputDir);
    $dependenciesFile = $outputDir . '/dependencies.md';
    file_put_contents($dependenciesFile, $dependenciesReport);
    logMessage("✅ Generated dependencies.md", 1, $config['verbosity'] ?? 1);
    
    // 4. Patterns report
    $patternsReport = generatePatternReport($patterns, $outputDir);
    $patternsFile = $outputDir . '/patterns.md';
    file_put_contents($patternsFile, $patternsReport);
    logMessage("✅ Generated patterns.md", 1, $config['verbosity'] ?? 1);
    
    // 5. Metrics report
    $metricsReport = generateMetricsReport($classes, $outputDir);
    $metricsFile = $outputDir . '/metrics.md';
    file_put_contents($metricsFile, $metricsReport);
    logMessage("✅ Generated metrics.md", 1, $config['verbosity'] ?? 1);
    
    // Also save normalized data as JSON for reference
    $normalizedData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'classes' => $classes,
        'dependencies' => $dependencies,
        'patterns' => $patterns,
    ];
    $normalizedFile = $outputDir . '/normalized_data.json';
    file_put_contents($normalizedFile, json_encode($normalizedData, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    
    // Print summary
    logMessage("\n" . str_repeat('=', 60), 1, $config['verbosity'] ?? 1);
    logMessage("REPORT GENERATION COMPLETE", 1, $config['verbosity'] ?? 1);
    logMessage(str_repeat('=', 60), 1, $config['verbosity'] ?? 1);
    
    logMessage("\nGenerated files:", 1, $config['verbosity'] ?? 1);
    logMessage("  - summary.md", 1, $config['verbosity'] ?? 1);
    logMessage("  - architecture.md", 1, $config['verbosity'] ?? 1);
    logMessage("  - dependencies.md", 1, $config['verbosity'] ?? 1);
    logMessage("  - patterns.md", 1, $config['verbosity'] ?? 1);
    logMessage("  - metrics.md", 1, $config['verbosity'] ?? 1);
    logMessage("  - normalized_data.json", 1, $config['verbosity'] ?? 1);
    
    logMessage("\nReports saved to: {$outputDir}", 1, $config['verbosity'] ?? 1);
    
    exit(0);
} catch (Throwable $e) {
    echo date('[Y-m-d H:i:s]') . " FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}
