<?php declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/autoload.php';

use App\Analyzer\PatternDetector;
use App\Analyzer\DependencyAnalyzer;

/**
 * Test PatternDetector with the sample application
 */
function test_pattern_detector_with_sample_app(): void
{
    $dependencyAnalyzer = new DependencyAnalyzer();
    $patternDetector = new PatternDetector($dependencyAnalyzer);
    
    $sampleAppDir = __DIR__ . '/../../fixtures/sample_app';
    $findings = $patternDetector->analyzeDirectory($sampleAppDir);
    
    echo "PatternDetector Analysis Results\n";
    echo "================================\n\n";
    
    echo "Files analyzed: " . $findings['files_analyzed'] . "\n\n";
    
    echo "Patterns found: " . count($findings['patterns']) . "\n";
    foreach ($findings['patterns'] as $pattern) {
        echo "  - [" . $pattern['severity'] . "] " . $pattern['type'] . ": " . $pattern['message'] . "\n";
    }
    
    echo "\nAnti-patterns found: " . count($findings['anti_patterns']) . "\n";
    foreach ($findings['anti_patterns'] as $antiPattern) {
        echo "  - [" . $antiPattern['severity'] . "] " . $antiPattern['type'] . ": " . $antiPattern['message'] . "\n";
    }
    
    // Verify expected findings
    $patternTypes = array_column($findings['patterns'], 'type');
    $antiPatternTypes = array_column($findings['anti_patterns'], 'type');
    
    // Should detect dependency injection in controllers and services
    assert(in_array('dependency_injection', $patternTypes, true), 'Should detect dependency injection pattern');
    
    // Should detect repository pattern
    assert(in_array('repository_pattern', $patternTypes, true), 'Should detect repository pattern');
    
    echo "\n✓ PatternDetector test with sample app passed\n";
}

/**
 * Test individual class analysis
 */
function test_pattern_detector_single_class(): void
{
    $patternDetector = new PatternDetector();
    
    // Test God Class detection
    $source = '<?php

namespace App\Test;

class GodClass
{
    private $prop1;
    private $prop2;
    private $prop3;
    private $prop4;
    private $prop5;
    private $prop6;
    private $prop7;
    private $prop8;
    private $prop9;
    private $prop10;
    private $prop11;
    private $prop12;
    private $prop13;
    private $prop14;
    private $prop15;

    public function method1() {}
    public function method2() {}
    public function method3() {}
    public function method4() {}
    public function method5() {}
    public function method6() {}
    public function method7() {}
    public function method8() {}
    public function method9() {}
    public function method10() {}
    public function method11() {}
    public function method12() {}
    public function method13() {}
    public function method14() {}
    public function method15() {}
    public function method16() {}
    public function method17() {}
    public function method18() {}
    public function method19() {}
    public function method20() {}
}';

    $tmpfile = tempnam(sys_get_temp_dir(), 'php_') . '.php';
    file_put_contents($tmpfile, $source);
    
    $fileNode = (new \PhpSyntax\Parser())->parse($source);
    $findings = $patternDetector->analyzeFile($fileNode, $tmpfile);
    
    unlink($tmpfile);
    
    // Should detect God Class anti-pattern
    $antiPatternTypes = array_column($findings['anti_patterns'], 'type');
    assert(in_array('god_class', $antiPatternTypes, true), 'Should detect God Class anti-pattern');
    
    echo "✓ God Class detection test passed\n";
}

/**
 * Test tight coupling detection
 */
