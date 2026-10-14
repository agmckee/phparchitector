# phparchitector - PHP Architecture Validator

A command-line tool for validating PHP codebase architecture against defined rules.

## Installation

This tool is the phparchitector package. When installed via Composer, it will be available at `vendor/bin/phparchitector`.

## Usage

```bash
# Show help
phparchitector --help

# Show version
phparchitector --version

# Analyze current project's src/ directory
phparchitector

# Analyze a specific directory
phparchitector /path/to/project

# Analyze with custom output directory
phparchitector --output=./reports

# Analyze quietly (exit code only)
phparchitector --quiet

# Generate JSON only (no markdown reports)
phparchitector --json

# Combine options
phparchitector /path/to/project --output=./reports --quiet
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `--help`, `-h` | Show help message | - |
| `--version`, `-v` | Show version information | - |
| `--target=TARGET` | Directory to analyze | `./src` |
| `--output=DIR` | Output directory for reports | `tools/self-analysis/reports/` |
| `--json` | Output JSON only (no markdown reports) | false |
| `--quiet`, `-q` | Suppress output (exit code only) | false |

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success - All analyses completed successfully |
| 1 | Failure - Analysis or report generation failed |

## Output

When run without `--json` flag, the following files are generated in the output directory:

- `summary.md` - Executive summary with overall score
- `architecture.md` - Layer structure and dependency analysis
- `dependencies.md` - Dependency graph details
- `patterns.md` - Pattern and anti-pattern detection results
- `metrics.md` - File and class metrics
- `raw_classanalyzer.json` - Raw ClassAnalyzer output
- `raw_dependencyanalyzer.json` - Raw DependencyAnalyzer output
- `raw_patterndetector.json` - Raw PatternDetector output
- `analysis_summary.json` - Summary of analysis results
- `normalized_data.json` - Normalized data for processing

## Implementation

This is a thin wrapper around the self-analysis tools in `tools/self-analysis/`. It:

1. Parses command-line arguments
2. Locates the self-analysis tool directory
3. Runs `run-analysis.php` with the specified target and output
4. Optionally runs `generate-report.php` to create markdown reports
5. Returns the appropriate exit code

## License

This tool is the phparchitector package and uses the MIT license.
