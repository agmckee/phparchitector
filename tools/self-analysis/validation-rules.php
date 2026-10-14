<?php declare(strict_types=1);

/**
 * Architectural validation rules for lossless-analysis codebase.
 * 
 * This file defines the architectural constraints and quality rules
 * that the codebase should adhere to.
 */

return [
    // File size limits
    'file_size' => [
        'max_lines' => 1000,
        'warning_threshold' => 500,
        'max_file_size_bytes' => 50000, // 50KB
    ],
    
    // Code quality constraints
    'code_quality' => [
        'max_circular_dependencies' => 0,
        'max_layer_violations' => 0,
        'max_code_duplication_percent' => 5,
        'max_class_complexity' => 20, // Cyclomatic complexity
    ],
    
    // Forbidden patterns (should not appear in code)
    'forbidden_patterns' => [
        'God Class' => [
            'description' => 'Classes with too many responsibilities',
            'max_methods' => 20,
            'max_properties' => 30,
            'max_lines' => 1000,
        ],
        'Tight Coupling' => [
            'description' => 'Classes with too many direct dependencies',
            'max_dependencies' => 15,
        ],
        'Static Overuse' => [
            'description' => 'Excessive use of static methods/properties',
            'max_static_methods_percent' => 30,
        ],
        'Circular Dependency' => [
            'description' => 'Classes that depend on each other circularly',
            'severity' => 'critical',
        ],
        'Duplicate Code' => [
            'description' => 'Code duplication above threshold',
            'threshold_percent' => 5,
        ],
    ],
    
    // Required patterns (should appear in code)
    'required_patterns' => [
        'Dependency Injection' => [
            'description' => 'Dependencies should be injected via constructor',
            'min_coverage' => 80, // 80% of classes should use DI
        ],
        'Layered Architecture' => [
            'description' => 'Code should follow Util -> Model -> Analyzer layering',
            'severity' => 'critical',
        ],
        'Type Safety' => [
            'description' => 'Classes should have proper type hints',
            'min_type_coverage' => 90,
        ],
        'SOLID Principles' => [
            'description' => 'Code should follow SOLID principles',
            'Single Responsibility' => [
                'max_methods_per_class' => 10,
            ],
            'Open Closed' => [
                'description' => 'Classes should be open for extension, closed for modification',
            ],
            'Liskov Substitution' => [
                'description' => 'Subtypes should be substitutable for base types',
            ],
            'Interface Segregation' => [
                'max_methods_per_interface' => 5,
            ],
            'Dependency Inversion' => [
                'description' => 'High-level modules should not depend on low-level modules',
            ],
        ],
    ],
    
    // Layer definitions and rules
    'layers' => [
        'Util' => [
            'description' => 'Utility classes - helper functions and common utilities',
            'namespace_pattern' => 'PhpArchitector\\Util\\',
            'can_depend_on' => [], // Util classes should have minimal external dependencies
            'can_be_depended_on_by' => ['Model', 'Analyzer'],
            'allowed_dependency_types' => ['php_builtin', 'composer'],
        ],
        'Model' => [
            'description' => 'Data Transfer Objects and domain models',
            'namespace_pattern' => 'PhpArchitector\\Model\\',
            'can_depend_on' => ['Util'],
            'can_be_depended_on_by' => ['Analyzer'],
            'allowed_dependency_types' => ['php_builtin', 'composer', 'Util'],
        ],
        'Analyzer' => [
            'description' => 'Analyzer classes - perform static analysis',
            'namespace_pattern' => 'PhpArchitector\\Analyzer\\',
            'can_depend_on' => ['Util', 'Model', 'Analyzer'],
            'can_be_depended_on_by' => [],
            'allowed_dependency_types' => ['php_builtin', 'composer', 'Util', 'Model'],
        ],
    ],
    
    // Layer dependency rules
    'layer_rules' => [
        // Directional rules: which layers can depend on which
        'allowed_dependencies' => [
            'Util' => ['Util', 'php_builtin', 'composer'],
            'Model' => ['Util', 'Model', 'php_builtin', 'composer'],
            'Analyzer' => ['Util', 'Model', 'Analyzer', 'php_builtin', 'composer'],
        ],
        
        // Forbidden dependencies (higher layer depending on lower is OK, but not reverse)
        'forbidden_dependencies' => [
            // Util should not depend on Model or Analyzer
            ['from' => 'Util', 'to' => 'Model', 'reason' => 'Util is lowest layer'],
            ['from' => 'Util', 'to' => 'Analyzer', 'reason' => 'Util is lowest layer'],
            
            // Model should not depend on Analyzer
            ['from' => 'Model', 'to' => 'Analyzer', 'reason' => 'Model is lower layer than Analyzer'],
        ],
    ],
    
    // Dependency type rules
    'dependency_rules' => [
        'allowed_types' => [
            'extends',
            'implements',
            'use',
            'type_hint',
            'new',
            'static_call',
            'method_call',
            'property_fetch',
        ],
        
        'forbidden_types' => [
            // No direct database access from Analyzer layer
            ['type' => 'direct_db_call', 'from_layer' => 'Analyzer'],
            // No static method calls to non-utility classes
            ['type' => 'static_call', 'to_layer' => 'Model', 'severity' => 'warning'],
        ],
    ],
    
    // Naming conventions
    'naming_conventions' => [
        'class' => [
            'pattern' => '/^[A-Z][a-zA-Z0-9]*$/',
            'description' => 'Classes should use PascalCase',
        ],
        'method' => [
            'pattern' => '/^[a-z][a-zA-Z0-9]*$/',
            'description' => 'Methods should use camelCase',
            'public_prefix' => 'no_prefix', // Don't require get/set prefixes
        ],
        'property' => [
            'pattern' => '/^[a-z][a-zA-Z0-9]*$/',
            'description' => 'Properties should use camelCase',
        ],
        'constant' => [
            'pattern' => '/^[A-Z][_A-Z0-9]*$/',
            'description' => 'Constants should use UPPER_SNAKE_CASE',
        ],
        'namespace' => [
            'pattern' => '/^[A-Z][a-zA-Z0-9]*(\\[A-Z][a-zA-Z0-9]*)*$/',
            'description' => 'Namespaces should use PascalCase segments',
        ],
    ],
    
    // Test coverage requirements (if applicable)
    'test_requirements' => [
        'min_coverage_percent' => 80,
        'min_unit_tests' => 10,
    ],
    
    // Documentation requirements
    'documentation' => [
        'require_class_docblocks' => true,
        'require_method_docblocks' => true,
        'require_param_types' => true,
        'require_return_types' => true,
    ],
];