function test_tight_coupling_detection(): void
{
    $patternDetector = new PatternDetector();
    
    $source = '<?php

namespace App\Test;

use App\External\ServiceA;
use App\External\ServiceB;
use App\External\ServiceC;

class TightlyCoupledClass
{
    public function doSomething()
    {
        $a = new ServiceA();
        $b = new ServiceB();
        $c = new ServiceC();
        
        return $a->process($b->getData(), $c->config());
    }
}';

    $tmpfile = tempnam(sys_get_temp_dir(), 'php_') . '.php';
    file_put_contents($tmpfile, $source);
    
    $fileNode = (new \PhpSyntax\Parser())->parse($source);
    $findings = $patternDetector->analyzeFile($fileNode, $tmpfile);
    
    unlink($tmpfile);
    
    // Should detect tight coupling
    $antiPatternTypes = array_column($findings['anti_patterns'], 'type');
    assert(in_array('tight_coupling', $antiPatternTypes, true), 'Should detect tight coupling anti-pattern');
    
    echo "✓ Tight coupling detection test passed\n";
}

/**
 * Test static overuse detection
 */
function test_static_overuse_detection(): void
{
    $patternDetector = new PatternDetector();
    
    $source = '<?php

namespace App\Test;

class StaticOveruseClass
{
    private static $instance;
    private static $config;
    private static $counter;
    private static $cache;

    public static function getInstance()
    {
        return self::$instance;
    }

    public static function create()
    {
        return new self();
    }

    public static function build()
    {
        return new self();
    }

    public static function make()
    {
        return new self();
    }

    public static function factory()
    {
        return new self();
    }
}';

    $tmpfile = tempnam(sys_get_temp_dir(), 'php_') . '.php';
    file_put_contents($tmpfile, $source);
    
    $fileNode = (new \PhpSyntax\Parser())->parse($source);
    $findings = $patternDetector->analyzeFile($fileNode, $tmpfile);
    
    unlink($tmpfile);
    
    // Should detect static overuse
    $antiPatternTypes = array_column($findings['anti_patterns'], 'type');
    assert(in_array('static_overuse', $antiPatternTypes, true), 'Should detect static overuse anti-pattern');
    
    echo "✓ Static overuse detection test passed\n";
}

/**
 * Test pattern configuration
 */
function test_pattern_configuration(): void
{
    $patternDetector = new PatternDetector();
    
    // Get default config
    $config = $patternDetector->getPatternConfig();
    
    assert(isset($config['god_class']), 'God class config should exist');
    assert(isset($config['god_class']['method_threshold']), 'Method threshold should exist');
    
    // Set custom config
    $patternDetector->setPatternConfig([
        'god_class' => [
            'method_threshold' => 50,
            'property_threshold' => 30,
        ],
    ]);
    
    $newConfig = $patternDetector->getPatternConfig();
    assert($newConfig['god_class']['method_threshold'] === 50, 'Custom method threshold should be applied');
    
    echo "✓ Pattern configuration test passed\n";
}

/**
 * Test layer violation detection
 */
