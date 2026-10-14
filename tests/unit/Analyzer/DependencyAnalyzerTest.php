<?php declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/autoload.php';

use App\Analyzer\DependencyAnalyzer;

/**
 * Test DependencyAnalyzer with the sample application
 */
function test_dependency_analyzer_with_sample_app(): void
{
    $analyzer = new DependencyAnalyzer();
    
    // Analyze the sample app directory
    $sampleAppDir = __DIR__ . '/../../fixtures/sample_app';
    $graph = $analyzer->analyzeDirectory($sampleAppDir);
    
    echo "Dependency Graph Analysis\n";
    echo "=========================\n\n";
    
    echo "Nodes found: " . count($graph['nodes']) . "\n";
    foreach ($graph['nodes'] as $node) {
        echo "  - " . $node['fqcn'] . " (" . $node['type'] . ")\n";
    }
    
    echo "\nEdges found: " . count($graph['edges']) . "\n";
    foreach ($graph['edges'] as $edge) {
        $source = $graph['nodes'][$edge['source']]['fqcn'];
        $target = $graph['nodes'][$edge['target']]['fqcn'];
        echo "  - " . $source . " -> " . $target . " (" . $edge['type'] . ")\n";
    }
    
    // Verify expected dependencies
    $fqcns = array_column($graph['nodes'], 'fqcn');
    
    assert(in_array('App\\Controllers\\UserController', $fqcns, true), 'UserController should be in graph');
    assert(in_array('App\\Services\\UserService', $fqcns, true), 'UserService should be in graph');
    assert(in_array('App\\Repositories\\UserRepository', $fqcns, true), 'UserRepository should be in graph');
    
    // Check for expected edges
    $edgesBySourceAndTarget = [];
    foreach ($graph['edges'] as $edge) {
        $source = $graph['nodes'][$edge['source']]['fqcn'];
        $target = $graph['nodes'][$edge['target']]['fqcn'];
        $edgesBySourceAndTarget[$source . '->' . $target] = $edge['type'];
    }
    
    // UserController should depend on UserService
    $key = 'App\\Controllers\\UserController->App\\Services\\UserService';
    assert(isset($edgesBySourceAndTarget[$key]), 'UserController should depend on UserService');
    
    // UserService should depend on UserRepository
    $key = 'App\\Services\\UserService->App\\Repositories\\UserRepository';
    assert(isset($edgesBySourceAndTarget[$key]), 'UserService should depend on UserRepository');
    
    // Check for circular dependencies (there should be none in sample app)
    $circular = $analyzer->findCircularDependencies($graph);
    assert(count($circular) === 0, 'Sample app should have no circular dependencies');
    
    echo "\n✓ DependencyAnalyzer test with sample app passed\n";
}

/**
 * Test dependency extraction from a single file
 */
function test_dependency_analyzer_single_file(): void
{
    $analyzer = new DependencyAnalyzer();
    
    $source = '<?php

namespace App\Controllers;

use App\Services\UserService;
use Symfony\Component\HttpFoundation\Response;

class UserController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(): Response
    {
        return new Response("Hello");
    }

    public function create(): void
    {
        $user = new \App\Models\User();
    }
}';

    // Write to temp file
    $tmpfile = tempnam(sys_get_temp_dir(), 'php_') . '.php';
    file_put_contents($tmpfile, $source);
    
    $result = $analyzer->analyzeFile($tmpfile);
    
    unlink($tmpfile);
    
    assert($result['fqcn'] === 'App\\Controllers\\UserController', 'Expected UserController FQCN');
    assert($result['type'] === 'class', 'Expected class type');
    
    $dependencyFqcns = array_column($result['dependencies'], 'fqcn');
    
    // Should detect use statement dependencies
    assert(in_array('App\\Services\\UserService', $dependencyFqcns, true), 'Expected UserService dependency');
    assert(in_array('Symfony\\Component\\HttpFoundation\\Response', $dependencyFqcns, true), 'Expected Response dependency');
    
    // Should detect instantiation
    assert(in_array('App\\Models\\User', $dependencyFqcns, true), 'Expected User instantiation dependency');
    
    // Should detect type hints
    assert(in_array('App\\Services\\UserService', $dependencyFqcns, true), 'Expected UserService type hint dependency');
    assert(in_array('Symfony\\Component\\HttpFoundation\\Response', $dependencyFqcns, true), 'Expected Response return type dependency');
    
    echo "✓ DependencyAnalyzer single file test passed\n";
}

/**
 * Test circular dependency detection
 */
function test_circular_dependency_detection(): void
{
    $analyzer = new DependencyAnalyzer();
    
    // Create a test directory with circular dependencies
    $tmpDir = sys_get_temp_dir() . '/circular_test_' . uniqid();
    mkdir($tmpDir, 0777, true);
    
    // Class A depends on B
    file_put_contents($tmpDir . '/ClassA.php', '<?php

namespace Test;

use Test\ClassB;

class ClassA
{
    private ClassB $b;

    public function __construct(ClassB $b)
    {
        $this->b = $b;
    }
}');

    // Class B depends on C
    file_put_contents($tmpDir . '/ClassB.php', '<?php

namespace Test;

use Test\ClassC;

class ClassB
{
    private ClassC $c;

    public function __construct(ClassC $c)
    {
        $this->c = $c;
    }
}');

    // Class C depends on A (creates circular dependency A -> B -> C -> A)
    file_put_contents($tmpDir . '/ClassC.php', '<?php

namespace Test;

use Test\ClassA;

class ClassC
{
    private ClassA $a;

    public function __construct(ClassA $a)
    {
        $this->a = $a;
    }
}');

    $graph = $analyzer->analyzeDirectory($tmpDir);
    $circular = $analyzer->findCircularDependencies($graph);
    
    // Clean up
    unlink($tmpDir . '/ClassA.php');
    unlink($tmpDir . '/ClassB.php');
    unlink($tmpDir . '/ClassC.php');
    rmdir($tmpDir);
    
    assert(count($circular) === 1, 'Expected 1 circular dependency group');
    assert(count($circular[0]) === 3, 'Expected cycle of 3 classes');
    
    // Verify the cycle contains all three classes
    $fqcnsInCycle = $circular[0];
    assert(in_array('Test\\ClassA', $fqcnsInCycle, true), 'Cycle should contain ClassA');
    assert(in_array('Test\\ClassB', $fqcnsInCycle, true), 'Cycle should contain ClassB');
    assert(in_array('Test\\ClassC', $fqcnsInCycle, true), 'Cycle should contain ClassC');
    
    echo "✓ Circular dependency detection test passed\n";
}

/**
 * Run all tests
 */
function run_tests(): void
{
    echo "Running DependencyAnalyzer tests...\n\n";
    
    try {
        test_dependency_analyzer_with_sample_app();
        test_dependency_analyzer_single_file();
        test_circular_dependency_detection();
        
        echo "\n✓ All DependencyAnalyzer tests passed!\n";
    } catch (\AssertionError $e) {
        echo "\n✗ Test failed: " . $e->getMessage() . "\n";
        exit(1);
    } catch (\Throwable $e) {
        echo "\n✗ Unexpected error: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
}

run_tests();
