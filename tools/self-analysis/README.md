# Self-Analysis Framework

## Overview

This framework uses the **baseline version** (commit `454be49`) of lossless-analysis to analyze the **current refactored version** (HEAD). This approach provides an objective "before" perspective to validate that refactoring goals were achieved.

## Purpose

- ✅ Validates that refactoring achieved its goals objectively
- ✅ Works even if current code is broken
- ✅ Doesn't pollute the main project deliverable
- ✅ Provides metrics to prove architectural improvements

## Structure

```
tools/
└── self-analysis/
    ├── baseline/
    │   ├── src/              # Baseline analyzer source code (commit 454be49)
    │   │   └── Analyzer/
    │   │       ├── ClassAnalyzer.php
    │   │       ├── DependencyAnalyzer.php
    │   │       └── PatternDetector.php
    │   └── vendor/           # Baseline dependencies
    ├── config.php          # Configuration (paths, exclusions, rules)
    ├── validation-rules.php # Architectural validation rules
    ├── run-analysis.php    # Main analysis execution script
    ├── generate-report.php # Report generator
    ├── reports/            # Generated reports (output directory)
    │   ├── summary.md       # Executive summary with score
    │   ├── architecture.md  # Architecture metrics
    │   ├── dependencies.md  # Dependency graph analysis
    │   ├── patterns.md      # Pattern detection results
    │   ├── metrics.md       # File/line metrics
    │   ├── raw_classanalyzer.json    # Raw ClassAnalyzer output
    │   ├── raw_dependencyanalyzer.json # Raw DependencyAnalyzer output
    │   ├── raw_patterndetector.json   # Raw PatternDetector output
    │   └── normalized_data.json      # Normalized data for reference
    └── README.md           # This file
```

## Configuration

The framework is configured via `config.php`. Key settings:

- **baseline_path**: Path to baseline analyzer (default: `tools/self-analysis/baseline`)
- **target_path**: Path to analyze (default: `src/`)
- **excludes**: Directories to exclude from analysis
- **analyzers**: List of analyzers to run
- **output_dir**: Where to save reports
- **verbosity**: Output verbosity level (0=quiet, 1=normal, 2=verbose)

## Quick Start

### 1. Run Analysis

```bash
# From the repository root
php tools/self-analysis/run-analysis.php
```

This will:
- Load baseline analyzers
- Analyze the current `src/` directory
- Save raw JSON output to `tools/self-analysis/reports/`
- Display progress and summary

### 2. Generate Reports

```bash
php tools/self-analysis/generate-report.php
```

This will:
- Load raw JSON data from reports/
- Generate markdown reports
- Validate against architectural rules
- Display generation summary

### 3. Run Everything

```bash
# Run analysis and generate reports
php tools/self-analysis/run-analysis.php && php tools/self-analysis/generate-report.php
```

## Reports

The framework generates the following reports:

### Markdown Reports (Human-readable)

1. **summary.md** - Executive summary
   - Overall score (A+, A, B+, etc.)
   - Key metrics
   - Validation results
   - Score breakdown

2. **architecture.md** - Architecture analysis
   - Layer structure (Util, Model, Analyzer)
   - Class counts per layer
   - Dependency analysis
   - Most depended-upon classes
   - Classes with most dependencies

3. **dependencies.md** - Dependency graph analysis
   - Graph overview (nodes, edges)
   - Full dependency graph in Mermaid format
   - Detailed node information

4. **patterns.md** - Pattern detection
   - Detected patterns
   - Anti-patterns
   - Layer violations

5. **metrics.md** - Code metrics
   - Class-level metrics table
   - Total and average counts
   - Size distribution

### JSON Data Files (Machine-readable)

- **raw_classanalyzer.json** - Raw output from ClassAnalyzer
- **raw_dependencyanalyzer.json** - Raw output from DependencyAnalyzer
- **raw_patterndetector.json** - Raw output from PatternDetector
- **normalized_data.json** - Normalized data for reference
- **analysis_summary.json** - Summary of analysis run

