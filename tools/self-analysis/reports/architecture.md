# Architecture Analysis

**Analysis Date:** 2026-10-14 21:51:08

## Layer Structure

### Util Layer

- Class count: 5
- Total lines: 413
- Average lines per class: 82.6

**Classes:**

- App\Util\AstVisitor (89 lines)
- App\Util\UseStatementExtractor (125 lines)
- App\Util\CodeMetrics (74 lines)
- App\Util\FqcnUtils (61 lines)
- App\Util\FileSystemUtils (64 lines)

### Model Layer

- Class count: 12
- Total lines: 613
- Average lines per class: 51.1

**Classes:**

- App\Model\MethodInfo (47 lines)
- App\Model\PatternThresholds (38 lines)
- App\Model\Graph\Node (43 lines)
- App\Model\Graph\DependencyGraph (138 lines)
- App\Model\Graph\Edge (45 lines)
- App\Model\DependencyInfo (38 lines)
- App\Model\LayerConfig (32 lines)
- App\Model\PatternFinding (44 lines)
- App\Model\ConstantInfo (32 lines)
- App\Model\ClassInfo (59 lines)
- App\Model\PatternConfig (59 lines)
- App\Model\PropertyInfo (38 lines)

### Analyzer Layer

- Class count: 16
- Total lines: 2980
- Average lines per class: 186.3

**Classes:**

- App\Analyzer\Dependency\DependencyVisitor (329 lines)
- App\Analyzer\Dependency\DependencyExtractor (372 lines)
- App\Analyzer\Dependency\CircularDependencyDetector (147 lines)
- App\Analyzer\Dependency\DependencyResolver (129 lines)
- App\Analyzer\Dependency\DependencyGraphBuilder (146 lines)
- App\Analyzer\Pattern\AntiPatternDetector (167 lines)
- App\Analyzer\Pattern\ClassInfoExtractor (164 lines)
- App\Analyzer\Pattern\LayerViolationDetector (131 lines)
- App\Analyzer\Pattern\DirectoryPatternAnalyzer (156 lines)
- App\Analyzer\Pattern\DesignPatternDetector (182 lines)
- App\Analyzer\Pattern\ClassPatternAnalyzer (81 lines)
- App\Analyzer\AbstractAnalyzer (73 lines)
- App\Analyzer\ClassInfoVisitor (238 lines)
- App\Analyzer\PatternDetector (171 lines)
- App\Analyzer\DependencyAnalyzer (142 lines)
- App\Analyzer\ClassAnalyzer (352 lines)

### Other Layer

- Class count: 0

## Dependency Analysis

- Total dependencies: 133
- Internal dependencies: 107
- External dependencies: 26

### Most Depended Upon Classes

- PhpSyntax\Nodes\Statement\ClassNode: 16 dependents
- PhpSyntax\Nodes\FileNode: 10 dependents
- App\Analyzer\Dependency\DependencyResolver: 9 dependents
- App\Model\ClassInfo: 9 dependents
- App\Analyzer\Dependency\DependencyExtractor: 6 dependents
- PhpSyntax\Parser: 6 dependents
- App\Model\PatternThresholds: 6 dependents
- App\Analyzer\Pattern\ClassPatternAnalyzer: 5 dependents
- App\Analyzer\Pattern\LayerViolationDetector: 4 dependents
- PhpSyntax\Nodes\ModifiersNode: 3 dependents

### Classes with Most Dependencies

- App\Analyzer\ClassAnalyzer: 15 dependencies
- App\Analyzer\ClassInfoVisitor: 14 dependencies
- App\Analyzer\DependencyAnalyzer: 14 dependencies
- App\Analyzer\Dependency\DependencyVisitor: 11 dependencies
- App\Analyzer\Pattern\ClassPatternAnalyzer: 10 dependencies
- App\Analyzer\PatternDetector: 10 dependencies
- App\Analyzer\Dependency\DependencyExtractor: 8 dependencies
- App\Analyzer\Pattern\DirectoryPatternAnalyzer: 8 dependencies
- App\Model\PatternConfig: 8 dependencies
- App\Analyzer\Pattern\ClassInfoExtractor: 6 dependencies
