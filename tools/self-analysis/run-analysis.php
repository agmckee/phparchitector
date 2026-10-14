<?php declare(strict_types=1);

/**
 * Self-Analysis Runner
 * 
 * This script loads the baseline analyzers and runs them on the current codebase.
 * It generates JSON reports for each analyzer and handles errors gracefully.
 */

use PhpArchitector\Analyzer\ClassAnalyzer;
use PhpArchitector\Analyzer\DependencyAnalyzer;
use PhpArchitector\Analyzer\PatternDetector;

// Set up error handling
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Parse command line arguments for overrides
$options = getopt('', ['target:', 'output:', 'help', 'version']);
if (isset($options['help'])) {
    echo "Usage: php run-analysis.php [OPTIONS]\n";
    echo "\nOptions:\n";
    echo "  --target=PATH    Target directory to analyze\n";
    echo "  --output=PATH    Output directory for reports\n";
    echo "  --help          Show this help message\n";
    echo "  --version       Show version\n";
    exit(0);
}

if (isset($options['version'])) {
    echo "Self-Analysis Runner version 1.0.0\n";
    exit(0);
}

// Load configuration
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die("Error: Configuration file not found at {$configPath}\n");
}

$config = require $configPath;

// Override config from command line
if (isset($options['target'])) {
    $config['target_path'] = $options['target'];
}
if (isset($options['output'])) {
    $config['output_dir'] = $options['output'];
}

// Ensure output directory exists
$outputDir = $config['output_dir'];
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        die("Error: Failed to create output directory {$outputDir}\n");
    }
}

// Ensure temp directory exists
$tempDir = $config['temp_dir'];
if (!is_dir($tempDir)) {
    if (!mkdir($tempDir, 0755, true)) {
        die("Error: Failed to create temp directory {$tempDir}\n");
    }
}

// Add baseline vendor to include path
$baselineVendor = $config['baseline_path'] . '/vendor';
set_include_path(get_include_path() . PATH_SEPARATOR . $baselineVendor);

// Load baseline autoloader
$baselineAutoload = $config['baseline_path'] . '/vendor/autoload.php';
if (!file_exists($baselineAutoload)) {
    die("Error: Baseline autoloader not found at {$baselineAutoload}\n");
}
require_once $baselineAutoload;

// Load baseline src autoloader
$baselineSrcAutoload = $config['baseline_path'] . '/src/autoload.php';
if (file_exists($baselineSrcAutoload)) {
    require_once $baselineSrcAutoload;
}

// Log function for verbose output
function logMessage(string $message, int $verbosity, int $configVerbosity): void
{
    if ($verbosity <= $configVerbosity) {
        echo date('[Y-m-d H:i:s]') . ' ' . $message . "\n";
    }
}

function logError(string $message, int $verbosity): void
{
    echo date('[Y-m-d H:i:s]') . ' ERROR: ' . $message . "\n";
}

function logWarning(string $message, int $verbosity): void
{
    if ($verbosity >= 1) {
        echo date('[Y-m-d H:i:s]') . ' WARNING: ' . $message . "\n";
    }
}

// Helper function to find PHP files recursively
function findPhpFiles(string $directory, array $excludes = []): array
{
    $files = [];
    
    if (!is_dir($directory)) {
        return $files;
    }
    
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isFile() && $item->getExtension() === 'php') {
            $filePath = $item->getRealPath();
            
            // Check exclusions
            $excluded = false;
            foreach ($excludes as $exclude) {
                if (strpos($filePath, $exclude) !== false) {
                    $excluded = true;
                    break;
                }
            }
            
            if (!$excluded) {
                $files[] = $filePath;
            }
        }
    }
    
    return $files;
}

