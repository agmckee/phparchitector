<?php declare(strict_types=1);

/**
 * Configuration for self-analysis framework.
 * 
 * This file defines paths, exclusions, and which analyzers to run.
 */

// Calculate absolute paths based on this file's location
$toolDir = __DIR__;
$repoRoot = dirname($toolDir, 2); // tools/self-analysis/ -> repo root

return [
    // Path to baseline analyzer
    'baseline_path' => $toolDir . '/baseline',
    
    // Target directory to analyze (absolute path)
    'target_path' => $repoRoot . '/src',
    
    // Files/directories to exclude from analysis
    'excludes' => [
        'tests/',
        'vendor/',
        'tools/',
    ],
    
    // Which analyzers to run
    'analyzers' => [
        'ClassAnalyzer',
        'DependencyAnalyzer',
        'PatternDetector',
    ],
    
    // Output directory for reports
    'output_dir' => $toolDir . '/reports',
    
    // Temporary directory for intermediate files
    'temp_dir' => $toolDir . '/temp',
    
    // Whether to clean up temp files after analysis
    'cleanup_temp' => true,
    
    // Verbosity level (0 = quiet, 1 = normal, 2 = verbose)
    'verbosity' => 1,
];