# Overview

Source: https://github.com/digital-blueprint/dbp-relay-cabinet-bundle

This bundle provides an API for managing student records.

```mermaid
graph TD
    A(cabinet bundle) --> B[typesense]
    A --> C[database]
    A --> D[blob bundle]
    F[frontend app] --> A
    F --> D
```

## Installation Requirements

* A MySQL/MariaDB database

## Documentation

* [Configuration](./config.md)
* [Database](./database.md)
* [API](./api.md)
* [CLI Commands](./cli.md)