## Architectural Rules

The framework validates against rules defined in `validation-rules.php`:

### File Size
- Max lines: 1000
- Warning threshold: 500
- Max file size: 50KB

### Code Quality
- Max circular dependencies: 0
- Max layer violations: 0
- Max code duplication: 5%

### Layer Rules
The codebase follows a **Layered Architecture**:

```
┌─────────────────────┐
│      Analyzer        │  (Highest layer)
│  (Analysis classes)  │  - Can depend on: Util, Model, Analyzer
└──────────┬──────────┘
           │
┌──────────▼──────────┐
│        Model         │  (Middle layer)
│   (DTOs, Interfaces) │  - Can depend on: Util, Model
└──────────┬──────────┘
           │
┌──────────▼──────────┐
│        Util          │  (Lowest layer)
│   (Utilities, Helpers)│  - Can depend on: (minimal external)
└─────────────────────┘
```

**Forbidden Dependencies:**
- Util cannot depend on Model or Analyzer
- Model cannot depend on Analyzer

### Required Patterns
- Dependency Injection
- Layered Architecture
- Type Safety
- SOLID Principles

### Forbidden Patterns
- God Class (too many responsibilities)
- Tight Coupling (too many dependencies)
- Static Overuse
- Circular Dependencies
- Duplicate Code

## Usage Examples

### Run with Verbose Output

```bash
php tools/self-analysis/run-analysis.php 2>&1 | tee analysis.log
```

### Run Only Specific Analyzers

Edit `config.php` and modify the `analyzers` array:

```php
'analyzers' => [
    'ClassAnalyzer',
    // 'DependencyAnalyzer',
    // 'PatternDetector',
],
```

### Run on Different Directory

Edit `config.php` and change the `target_path`:

```php
'target_path' => __DIR__ . '/../../tests',
```

### Custom Exclusions

Edit `config.php` and modify the `excludes` array:

```php
'excludes' => [
    'tests/',
    'vendor/',
    'tools/',
    'tmp/',
],
```

## Expected Outcomes

When the framework runs successfully on the current codebase, you should see:

✅ All three analyzers run without errors  
✅ JSON files generated for each analyzer  
✅ Markdown reports generated  
✅ All architectural validations pass  
✅ No circular dependencies detected  
✅ No layer violations detected  
✅ No anti-patterns detected  
✅ Overall score: A+  

## Troubleshooting

### Error: "Analyzer class not found"

This means the baseline analyzer doesn't have the specified class. Ensure:
- The baseline directory has the correct source files
- The autoloader is working correctly
- The class name is correct in the config

### Error: "Target directory does not exist"

Check that the `target_path` in `config.php` points to a valid directory.

### Error: "No PHP files found"

Check that:
- The target directory contains PHP files
- The exclusions aren't too broad
- The directory is readable

### Report Generation Fails

This can happen if the raw JSON data format is unexpected. Check:
- The raw JSON files exist in the reports directory
- The JSON is valid (can be parsed)
- The data structure matches what the report generator expects

## Implementation Notes

- Uses **absolute paths** for everything
- Handles cases where baseline analyzers might fail on current code
- Generates reports even if some analyzers fail
- Uses try-catch for error handling
- Includes timestamps in reports
- Output is human-readable

## Future Enhancements

- [ ] Composer script integration
- [ ] CI/CD integration (GitHub Actions)
- [ ] Historical comparison (compare with previous runs)
- [ ] Visual dependency graph generation
- [ ] HTML report generation
- [ ] PDF report generation
- [ ] Automated refactoring suggestions

## License

This framework is part of the lossless-analysis project and inherits its license.

## References

- [Self-Analysis Implementation Plan](../SELF_ANALYSIS_PLAN.md)
- [Baseline README](baseline/README.md)
- [Validation Rules](validation-rules.php)
