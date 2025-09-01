#!/bin/bash

# Script to run PHP unit tests and capture results

set -e  # Exit on any error (but we'll handle specific cases)

echo "Starting PHP Unit Tests..."
echo "=========================="

# Function to check if a service is running
check_service() {
    local url=$1
    local timeout=${2:-60}
    echo "Checking if $url is accessible..."
    
    for i in $(seq 1 $timeout); do
        if curl -f "$url" &>/dev/null; then
            echo "✓ Service at $url is ready"
            return 0
        fi
        echo "Waiting for service... ($i/$timeout)"
        sleep 2
    done
    echo "✗ Service at $url is not accessible after ${timeout} attempts"
    return 1
}

# Function to run a test suite and capture results
run_test_suite() {
    local suite_name=$1
    local npm_command=$2
    local output_file=$3
    
    echo "Running $suite_name..."
    
    # Run the test and capture both stdout and stderr
    if timeout 300 $npm_command > "$output_file" 2>&1; then
        echo "✓ $suite_name: PASSED"
        return 0
    else
        local exit_code=$?
        echo "✗ $suite_name: FAILED (exit code: $exit_code)"
        return $exit_code
    fi
}

# Check if wp-env is running, start if needed
if ! check_service "http://localhost:8902" 5; then
    echo "WordPress test environment not accessible. Starting wp-env..."
    
    # Try to start wp-env
    if npm run wp-env start; then
        echo "wp-env started successfully"
        check_service "http://localhost:8902" 30
    else
        echo "Failed to start wp-env. Will attempt to run tests anyway..."
    fi
fi

# Create results directory
mkdir -p test-results

# Initialize status variables
UNIT_STATUS="NOT_RUN"
INTEGRATION_STATUS="NOT_RUN"
UNIT_EXIT_CODE=1
INTEGRATION_EXIT_CODE=1

# Run unit tests and capture output
if run_test_suite "Unit Tests" "npm run test:unit" "test-results/unit-output.txt"; then
    UNIT_STATUS="PASSED"
    UNIT_EXIT_CODE=0
else
    UNIT_STATUS="FAILED"
    UNIT_EXIT_CODE=1
fi

# Run integration tests and capture output
if run_test_suite "Integration Tests" "npm run test:integration" "test-results/integration-output.txt"; then
    INTEGRATION_STATUS="PASSED"
    INTEGRATION_EXIT_CODE=0
else
    INTEGRATION_STATUS="FAILED"
    INTEGRATION_EXIT_CODE=1
fi

# Generate summary report
cat > test-results/summary.md << EOF
# PHP Unit Test Results

## Summary
- **Unit Tests**: $UNIT_STATUS
- **Integration Tests**: $INTEGRATION_STATUS
- **Generated**: $(date)
- **Environment**: $(uname -s) $(uname -r)

## Unit Test Details
### Status: $UNIT_STATUS
\`\`\`
$(head -n 50 test-results/unit-output.txt 2>/dev/null || echo "No output captured")
\`\`\`

## Integration Test Details  
### Status: $INTEGRATION_STATUS
\`\`\`
$(head -n 50 test-results/integration-output.txt 2>/dev/null || echo "No output captured")
\`\`\`

## Debug Information
- WordPress test URL: http://localhost:8902
- Test environment: wp-env
- Script executed: $(basename "$0")
EOF

# Generate a simple text summary as well
cat > test-results/summary.txt << EOF
PHP Unit Test Results Summary
=============================
Unit Tests: $UNIT_STATUS
Integration Tests: $INTEGRATION_STATUS
Generated: $(date)
EOF

# Display results
echo ""
echo "=========================="
echo "TEST RESULTS SUMMARY"
echo "=========================="
echo "Unit Tests: $UNIT_STATUS"
echo "Integration Tests: $INTEGRATION_STATUS"
echo ""
echo "Detailed results saved to:"
echo "  - test-results/summary.md (Markdown format)"
echo "  - test-results/summary.txt (Plain text format)"
echo "  - test-results/unit-output.txt (Unit test output)"
echo "  - test-results/integration-output.txt (Integration test output)"

# If running in CI, also output to GitHub Actions format
if [ "$GITHUB_ACTIONS" = "true" ]; then
    echo "::group::Test Results Summary"
    cat test-results/summary.txt
    echo "::endgroup::"
    
    if [ $UNIT_EXIT_CODE -eq 0 ]; then
        echo "::notice::Unit tests passed"
    else
        echo "::error::Unit tests failed"
    fi
    
    if [ $INTEGRATION_EXIT_CODE -eq 0 ]; then
        echo "::notice::Integration tests passed"
    else
        echo "::error::Integration tests failed"
    fi
fi

# Return appropriate exit code
if [ $UNIT_EXIT_CODE -eq 0 ] && [ $INTEGRATION_EXIT_CODE -eq 0 ]; then
    echo "All tests passed!"
    exit 0
else
    echo "Some tests failed."
    exit 1
fi