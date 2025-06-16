# LearningObjectAdapter Tests

This directory contains comprehensive tests for the LearningObjectAdapter module.

## Test Structure

```
test/
├── README.md                                    # This file
├── bootstrap.php                               # Test bootstrap
├── phpunit.xml                                # PHPUnit configuration
└── LearningObjectAdapterTest/
    ├── Form/
    │   └── ConfigFormTest.php                 # Configuration form tests
    ├── Integration/
    │   └── LearningObjectWorkflowTest.php     # End-to-end workflow tests
    ├── Media/
    │   └── Ingester/
    │       └── LearningObjectTest.php         # Media ingester tests
    ├── Service/
    │   └── ScormPackageManagerTest.php        # SCORM package manager tests
    └── ModuleTest.php                         # Main module tests
```

## Test Coverage

### ModuleTest.php
- Tests module configuration loading
- Tests event listener attachment
- Tests media deletion event handling
- Tests directory cleanup functionality
- Tests edge cases (missing data, non-existent directories)

### Service/ScormPackageManagerTest.php
- Tests SCORM package validation
- Tests package extraction with security checks
- Tests SCORM metadata extraction
- Tests directory traversal attack prevention
- Tests error handling for invalid packages

### Media/Ingester/LearningObjectTest.php
- Tests ingester metadata (label, description, file extensions)
- Tests file upload validation
- Tests SCORM and eXeLearning package processing
- Tests form generation
- Tests error handling for invalid files

### Form/ConfigFormTest.php
- Tests configuration form creation
- Tests form element validation
- Tests form data handling

### Integration/LearningObjectWorkflowTest.php
- Tests complete workflow from ingestion to deletion
- Tests multiple file handling
- Tests nested directory structures
- Tests cleanup verification

## Running Tests

### Prerequisites
- PHP 7.4 or higher
- PHPUnit 9.x or higher
- Composer dependencies installed

### Run All Tests
```bash
cd test
../vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
# Run only unit tests
../vendor/bin/phpunit --testsuite LearningObjectAdapterTest

# Run specific test class
../vendor/bin/phpunit LearningObjectAdapterTest/ModuleTest.php

# Run specific test method
../vendor/bin/phpunit --filter testHandleMediaDeletionWithLearningObjectMedia
```

### Run Tests with Coverage
```bash
../vendor/bin/phpunit --coverage-html coverage/
```

### Run Tests with Verbose Output
```bash
../vendor/bin/phpunit --verbose
```

## Test Features

### Mocking
- Uses PHPUnit's built-in mocking framework
- Mocks Omeka services and dependencies
- Creates realistic test scenarios without external dependencies

### Temporary Files
- Creates temporary directories for file system tests
- Automatically cleans up after each test
- Tests real file operations safely

### Security Testing
- Tests directory traversal attack prevention
- Validates file type checking
- Tests secure extraction processes

### Integration Testing
- Tests complete workflows end-to-end
- Verifies proper cleanup after operations
- Tests multiple file scenarios

## Key Test Scenarios

### Media Deletion Cleanup
1. **Valid Learning Object**: Tests that directories are properly deleted when a learning object media is removed
2. **Non-Learning Object**: Ensures other media types are not affected
3. **Missing Data**: Handles cases where extraction path is missing
4. **Non-existent Directory**: Gracefully handles cleanup of already-deleted directories

### SCORM Package Processing
1. **Valid Packages**: Tests successful processing of valid SCORM packages
2. **Invalid Packages**: Tests rejection of invalid or malformed packages
3. **Security**: Tests prevention of directory traversal attacks
4. **Metadata Extraction**: Tests proper extraction of SCORM metadata

### File Upload Handling
1. **Valid Files**: Tests successful upload and processing
2. **Invalid Types**: Tests rejection of non-zip files
3. **eXeLearning Support**: Tests both SCORM and eXeLearning formats
4. **Error Handling**: Tests proper error reporting

## Debugging Tests

### Enable Debug Output
```bash
../vendor/bin/phpunit --debug
```

### Stop on First Failure
```bash
../vendor/bin/phpunit --stop-on-failure
```

### Run Tests in Isolation
```bash
../vendor/bin/phpunit --process-isolation
```

## Contributing

When adding new features to the module:

1. **Add corresponding tests** for new functionality
2. **Update existing tests** if behavior changes
3. **Maintain test coverage** above 80%
4. **Follow naming conventions** for test methods
5. **Add integration tests** for complex workflows

### Test Naming Convention
- Test classes: `{ClassName}Test`
- Test methods: `test{MethodName}With{Scenario}`
- Example: `testHandleMediaDeletionWithLearningObjectMedia`

### Test Organization
- **Unit tests**: Test individual methods/classes in isolation
- **Integration tests**: Test complete workflows
- **Mock external dependencies**: Don't rely on actual Omeka installation
- **Clean up resources**: Always clean up temporary files/directories
