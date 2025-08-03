# WonderWp Import Foundation

A robust and flexible import framework for WordPress applications built on the WonderWp ecosystem. This package provides a complete architecture for importing data from external sources into WordPress, with support for data transformation, synchronization, and dry-run capabilities.

## Overview

The Import Foundation package implements a clean architecture pattern for data imports, separating concerns into distinct components:

- **Commands**: WP-CLI commands for executing imports
- **Importers**: Main orchestrators that coordinate the import process
- **Repositories**: Data access layer for source and destination
- **Transformers**: Data format conversion and normalization
- **Syncers**: Synchronization logic between source and destination
- **Persisters**: Data persistence layer
- **Requests/Responses**: Request/response handling for imports and syncs

## Architecture Components

### 1. Commands (`src/Commands/`)

WP-CLI commands that provide the entry point for import operations.

#### `AbstractImporterCommand`
Base class for all importer commands that:
- Extends `AbstractWpCliCommand` with dry-run support
- Provides standardized argument handling
- Manages logging and response output
- Requires implementation of `loadImporter()` method

**Key Features:**
- Built-in dry-run functionality
- Automatic timing and logging
- Standardized error handling
- Progress tracking

### 2. Importers (`src/Importers/`)

The main orchestrators that coordinate the entire import process.

#### `AbstractImporter`
Core importer class that implements the complete import workflow:

1. **Fetch source data** via `sourceRepository`
2. **Transform source data** via `sourceTransformer`
3. **Fetch destination data** via `destinationRepository`
4. **Transform destination data** via `destinationTransformer`
5. **Synchronize data** via `syncer`

**Constructor Dependencies:**
```php
public function __construct(
    RepositoryInterface  $sourceRepository,
    TransformerInterface $sourceTransformer,
    RepositoryInterface  $destinationRepository,
    TransformerInterface $destinationTransformer,
    SyncerInterface      $syncer
)
```

### 3. Repositories (`src/Repositories/`)

Data access layer that abstracts data retrieval operations.

#### `RepositoryInterface`
Extends the base WonderWp repository interface, providing:
- `find($id)` - Find single item by ID
- `findAll()` - Retrieve all items
- `findBy(array $criteria)` - Find items by criteria
- `findOneBy(array $criteria)` - Find single item by criteria

### 4. Transformers (`src/Transformers/`)

Data transformation layer that converts data between different formats.

#### `TransformerInterface`
```php
public function transform(WP_Post $post, bool $isDryRun): WP_Post
```

Transformers handle:
- Data format conversion
- Field mapping
- Data validation
- Normalization

### 5. Syncers (`src/Syncers/`)

Synchronization logic that determines what data needs to be created, updated, or deleted.

#### `AbstractPostsSyncer`
Advanced syncer implementation that:
- Compares source and destination data
- Identifies differences using configurable indexes
- Supports post content, meta, and taxonomy comparison
- Provides detailed sync statistics

**Configurable Comparison Indexes:**
- `postComparisonIndexes` - Post fields to compare
- `postMetaComparisonIndexes` - Meta fields to compare
- `postTermsComparisonIndexes` - Taxonomy terms to compare

### 6. Persisters (`src/Persisters/`)

Data persistence layer that handles saving data to the destination.

#### `WpPostPersister`
WordPress-specific persister that:
- Creates new posts
- Updates existing posts
- Handles post meta and taxonomies
- Manages post status and visibility

### 7. Requests & Responses (`src/Requests/`, `src/Responses/`)

Request/response objects for structured data handling.

#### Import Requests
- `ImportRequest` - Standard import request with dry-run support
- `ImportRequestInterface` - Contract for import requests

#### Sync Requests
- `SyncRequest` - Synchronization request with source/destination data
- `SyncRequestInterface` - Contract for sync requests

#### Responses
- `ImportResponse` - Import operation results
- `SyncResponse` - Synchronization operation results with detailed statistics

### 8. Resetters (`src/Resetters/`)

Data cleanup and reset functionality.

#### `AbstractPostResetter`
Base class for resetting imported data:
- Removes imported posts
- Cleans up associated meta and taxonomies
- Provides safe reset operations

### 9. Exceptions (`src/Exceptions/`)

Custom exception classes for import operations.

- `ImportException` - General import errors
- `TransformException` - Data transformation errors

## Usage Example

### 1. Create Your Importer

