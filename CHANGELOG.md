# Changelog

## 0.2.2

- add `/cabinet/signatures` endpoint for blob signature verification

## 0.2.1

- add `file.base.fileName` to schema and sync service

## 0.2.0

- typesense schema changes
- breaking changes to the PersonSyncInterface
- don't delete legacy testing collections in typesense for now

## 0.1.3

- Migrate dev document seed scripts from `student` to `person`
- "dbp:relay:cabinet:sync" now supports incremental syncs
- typesense schema updates and "sync" now fills typesense with dummy file documents
  in addition to person documents.

## 0.1.2

- Add basic sync functionality

## 0.1.1

- Add dummy implementations for the sync service

## 0.1.0

- Bump minor version for easier updates

## 0.0.10

- Port to PHPUnit 10
- Port from doctrine annotations to PHP attributes

## 0.0.9

- Add support for api-platform 3.2

## 0.0.8

- Allow exceptions from e.g. server errors to be routed to the client in the Typesense proxy

## 0.0.7

- Add authentication check for the Typesense proxy

## 0.0.6

- Allow Typesense proxy to relay requests to the JS Typesense library in the frontend successfully

## 0.0.5

- Add Typesense settings and service

## 0.0.4

- Fix linting errors

## 0.0.3

- Fix tests

## 0.0.2

- Add missing parts to be installed

## 0.0.1

- Initial release