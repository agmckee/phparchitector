#!/bin/bash

# Run all tests in the Podman container

echo "Running tests in Podman container..."
echo "=================================="
echo ""

# Check if container is running
if ! podman ps --filter "name=php-analysis-dev" --format "{{.Names}}" | grep -q "php-analysis-dev"; then
    echo "Container 'php-analysis-dev' is not running."
    echo "Starting container..."
    podman start php-analysis-dev
    sleep 2
fi

# Run ClassAnalyzer tests
echo "=== ClassAnalyzer Tests ==="
podman exec php-analysis-dev php tests/unit/Analyzer/ClassAnalyzerTest.php

# Run DependencyAnalyzer tests
echo ""
echo "=== DependencyAnalyzer Tests ==="
podman exec php-analysis-dev php tests/unit/Analyzer/DependencyAnalyzerTest.php

# Run PatternDetector tests
echo ""
echo "=== PatternDetector Tests ==="
podman exec php-analysis-dev php tests/unit/Analyzer/PatternDetectorTest.php

echo ""
echo "All tests completed."