```php
<?php

namespace WonderWp\Plugin\MyPlugin\Child\Importer;

use WonderWp\Component\ImportFoundation\Importers\AbstractImporter;

class MyEntityImporter extends AbstractImporter
{
    // The AbstractImporter handles all the import logic
    // You can override methods if you need custom behavior
}
```

### 2. Create Your Command

```php
<?php

namespace WonderWp\Plugin\MyPlugin\Child\Commands;

use WonderWp\Component\ImportFoundation\Commands\AbstractImporterCommand;
use WonderWp\Component\ImportFoundation\Importers\ImporterInterface;

class ImportMyEntityCommand extends AbstractImporterCommand
{
    public static function getName(): string
    {
        return 'my-plugin:import-my-entity';
    }

    protected static function getCommandDescription(): string
    {
        return 'Import MyEntity from external source';
    }

    protected function loadImporter(string $importerKey): ImporterInterface
    {
        return $this->manager->getService($importerKey);
    }
}
```

### 3. Register Services

```php
<?php

namespace WonderWp\Plugin\MyPlugin\Child;

use WonderWp\Component\DependencyInjection\Container;
use WonderWp\Component\ImportFoundation\Persisters\WpPostPersister;

class MyPluginThemeManager extends MyPluginManager
{
    const MY_ENTITY_IMPORTER_KEY = 'my-entity';
    const MY_ENTITY_SOURCE_REPOSITORY = 'my-entity.source.repository';
    const MY_ENTITY_SOURCE_TRANSFORMER = 'my-entity.source.transformer';
    const MY_ENTITY_DEST_REPOSITORY = 'my-entity.destination.repository';
    const MY_ENTITY_DEST_TRANSFORMER = 'my-entity.destination.transformer';
    const MY_ENTITY_SYNCER = 'my-entity.syncer';

    public function register(Container $container)
    {
        parent::register($container);

        // Register repositories
        $this->addService(self::MY_ENTITY_SOURCE_REPOSITORY, function(){
            return new MyEntitySourceRepository();
        });

        $this->addService(self::MY_ENTITY_DEST_REPOSITORY, function(){
            return new MyEntityDestinationRepository();
        });

        // Register transformers
        $this->addService(self::MY_ENTITY_SOURCE_TRANSFORMER, function(){
            return new MyEntitySourceTransformer();
        });

        $this->addService(self::MY_ENTITY_DEST_TRANSFORMER, function(){
            return new MyEntityDestinationTransformer();
        });

        // Register syncer
        $this->addService(self::MY_ENTITY_SYNCER, function(){
            return new MyEntitySyncer(
                $this->getService('my-entity.persister')
            );
        });

        // Register importer
        $this->addService(self::MY_ENTITY_IMPORTER_KEY, function(){
            return new MyEntityImporter(
                $this->getService(self::MY_ENTITY_SOURCE_REPOSITORY),
                $this->getService(self::MY_ENTITY_SOURCE_TRANSFORMER),
                $this->getService(self::MY_ENTITY_DEST_REPOSITORY),
                $this->getService(self::MY_ENTITY_DEST_TRANSFORMER),
                $this->getService(self::MY_ENTITY_SYNCER)
            );
        });

        return $this;
    }
}
```

### 4. Execute Import

```bash
# Run the import
wp my-plugin:import-my-entity --importer=my-entity

# Run in dry-run mode
wp my-plugin:import-my-entity --importer=my-entity --dry-run
```

## Key Features

### 🔄 Dry-Run Support
All operations support dry-run mode, allowing you to preview changes without making them.

### 📊 Detailed Logging
Comprehensive logging throughout the import process with timing information.

### 🎯 Flexible Comparison
Configurable comparison indexes for posts, meta, and taxonomies.

### 🛡️ Error Handling
Robust error handling with detailed error messages and graceful degradation.

### ⚡ Performance Optimized
Efficient data processing with progress tracking and memory management.

### 🔧 Extensible Architecture
Clean separation of concerns allows easy customization and extension.

## Requirements

- PHP >= 8.0
- WordPress
- WonderWp Framework

## Installation

```bash
composer require wonderwp/import-foundation:dev-develop@dev
```

## Contributing

This package is part of the WonderWp ecosystem. Contributions are welcome!

## License

MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions, please refer to the WonderWp documentation or create an issue in the repository.
