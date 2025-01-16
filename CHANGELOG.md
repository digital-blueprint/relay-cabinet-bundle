# Changelog

## 0.2.36

* Add new DocumentFinalizeEvent which is dispatched right before the typesense document
  is sent to typesense, allowing users to modify the document one last time.

## 0.2.35

* Test with PHP 8.4
* Port to phpstan v2

## 0.2.34

- Speed up "dbp:relay:cabinet:add-fake-files" if "--no-blob" is passed with many files

## 0.2.33

- Add "dbp:relay:cabinet:add-fake-files" command to add dummy files to the blob bucket

## 0.2.32

- Expose some functions for adding dummy documents for performance testing more easily

## 0.2.31

- api-docs: document deleteIn parameter
- store sync cursor in the respective typesense collections instead of the cache

## 0.2.30
- blob: add `deleteIn` parameter to signed blob PATCH requests

## 0.2.29

- blob: include files that are scheduled for deletion

## 0.2.28

- DocumentTransformEvent: expose nullable "deleteAt" field for files

## 0.2.27

- add config option to switch to using the PHP blob API instead of the HTTP one
- also provide binary download URLs via /blob-urls

## 0.2.26

- php api: translate -> transform everywhere, for better clarity
- transform: allow creating multiple documents from one source, or none at all

## 0.2.25

- typesense: all documents now share the "person" field instead of the "base" one

## 0.2.24
- Change `/cabinet/signature` endpoint to `/cabinet/blob-urls` and change the response format from a raw string to json

## 0.2.23

- Adjust for recent ApiError changes in the core bundle

## 0.2.22

- typesense proxy: only allow read-only access, even for authorized users
- typesense proxy: forward query parameters to typesense to allow for more complex queries

## 0.2.21

- Add PersonSyncResultInterface::isFullSyncResult() for the connector to communicate
  if the result is a full sync result.

## 0.2.20

- Fix initial typesense setup in case no collection alias exists yet

## 0.2.19

- The confirmation in the command `dbp:relay:cabinet:delete-all-files` now shows the Blob API URL
  to make sure the user knows which files are going to be deleted
- Add optional `--ask` flag to `dbp:relay:cabinet:sync` command to ask for confirmation before syncing
  - This confirmation includes the connection base URL to make sure the user knows to which server
    the data is going to be sent

## 0.2.18

- Add support for newer doctrine dbal/orm
- DocumentTranslationEvent: added mimeType/dateCreated/dateModified fields
  for blob documents

## 0.2.17

- Sync blob file deletions to Typesense
- Add support for schema versioning via the SchemaRetrievalEvent
- Fix partial person sync always doing a full sync due to wrongly configured caching

## 0.2.16

- Fix some API regressions

## 0.2.15

- New `dbp:relay:cabinet:delete-all-files` command for deleting all cabinet related blob files
- Blob events are now completely handled asynchronously
- The `/cabinet/signature` endpoint gained new parameters for handling multiple methods

## 0.2.14

- `dbp:relay:cabinet:sync` and `dbp:relay:cabinet:sync-one` will now also sync files into typesense. And delta syncs of person information will be reflected in the files as well.

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