// Main analysis function
function runAnalysis(array $config): array
{
    $results = [
        'timestamp' => date('Y-m-d H:i:s'),
        'config' => $config,
        'analyzers' => [],
        'errors' => [],
        'warnings' => [],
    ];
    
    $targetPath = $config['target_path'];
    $verbosity = $config['verbosity'] ?? 1;
    
    logMessage("Starting self-analysis...", 1, $verbosity);
    logMessage("Target directory: {$targetPath}", 1, $verbosity);
    logMessage("Analyzers to run: " . implode(', ', $config['analyzers']), 1, $verbosity);
    
    // Check if target directory exists
    if (!is_dir($targetPath)) {
        $error = "Target directory does not exist: {$targetPath}";
        logError($error, $verbosity);
        $results['errors'][] = $error;
        return $results;
    }
    
    // Find PHP files to analyze
    $phpFiles = findPhpFiles($targetPath, $config['excludes']);
    logMessage("Found " . count($phpFiles) . " PHP files to analyze", 1, $verbosity);
    
    if (empty($phpFiles)) {
        $warning = "No PHP files found in target directory";
        logWarning($warning, $verbosity);
        $results['warnings'][] = $warning;
    }
    
    // Run each analyzer
    foreach ($config['analyzers'] as $analyzerName) {
        $analyzerKey = lcfirst($analyzerName);
        
        logMessage("\n=== Running {$analyzerName} ===", 1, $verbosity);
        
        try {
            // Instantiate the analyzer
            $className = 'PhpArchitector\\Analyzer\\' . $analyzerName;
            
            if (!class_exists($className)) {
                $error = "Analyzer class not found: {$className}";
                logError($error, $verbosity);
                $results['errors'][] = $error;
                $results['analyzers'][$analyzerKey] = [
                    'status' => 'error',
                    'error' => $error,
                ];
                continue;
            }
            
            $analyzer = new $className();
            
            // Check if analyzer has analyzeDirectory method
            if (method_exists($analyzer, 'analyzeDirectory')) {
                logMessage("Running analyzeDirectory...", 2, $verbosity);
                $startTime = microtime(true);
                
                $analyzerResults = $analyzer->analyzeDirectory($targetPath);
                
                $executionTime = microtime(true) - $startTime;
                logMessage("Completed in " . round($executionTime, 2) . " seconds", 1, $verbosity);
                
                // Save results to JSON file
                $outputFile = $config['output_dir'] . '/raw_' . strtolower($analyzerName) . '.json';
                file_put_contents($outputFile, json_encode($analyzerResults, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                
                $results['analyzers'][$analyzerKey] = [
                    'status' => 'success',
                    'results' => $analyzerResults,
                    'execution_time' => $executionTime,
                    'file_count' => count($phpFiles),
                    'output_file' => $outputFile,
                ];
            } elseif (method_exists($analyzer, 'analyze')) {
                // Analyzer only supports single file analysis, run on each file
                logMessage("Running analyze on each file...", 2, $verbosity);
                $startTime = microtime(true);
                
                $fileResults = [];
                foreach ($phpFiles as $filePath) {
                    try {
                        $fileResults[$filePath] = $analyzer->analyze(file_get_contents($filePath));
                    } catch (Throwable $e) {
                        logWarning("Failed to analyze {$filePath}: " . $e->getMessage(), $verbosity);
                        $fileResults[$filePath] = [
                            'error' => $e->getMessage(),
                            'file' => $filePath,
                        ];
                    }
                }
                
                $executionTime = microtime(true) - $startTime;
                logMessage("Completed in " . round($executionTime, 2) . " seconds", 1, $verbosity);
                
                // Save results to JSON file
                $outputFile = $config['output_dir'] . '/raw_' . strtolower($analyzerName) . '.json';
                file_put_contents($outputFile, json_encode($fileResults, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                
                $results['analyzers'][$analyzerKey] = [
                    'status' => 'success',
                    'results' => $fileResults,
                    'execution_time' => $executionTime,
                    'file_count' => count($phpFiles),
                    'output_file' => $outputFile,
                ];
            } else {
                $error = "Analyzer {$analyzerName} has no analyzeDirectory or analyze method";
                logError($error, $verbosity);
                $results['errors'][] = $error;
                $results['analyzers'][$analyzerKey] = [
                    'status' => 'error',
                    'error' => $error,
                ];
            }
        } catch (Throwable $e) {
            $error = "Failed to run {$analyzerName}: " . $e->getMessage();
            logError($error, $verbosity);
            $results['errors'][] = $error;
            $results['analyzers'][$analyzerKey] = [
                'status' => 'error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }
    
    return $results;
}

// Main execution
try {
    logMessage("\n" . str_repeat('=', 60), 1, $config['verbosity'] ?? 1);
    logMessage("SELF-ANALYSIS RUNNER", 1, $config['verbosity'] ?? 1);
    logMessage(str_repeat('=', 60), 1, $config['verbosity'] ?? 1);
    
    $results = runAnalysis($config);
    
    // Save summary results
    $summaryFile = $config['output_dir'] . '/analysis_summary.json';
    file_put_contents($summaryFile, json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    
    // Print summary
    logMessage("\n" . str_repeat('=', 60), 1, $config['verbosity'] ?? 1);
    logMessage("ANALYSIS COMPLETE", 1, $config['verbosity'] ?? 1);
    logMessage(str_repeat('=', 60), 1, $config['verbosity'] ?? 1);
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($results['analyzers'] as $analyzerName => $analyzerResult) {
        if ($analyzerResult['status'] === 'success') {
            $successCount++;
            logMessage("✅ {$analyzerName}: Success", 1, $config['verbosity'] ?? 1);
        } else {
            $errorCount++;
            logMessage("❌ {$analyzerName}: Failed", 1, $config['verbosity'] ?? 1);
        }
    }
    
    logMessage("\nSummary:", 1, $config['verbosity'] ?? 1);
    logMessage("  Successful analyzers: {$successCount}", 1, $config['verbosity'] ?? 1);
    logMessage("  Failed analyzers: {$errorCount}", 1, $config['verbosity'] ?? 1);
    logMessage("  Errors: " . count($results['errors']), 1, $config['verbosity'] ?? 1);
    logMessage("  Warnings: " . count($results['warnings']), 1, $config['verbosity'] ?? 1);
    
    if (!empty($results['errors'])) {
        logMessage("\nErrors:", 1, $config['verbosity'] ?? 1);
        foreach ($results['errors'] as $error) {
            logError("  - {$error}", $config['verbosity'] ?? 1);
        }
    }
    
    if (!empty($results['warnings'])) {
        logMessage("\nWarnings:", 1, $config['verbosity'] ?? 1);
        foreach ($results['warnings'] as $warning) {
            logWarning("  - {$warning}", $config['verbosity'] ?? 1);
        }
    }
    
    logMessage("\nReports saved to: {$outputDir}", 1, $config['verbosity'] ?? 1);
    
    // Clean up temp directory if configured
    if (!empty($config['cleanup_temp']) && $config['cleanup_temp'] && is_dir($tempDir)) {
        array_map('unlink', glob($tempDir . '/*.*'));
        rmdir($tempDir);
    }
    
    // Exit with appropriate code
    if ($errorCount > 0) {
        exit(1);
    }
    
    exit(0);
} catch (Throwable $e) {
    echo date('[Y-m-d H:i:s]') . " FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}
