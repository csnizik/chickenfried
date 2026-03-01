---
applyTo: "**/tests/**/*.php"
---
All custom code must have accompanying PHPUnit tests that follow Drupal testing standards.

## Test Class Structure

```php
<?php

namespace Drupal\Tests\{module_name}\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests {ClassName}.
 *
 * @group {module_name}
 * @coversDefaultClass \Drupal\{module_name}\{ClassName}
 */
class {ClassName}Test extends UnitTestCase {

  /**
   * The service under test.
   */
  protected $serviceUnderTest;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->serviceUnderTest = new ServiceClass();
  }

  /**
   * Tests methodName().
   *
   * @covers ::methodName
   */
  public function testMethodName(): void {
    $result = $this->serviceUnderTest->methodName('input');
    $this->assertEquals('expected', $result);
  }
}
```

## Test Types

### Unit Tests
**Location:** `tests/src/Unit/`
- Test single classes in isolation
- Mock all dependencies
- Fast execution
- No database needed

### Kernel Tests
**Location:** `tests/src/Kernel/`
- Test with minimal Drupal bootstrap
- Database available
- Services available
- Use for entity operations

### Functional Tests
**Location:** `tests/src/Functional/`
- Full Drupal installation
- Test complete workflows
- Browser simulation
- Slowest but most comprehensive

## Mocking Dependencies

```php
use PHPUnit\Framework\MockObject\MockObject;

protected function setUp(): void {
  parent::setUp();

  // Mock entity type manager
  $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

  // Mock storage
  $storage = $this->createMock(EntityStorageInterface::class);
  $this->entityTypeManager
    ->method('getStorage')
    ->with('node')
    ->willReturn($storage);

  // Inject mocks
  $this->serviceUnderTest = new MyService($this->entityTypeManager);
}
```

## Testing Entity Operations

```php
/**
 * Tests entity creation.
 *
 * @covers ::createEntity
 */
public function testCreateEntity(): void {
  $entity = $this->createMock(NodeInterface::class);
  $entity->method('save')->willReturn(SAVED_NEW);

  $storage = $this->createMock(NodeStorageInterface::class);
  $storage->method('create')->willReturn($entity);

  $this->entityTypeManager
    ->method('getStorage')
    ->with('node')
    ->willReturn($storage);

  $result = $this->serviceUnderTest->createEntity([
    'type' => 'article',
    'title' => 'Test',
  ]);

  $this->assertEquals(SAVED_NEW, $result);
}
```

## Testing Exceptions

```php
/**
 * Tests exception handling.
 *
 * @covers ::methodThatThrows
 */
public function testMethodThrowsException(): void {
  $this->expectException(\InvalidArgumentException::class);
  $this->expectExceptionMessage('Invalid input');

  $this->serviceUnderTest->methodThatThrows('invalid');
}
```

## Data Providers

Use data providers for testing multiple scenarios:

```php
/**
 * Tests input validation.
 *
 * @dataProvider validationDataProvider
 * @covers ::validateInput
 */
public function testValidation($input, $expected): void {
  $result = $this->serviceUnderTest->validateInput($input);
  $this->assertEquals($expected, $result);
}

/**
 * Data provider for testValidation().
 */
public function validationDataProvider(): array {
  return [
    'valid email' => ['test@example.com', TRUE],
    'invalid email' => ['not-an-email', FALSE],
    'empty string' => ['', FALSE],
  ];
}
```

## Testing Drush Commands

```php
use Drush\TestTraits\DrushTestTrait;
use Drupal\Tests\KernelTestCase;

/**
 * Tests the Drush command.
 *
 * @group arsapps_tools
 */
class ExportCommandTest extends KernelTestCase {
  use DrushTestTrait;

  /**
   * Tests the export command.
   */
  public function testExportCommand(): void {
    $this->drush('arsapps:export-content', ['node', 'article']);
    $this->assertStringContainsString('Exported', $this->getOutput());
  }
}
```

## Running Tests

### Run all module tests:
```bash
ddev exec phpunit web/modules/custom/{module_name}
```

### Run specific test:
```bash
ddev exec phpunit web/modules/custom/{module_name}/tests/src/Unit/MyClassTest.php
```

### Run with coverage:
```bash
ddev exec phpunit --coverage-html reports/ web/modules/custom/{module_name}
```

## Assertions to Use

- `assertEquals($expected, $actual)` - Values are equal
- `assertSame($expected, $actual)` - Values are identical
- `assertTrue($condition)` - Condition is true
- `assertFalse($condition)` - Condition is false
- `assertNull($value)` - Value is null
- `assertEmpty($value)` - Value is empty
- `assertCount($count, $array)` - Array has count
- `assertInstanceOf($class, $object)` - Object is instance
- `assertStringContainsString($needle, $haystack)` - String contains
- `assertArrayHasKey($key, $array)` - Array has key

## Test Coverage Requirements

- Aim for 80%+ code coverage on custom code
- All public methods must be tested
- Test both success and failure paths
- Test edge cases and boundary conditions
- Mock external dependencies (APIs, file system)

## Common Pitfalls

- ❌ Don't test Drupal core functionality
- ❌ Don't make real database calls in Unit tests
- ❌ Don't test contrib module code
- ❌ Don't skip exception testing
- ✅ Do test your business logic
- ✅ Do mock all dependencies
- ✅ Do test edge cases
- ✅ Do use descriptive test names

## Testing Group-Aware Code

```php
/**
 * Tests group content association.
 *
 * @covers ::addToGroup
 */
public function testAddToGroup(): void {
  $group = $this->createMock(GroupInterface::class);
  $node = $this->createMock(NodeInterface::class);

  $group->expects($this->once())
    ->method('addContent')
    ->with($node, 'group_node:article')
    ->willReturn($this->createMock(GroupContentInterface::class));

  $this->serviceUnderTest->addToGroup($node, $group);
}
```

## CI/CD Integration

Tests run automatically in GitHub Actions:

```yaml
- name: Run PHPUnit tests
  run: |
    ddev exec phpunit --testdox --colors=always web/modules/custom/
```

## Documentation

- PHPUnit docs: https://phpunit.de/
- Drupal testing: https://www.drupal.org/docs/testing
- Write tests BEFORE implementing features (TDD)

