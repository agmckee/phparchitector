<?php declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/autoload.php';

use App\Analyzer\ClassAnalyzer;

/**
 * Test the ClassAnalyzer
 */
function test_analyzer_with_sample_files(): void
{
    $analyzer = new ClassAnalyzer();
    
    // Test UserController
    $controllerPath = __DIR__ . '/../../fixtures/sample_app/Controllers/UserController.php';
    $controllerInfo = $analyzer->analyzeFile($controllerPath);
    
    assert($controllerInfo['namespace'] === 'App\Controllers', 'Expected App\Controllers namespace');
    assert($controllerInfo['class_name'] === 'UserController', 'Expected UserController class');
    assert(in_array('App\Services\UserService', $controllerInfo['dependencies'], true), 'Expected UserService dependency');
    assert(in_array('Symfony\Component\HttpFoundation\Response', $controllerInfo['dependencies'], true), 'Expected Response dependency');
    assert(count($controllerInfo['methods']) === 3, 'Expected 3 methods (construct, index, show)');
    
    // Check for specific methods
    $methodNames = array_column($controllerInfo['methods'], 'name');
    assert(in_array('__construct', $methodNames, true), 'Expected __construct method');
    assert(in_array('index', $methodNames, true), 'Expected index method');
    assert(in_array('show', $methodNames, true), 'Expected show method');
    
    echo "✓ ClassAnalyzer test with sample files passed\n";
}

/**
 * Test with inline code
 */
function test_analyzer_with_inline_code(): void
{
    $analyzer = new ClassAnalyzer();
    
    $source = '<?php

namespace App\Models;

use App\Repositories\UserRepository;

abstract class User extends BaseModel implements \ArrayAccess
{
    protected string $name;
    private static int $count = 0;
    
    public function __construct(string $name)
    {
        $this->name = $name;
        self::$count++;
    }
    
    abstract public function save(): bool;
    
    final public function getName(): string
    {
        return $this->name;
    }
}';

    $info = $analyzer->analyze($source);
    
    assert($info['namespace'] === 'App\Models', 'Expected App\Models namespace');
    assert($info['class_name'] === 'User', 'Expected User class');
    assert($info['extends'] === 'BaseModel', 'Expected BaseModel as parent');
    assert(count($info['implements']) === 1, 'Expected 1 interface');
    assert($info['implements'][0] === '\ArrayAccess', 'Expected ArrayAccess interface');
    
    // Check properties
    $propertyNames = array_column($info['properties'], 'name');
    assert(in_array('$name', $propertyNames, true), 'Expected $name property');
    assert(in_array('$count', $propertyNames, true), 'Expected $count property');
    
    // Check methods
    $methodNames = array_column($info['methods'], 'name');
    assert(in_array('__construct', $methodNames, true), 'Expected __construct method');
    assert(in_array('save', $methodNames, true), 'Expected save method');
    assert(in_array('getName', $methodNames, true), 'Expected getName method');
    
    // Check method modifiers
    $saveMethod = null;
    $getNameMethod = null;
    foreach ($info['methods'] as $method) {
        if ($method['name'] === 'save') {
            $saveMethod = $method;
        }
        if ($method['name'] === 'getName') {
            $getNameMethod = $method;
        }
    }
    
    assert($saveMethod['is_abstract'] === true, 'Expected save to be abstract');
    assert($getNameMethod['is_final'] === true, 'Expected getName to be final');
    
    // Check dependencies
    assert(in_array('App\Repositories\UserRepository', $info['dependencies'], true), 'Expected UserRepository dependency');
    
    echo "✓ ClassAnalyzer test with inline code passed\n";
}

/**
 * Run all tests
 */
function run_tests(): void
{
    echo "Running ClassAnalyzer tests...\n\n";
    
    try {
        test_analyzer_with_sample_files();
        test_analyzer_with_inline_code();
        
        echo "\n✓ All ClassAnalyzer tests passed!\n";
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
