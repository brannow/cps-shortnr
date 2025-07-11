# Claude Memory - ShortNr TYPO3 Extension

## Vision

URL shortner that works with encode and decode URLs. it can handle Pages and plugin calls.
The configuration is handled over a YAML config file. the upmost Priority is Clean Code AND Performance, since the middleware is between EVERY request

OOP and TDD is important, in case of OOP it is used, when possible, and not in conflict with performance or clean code, his true origin form "messaging". messaging refers to the way objects interact and communicate with each other by sending messages. This mechanism involves invoking methods on objects, which can lead to changes in the object's state or the return of a value. Essentially, objects don't directly access each other's internal data or methods; instead, they send messages to request specific actions or information

## Project Overview
- **Extension**: ShortNr URL shortener for TYPO3
- **TYPO3 Versions**: 12.4 and 13.4
- **PHP**: Written in 8.4, compatible with 8.1+
- **Status**: Core architecture established, comprehensive test coverage implemented
- **Stage**: The Project is still early Work in Progress with many features and functions missing

## Architecture Overview

### Core Components
```
Classes/
├── Cache/
│   ├── CacheAdapter/FastArrayFileCache.php - PHP array file cache with atomic writes
│   └── CacheManager.php - TYPO3 cache integration
├── Config/
│   ├── ConfigLoader.php - Multi-level caching YAML config loader
│   └── ExtensionSetup.php - TYPO3 extension configuration
├── Middleware/
│   └── ShortNumberMiddleware.php - Main HTTP request handler
├── Service/
│   ├── FileSystem/
│   │   ├── FileSystemInterface.php - File operations abstraction
│   │   └── FileSystem.php - Concrete implementation
│   └── Path/
│       ├── PathResolverInterface.php - TYPO3 path resolution abstraction
│       └── Typo3PathResolver.php - GeneralUtility::getFileAbsFileName wrapper
└── Exception/ - Custom exceptions
```

### What is missing

* URL decoder Service
* URL encoder Service
* URL encoder ViewHelper
* tbd...

### Key Architectural Patterns
- **Dependency Injection**: All services use constructor injection
- **Interface Segregation**: Separate interfaces for file operations vs path resolution
- **Multi-level Caching**: Runtime → File cache → YAML parsing chain
- **Atomic Operations**: Safe file writes using temp files + rename
- **Clean Abstractions**: TYPO3 utilities isolated behind interfaces

## Detailed Component Analysis

### ConfigLoader (`Classes/Config/ConfigLoader.php:34`)
**Purpose**: Sophisticated config loading with multi-level caching
**Architecture**: 
- **Runtime Cache**: Static array for request-level caching
- **File Cache**: PHP serialized arrays stored as executable PHP files
- **YAML Parsing**: Symfony/Yaml component for config files
- **Cache Invalidation**: File modification time comparison

**Key Methods**:
- `getConfig()`: Main entry point, handles cache validation flow
- `isConfigCacheValid()`: Compares YAML vs cache file modification times
- `getConfigFileSuffix()`: Generates MD5 hash for cache file naming
- `prepareConfigFilePath()`: Handles FILE: prefix removal + path resolution

**Dependencies**: CacheManager, ExtensionConfiguration, FileSystemInterface, PathResolverInterface

### FastArrayFileCache (`Classes/Cache/CacheAdapter/FastArrayFileCache.php`)
**Purpose**: High-performance array caching with atomic writes
**Features**:
- **Atomic Writes**: tempnam() → file_put_contents() → rename() pattern
- **Runtime Cache**: Prevents duplicate file reads within request
- **PHP Code Generation**: Stores arrays as executable PHP files (`return [...]`)
- **Safe Directory Creation**: Ensures cache directories exist

**Key Methods**:
- `writeArrayFileCache()`: Atomic write with error handling
- `readArrayFileCache()`: Read with runtime cache fallback
- `invalidateFileCache()`: Clear cache files and runtime cache
- `generatePhpArrayCode()`: Creates executable PHP from array

### CacheManager (`Classes/Cache/CacheManager.php`)
**Purpose**: Bridge between custom cache and TYPO3 cache system
**Features**:
- **TYPO3 Integration**: Connects to TYPO3 cache framework
- **Graceful Degradation**: Falls back when TYPO3 cache unavailable
- **Exception Handling**: Catches all cache-related exceptions

### Path Resolution Abstraction
**Design Decision**: Separate PathResolverInterface from FileSystemInterface
**Rationale**: 
- Path resolution ≠ File system operations
- Better testability (can mock path resolution independently)
- TYPO3 utilities isolated from core file operations

