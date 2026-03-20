# DoctrineExtensions (gedmo) Architecture

## Purpose

A collection of Doctrine ORM/ODM behavioral extensions: Blameable,
IpTraceable, Loggable, Sluggable, SoftDeleteable, Sortable, Timestampable,
Translatable, Tree (Nested Set, Closure, Materialized Path), and Uploadable.

## Directory Structure

```
src/
  Gedmo/
    Blameable/         — auto-populate "created by" / "updated by" fields
    IpTraceable/       — auto-populate IP address fields
    Loggable/          — maintain a full change log per entity
    Mapping/
      Annotation/      — Doctrine annotation definitions (legacy)
      Attribute/       — PHP 8.x attribute definitions
      Driver/          — annotation / attribute / XML / YAML metadata drivers
    Sluggable/         — auto-generate URL slugs from other fields
    SoftDeleteable/    — soft-delete: add deletedAt timestamp instead of removing
    Sortable/          — manage a sort-position integer field
    Timestampable/     — auto-populate createdAt / updatedAt fields
    Translatable/      — per-locale translations stored in a separate entity
    Tree/
      Nested/          — MPTT (left/right/level)
      Closure/         — closure table
      MaterializedPath/ — materialised path strings
    Uploadable/        — track uploaded file metadata
example/               — runnable example app (Translatable tree)
```

## Key Design Decisions

- **Event subscriber model**: each behavior registers a Doctrine event subscriber
  (`prePersist`, `preUpdate`, `loadClassMetadata`, etc.); no annotations or
  attributes are required on the listener itself.
- **Dual annotation + attribute support**: all behaviors support both legacy
  Doctrine Annotations and modern PHP 8.x attributes, with a shared metadata
  driver that detects which style is used.
- **Driver chaining**: the mapping layer supports chaining XML, YAML, annotation,
  and attribute drivers so that mixed-format projects work without reconfiguration.
- **Strategy pattern for trees**: each tree type (`Nested`, `Closure`,
  `MaterializedPath`) is a strategy; the generic `TreeListener` delegates to the
  appropriate strategy based on the entity's mapping metadata.

## Extension Points

- Implement a custom slug handler via `SlugHandlerInterface` for bespoke
  slugification logic.
- Provide a custom `TranslatableListener` locale callable to determine the
  current locale dynamically.
- Extend `AbstractTrackingListener` to create new auto-populate behaviors.

## Dependency Flow

```
Doctrine EventManager
  └── {Extension}Listener  (e.g. TimestampableListener, SlugListener)
        ├── ExtensionMetadataFactory  → Driver (Annotation/Attribute/XML/YAML)
        └── Entity Manager  (persist / update operations)
```
