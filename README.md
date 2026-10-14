# PHP Architecture Analysis Tool

A static analysis tool for PHP projects that identifies architectural patterns, anti-patterns, and drift.

## Overview

This tool uses the `phpsyntax/phpsyntax` library to parse PHP code into a lossless concrete syntax tree (CST) and perform architectural analysis.

## Current Status

This is a pre-alpha project at present.

## Architecture Analysis Goals

### Pattern Detection
- Layered architecture compliance
- Dependency injection patterns
- SOLID principle adherence
- Design pattern usage

### Anti-Pattern Detection
- God classes (too many responsibilities)
- Circular dependencies
- Tight coupling
- Static method overuse
- Layer violations

### Drift Detection
- Track architectural changes
- Detect violations of established patterns
- Report on codebase evolution

### Refactoring Support
- Suggest improvements
- Generate patches
- Automated refactoring where possible

## License

MIT License