function test_layer_violation_detection(): void
{
    $dependencyAnalyzer = new DependencyAnalyzer();
    $patternDetector = new PatternDetector($dependencyAnalyzer);
    
    // Create a test directory with layer violations
    $tmpDir = sys_get_temp_dir() . '/layer_test_' . uniqid();
    mkdir($tmpDir, 0777, true);
    mkdir($tmpDir . '/Controllers', 0777, true);
    mkdir($tmpDir . '/Services', 0777, true);
    mkdir($tmpDir . '/Repositories', 0777, true);
    
    // Repository that depends on a Service (layer violation)
    file_put_contents($tmpDir . '/Repositories/BadRepository.php', '<?php

namespace App\Repositories;

use App\Services\SomeService;

class BadRepository
{
    private SomeService $service;

    public function __construct(SomeService $service)
    {
        $this->service = $service;
    }

    public function find()
    {
        return $this->service->getData();
    }
}');

    // Service that depends on a Controller (layer violation)
    file_put_contents($tmpDir . '/Services/BadService.php', '<?php

namespace App\Services;

use App\Controllers\SomeController;

class BadService
{
    private SomeController $controller;

    public function __construct(SomeController $controller)
    {
        $this->controller = $controller;
    }
}');

    // Good controller that depends on service
    file_put_contents($tmpDir . '/Controllers/GoodController.php', '<?php

namespace App\Controllers;

use App\Services\GoodService;

class GoodController
{
    private GoodService $service;

    public function __construct(GoodService $service)
    {
        $this->service = $service;
    }
}');

    // Good service that depends on repository
    file_put_contents($tmpDir . '/Services/GoodService.php', '<?php

namespace App\Services;

use App\Repositories\GoodRepository;

class GoodService
{
    private GoodRepository $repository;

    public function __construct(GoodRepository $repository)
    {
        $this->repository = $repository;
    }
}');

    // Good repository
    file_put_contents($tmpDir . '/Repositories/GoodRepository.php', '<?php

namespace App\Repositories;

class GoodRepository
{
    public function find()
    {
        return new \stdClass();
    }
}');

    $findings = $patternDetector->analyzeDirectory($tmpDir);
    
    // Clean up
    $files = [
        '/Repositories/BadRepository.php',
        '/Services/BadService.php',
        '/Controllers/GoodController.php',
        '/Services/GoodService.php',
        '/Repositories/GoodRepository.php',
    ];
    foreach ($files as $file) {
        if (file_exists($tmpDir . $file)) {
            unlink($tmpDir . $file);
        }
    }
    rmdir($tmpDir . '/Controllers');
    rmdir($tmpDir . '/Services');
    rmdir($tmpDir . '/Repositories');
    rmdir($tmpDir);
    
    // Should detect layer violations
    $antiPatternTypes = array_column($findings['anti_patterns'], 'type');
    $layerViolations = array_filter($findings['anti_patterns'], function($item) {
        return $item['type'] === 'layer_violation';
    });
    
    assert(count($layerViolations) >= 1, 'Should detect at least one layer violation');
    
    echo "✓ Layer violation detection test passed\n";
}

/**
 * Test pattern detection for Repository and Factory patterns
 */
function test_design_patterns(): void
{
    $patternDetector = new PatternDetector();
    
    // Test Repository pattern
    $repositorySource = '<?php

namespace App\Repositories;

use App\Entities\User;

class UserRepository
{
    public function find(int $id): ?User
    {
        return null;
    }

    public function save(User $user): void
    {
    }

    public function delete(int $id): void
    {
    }
}';

    $tmpfile = tempnam(sys_get_temp_dir(), 'php_') . '.php';
    file_put_contents($tmpfile, $repositorySource);
    
    $fileNode = (new \PhpSyntax\Parser())->parse($repositorySource);
    $findings = $patternDetector->analyzeFile($fileNode, $tmpfile);
    
    unlink($tmpfile);
    
    $patternTypes = array_column($findings['patterns'], 'type');
    assert(in_array('repository_pattern', $patternTypes, true), 'Should detect repository pattern');
    
    // Test Factory pattern
    $factorySource = '<?php

namespace App\Factories;

class UserFactory
{
    public function create(string $name, string $email): object
    {
        return new \stdClass();
    }

    public function make(array $data): object
    {
        return new \stdClass();
    }

    public function build(): object
    {
        return new \stdClass();
    }
}';

    $tmpfile = tempnam(sys_get_temp_dir(), 'php_') . '.php';
    file_put_contents($tmpfile, $factorySource);
    
    $fileNode = (new \PhpSyntax\Parser())->parse($factorySource);
    $findings = $patternDetector->analyzeFile($fileNode, $tmpfile);
    
    unlink($tmpfile);
    
    $patternTypes = array_column($findings['patterns'], 'type');
    assert(in_array('factory_pattern', $patternTypes, true), 'Should detect factory pattern');
    
    echo "✓ Design patterns detection test passed\n";
}

/**
 * Run all tests
 */
function run_tests(): void
{
    echo "Running PatternDetector tests...\n\n";
    
    try {
        test_pattern_detector_with_sample_app();
        test_pattern_detector_single_class();
        test_tight_coupling_detection();
        test_static_overuse_detection();
        test_pattern_configuration();
        test_layer_violation_detection();
        test_design_patterns();
        
        echo "\n✓ All PatternDetector tests passed!\n";
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