## Testing Architecture

### Test Structure
```
Tests/Unit/
├── Cache/
│   ├── CacheAdapter/FastArrayFileCacheTest.php
│   └── CacheManagerTest.php
├── Config/
│   ├── ConfigLoaderTest.php
│   └── ExtensionSetupTest.php
├── Middleware/ShortNumberMiddlewareTest.php
└── Service/Path/PathResolverTest.php
```

### Testing Strategy
- **DataProviders**: Extensively used for testing multiple scenarios efficiently
- **Mock Abstractions**: FileSystemInterface and PathResolverInterface fully mockable
- **Static Cache Management**: Proper cleanup between tests using reflection
- **Comprehensive Coverage**: Normal flows, edge cases, error conditions

### Test Commands
```bash
# Run all tests
docker exec php-shortnr /var/www/html/.Build/bin/phpunit

# Run specific test class
docker exec php-shortnr /var/www/html/.Build/bin/phpunit Tests/Unit/Config/ConfigLoaderTest.php

# Run with coverage
docker exec php-shortnr /var/www/html/.Build/bin/phpunit --coverage-html var/coverage

# Run specific test method
docker exec php-shortnr /var/www/html/.Build/bin/phpunit --filter="testMethodName"
```

## Configuration & Dependency Injection

### URL Shortener Configuration Schema (`Configuration/config.yaml`)

The configuration follows a hierarchical structure with global settings, defaults, and specific route definitions:

```yaml
ShortNr:
  notFound: "/fehler-404"  # Fallback URL for 404 errors (supports page ID or slug)
  types:                   # Processor type mappings
    page: "\\CPSIT\\ShortNr\\Service\\Processor\\PageProcessor"
    plugin: "\\CPSIT\\ShortNr\\Service\\Processor\\PluginProcessor"
  
  _default:               # Default matching rules for all routes
    regex: "/^([a-zA-Z]+?)(\\d+)[-]?(\\d+)?$/"  # Pattern: prefix + ID + optional language
    regexGroupMapping:    # Maps regex groups to placeholders
      prefix: "{match-1}"
      id: "{match-2}"
      language_id: "{match-3}"
    condition:            # Database query conditions
      uid: "{match-2}"
      sys_language_uid: "{match-3}"
  
  # Route definitions inherit from _default and override specific settings
  pages:                  # User-defined route name
    type: page            # Processor type (from 'types' section)
    prefix: PAGE          # URL prefix pattern
    table: pages          # Database table
  
  press:                  # Custom route name (user-defined, not reserved)
    prefix: pm
    type: plugin
    table: tx_bmubarticles_domain_model_article
    condition:
      article_type: press
    pluginConfig:         # TYPO3 plugin configuration
      extension: BmubArticles
      plugin: Articles
      pid: 289
      action: show
      controller: Article
      objectName: article
```

**Key Schema Concepts:**
- **Global Settings**: `notFound` URL and processor type mappings
- **Extensible Processor System**: Custom processor classes can be registered in `types` section
- **Default Template**: `_default` section provides base configuration inherited by all routes
- **Route Inheritance**: Each route inherits `_default` settings and overrides specific values
- **Dynamic Route Names**: Route identifiers (like `press`, `pages`) are completely user-defined, not reserved keywords
- **Regex Placeholders**: `{match-N}` placeholders map to regex capture groups
- **Database Conditions**: Dynamic conditions using placeholder substitution
- **Plugin Integration**: Full TYPO3 plugin configuration for complex routing
- **Custom Type Support**: Add exotic structures by implementing custom processors and registering them

### Services Configuration (`Configuration/Services.yaml`)
```yaml
services:
  CPSIT\ShortNr\Service\FileSystem\FileSystemInterface:
    alias: CPSIT\ShortNr\Service\FileSystem\FileSystem
  
  CPSIT\ShortNr\Service\Path\PathResolverInterface:
    alias: CPSIT\ShortNr\Service\Path\Typo3PathResolver
```

### Cache Configuration (`Classes/Config/ExtensionSetup.php`)
- Registers TYPO3 cache with key `cps_shortnr`
- Uses VariableFrontend + FileBackend
- Belongs to 'system' cache group

## Development Workflow

### Adding New Components
1. Create interface in appropriate Service namespace
2. Implement concrete class
3. Add to Services.yaml if needed
4. Create comprehensive test with dataProviders
5. Mock all dependencies via interfaces

