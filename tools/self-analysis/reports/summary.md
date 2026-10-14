# Self-Analysis Report: lossless-analysis

**Analysis Date:** 2026-10-14 21:51:08

## Overall Score: **B-**

### Score Breakdown

- ❌ 17 circular dependency(ies) found
- ❌ 8 anti-pattern(s) detected

## File Metrics

- Total classes analyzed: 33
- Total lines of code: 4006
- Average lines per class: 121.4

## Dependency Metrics

- Total nodes: 53
- Total dependencies (edges): 133
- Circular dependencies: 17

## Pattern Metrics

- Patterns detected: 22
- Anti-patterns detected: 8
- Layer violations detected: 0

## Validation Results

- ✅ Passed: 5
- ❌ Failed: 17
- ⚠️  Warnings: 8

### Passed Checks

- ✅ All files under max line limit (1000)
- ✅ No layer violations detected
- ✅ No forbidden dependencies: Util -> Model
- ✅ No forbidden dependencies: Util -> Analyzer
- ✅ No forbidden dependencies: Model -> Analyzer

### Failed Checks

- ❌ Circular dependency: App\Analyzer\Dependency\DependencyResolver -> App\Analyzer\Dependency\DependencyVisitor
- ❌ Circular dependency: App\Analyzer\Dependency\DependencyResolver -> App\Analyzer\Dependency\DependencyExtractor
- ❌ Circular dependency: App\Analyzer\Dependency\DependencyExtractor -> App\Analyzer\Dependency\DependencyGraphBuilder
- ❌ Circular dependency: PhpSyntax\Nodes\Statement\ClassNode -> App\Analyzer\Pattern\ClassInfoExtractor
- ❌ Circular dependency: App\Analyzer\Pattern\ClassInfoExtractor -> App\Analyzer\Pattern\ClassPatternAnalyzer
- ❌ Circular dependency: PhpSyntax\Parser -> App\Analyzer\AbstractAnalyzer
- ❌ Circular dependency: App\Util\AstVisitor -> App\Analyzer\ClassInfoVisitor
- ❌ Circular dependency: App\Analyzer\AbstractAnalyzer -> App\Analyzer\PatternDetector
- ❌ Circular dependency: App\Analyzer\AbstractAnalyzer -> App\Analyzer\DependencyAnalyzer
- ❌ Circular dependency: App\Analyzer\AbstractAnalyzer -> App\Analyzer\ClassAnalyzer
- ❌ Circular dependency: App\Model\Graph\Node -> App\Model\Graph\DependencyGraph
- ❌ Circular dependency: App\Model\DependencyType -> App\Model\DependencyInfo
- ❌ Circular dependency: App\Model\PatternSeverity -> App\Model\LayerConfig
- ❌ Circular dependency: App\Model\PatternSeverity -> App\Model\PatternFinding
- ❌ Circular dependency: App\Model\PatternThresholds -> App\Model\PatternConfig
- ❌ Circular dependency: PhpSyntax\Nodes\FileNode -> App\Util\UseStatementExtractor
- ❌ Circular dependency: PhpSyntax\Nodes\Statement\ClassNode -> App\Util\CodeMetrics

### Warnings

- ⚠️  Anti-pattern detected: tight_coupling
- ⚠️  Anti-pattern detected: tight_coupling
- ⚠️  Anti-pattern detected: tight_coupling
- ⚠️  Anti-pattern detected: tight_coupling
- ⚠️  Anti-pattern detected: tight_coupling
- ⚠️  Anti-pattern detected: tight_coupling
- ⚠️  Anti-pattern detected: tight_coupling
- ⚠️  Anti-pattern detected: static_overuse

---

## Conclusion

⚠️  Some validations failed. Please review the failed checks above.

Score: **B-**
