# Changelog

## 0.2.13

- `dbp:relay:cabinet:upload-file` gained a `--filename` option for specifying the filename
- New `dbp:relay:cabinet:delete-file` command for deleting files
- Files changes in the blob bucket will not automatically be reflected to Typesense
- `dbp:relay:cabinet:sync` no longer adds dummy file documents to Typesense

## 0.2.12

- New `dbp:relay:cabinet:upload-file` command for uploading files
- Adjusted demo file metadata to match the newest schema

## 0.2.11

- Added a health check for the blob connection
- Fixes the typesense health check to not block forever if the connection fails
- config changes:
  - typesense_base_url -> typesense.api_url
  - typesense_api_key -> typesense.api_key
  - blob_base_url -> blob.api_url
  - blob_bucket_id -> blob.bucket_id
  - blob_key -> blob.bucket_key
- new config options:
  - blob.idp_url
  - blob.idp_client_id
  - blob.idp_client_secret
  - blob.api_url_internal (optional)

## 0.2.10

- reduce memory usage a bit when syncing to typesense

## 0.2.9

- update dummy documents to new schema
- drop support for Symfony 5 and api-platform 2

## 0.2.8

- add @type and fileSource fields to dummy file documents

## 0.2.7

- `conversation` got renamed to `communication`

## 0.2.6

- schema creation is now done via the SchemaRetrievalEvent
- person translation is now done via the DocumentTranslationEvent

## 0.2.5

- use GET parameters in `/cabinet/signatures` endpoint

## 0.2.4

- typesense schema changes
- more fields in typesense schema

## 0.2.3

- fix version of `dbp/relay-blob-library`

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