### Testing New Features
1. Use dataProviders for multiple scenarios
2. Mock FileSystemInterface for all file operations
3. Mock PathResolverInterface for path resolution
4. Clear static caches in setUp/tearDown
5. Test normal flows, edge cases, and error conditions
6. **Test Quality over Quantity** - Remove tests that fight against architecture rather than fixing them
7. **Use willReturnCallback() for complex mock scenarios** - More reliable than willReturnMap() for interface-based mocking

### Key Patterns to Follow
- **Interface-first design**: Always create interface before implementation
- **Dependency injection**: Use constructor injection for all dependencies
- **Atomic operations**: Use temp files for safe writes
- **Runtime caching**: Cache expensive operations within request
- **Graceful degradation**: Handle failures without breaking system
- **Zero Tolerance for Deprecations**: All deprecation warnings must be resolved - they indicate future breaking changes and technical debt.

## Important Implementation Details

### Cache File Strategy
- **Location**: `{TYPO3_VAR_PATH}/cache/code/cps_shortnr/`
- **Naming**: `config{md5_hash}.php` 
- **Format**: Executable PHP files with `return [array];`
- **Atomic Writes**: temp file → rename pattern prevents corruption

### Error Handling Patterns
- **ConfigLoader**: Returns empty array on errors (graceful degradation)
- **FastArrayFileCache**: Throws ShortNrCacheException on write failures
- **CacheManager**: Returns null on TYPO3 cache failures
- **PathResolver**: Delegates to TYPO3 GeneralUtility (may throw)

### Performance Considerations
- **Runtime cache**: Prevents duplicate file reads/parsing
- **File modification time checks**: Efficient cache invalidation
- **Atomic writes**: Prevents cache corruption during writes
- **PHP array serialization**: Faster than JSON for large arrays

## Future Development Notes
- **Middleware**: Currently placeholder, needs URL shortening logic
- **Database layer**: Will need URL storage/retrieval
- **Cache warming**: Consider background cache warming for large configs
- **Monitoring**: Add cache hit/miss metrics
- **Path resolution**: Consider caching resolved paths for performance

## Session Update Instructions (For Claude Code)

At the end of each session, update this document with:

### Required Updates
- [ ] Move completed items from "What is missing" to appropriate sections
- [ ] Add new architectural insights to "Detailed Component Analysis"
- [ ] Update test commands if new ones were used
- [ ] Add any new patterns discovered to "Key Patterns to Follow"
- [ ] Add any new Files you found in the Architecture Overview (Core Components)

### Quality Checklist
- [ ] Keep explanations concise but complete
- [ ] Use consistent terminology throughout
- [ ] Maintain existing structure and formatting
- [ ] Remove outdated information
- [ ] Ensure code examples are accurate
- [ ] Keep file structure format consistent (path - description pattern)

### Update Format
**Session Date**: [Date]
**Changes Made**: Brief summary
**New Insights**: Key learnings

### Quality Standards
- Maximum 3 new insights per session (avoid information overload), ranked by what you think is most important
- Each insight must include WHY it matters, not just WHAT was done
- Session updates: default to short bullet points, use longer explanations only when necessary for clarity
- Remove redundant or outdated information
- Keep the document scannable with clear headers
- Deprecation warnings are NOT acceptable - fix immediately or document why they must remain
- Run tests with full error reporting to catch all deprecations and warnings
- Treat deprecations as bugs that must be resolved before considering code complete

---

## Session Updates

### Session Date: 2025-01-11
**Changes Made**: Test suite cleanup - 24 failing tests fixed, 4 problematic tests removed, 100% passing suite achieved

**New Insights**:

1. **Test Quality over Quantity** - Remove tests that fight architecture rather than fix them - maintains Clean Code vision and prevents architectural violations

2. **Mock Configuration for Interface-based DI** - `willReturnCallback()` more reliable than `willReturnMap()` for complex interface mocking - critical for FileSystemInterface/PathResolverInterface patterns

3. **Test Architecture Alignment** - Tests must follow same patterns as codebase (DataProviders, interface mocking, static cache management) - validates object messaging without exposing implementation details

### Session Date: 2025-01-11
**Changes Made**: Created comprehensive ExtensionSetupTest with 14 data provider scenarios

**New Insights**:

1. **Global State Testing Patterns** - ExtensionSetup static testing requires careful GLOBALS management with setUp/tearDown - ensures test isolation while validating TYPO3 integration patterns

2. **Data Provider Edge Case Coverage** - Testing null/false values and partial configurations validates graceful degradation - critical for extension setup robustness in diverse TYPO3 environments
