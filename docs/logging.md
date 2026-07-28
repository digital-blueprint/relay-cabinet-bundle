# Logging

The Cabinet bundle provides the `dbp_relay_cabinet_audit` channel. It records
the successful issuance of signed Blob URLs, which is the point where Cabinet
authorizes a user to access a document. Reads (`GET`, `DOWNLOAD`) and writes
(`POST`, `PATCH`, `DELETE`) share the message `Issued signed URL for Blob` and
are distinguished by `method` in the logged `query-parameters`.

Note that this records the authorization, not the actual access: the signed URL
is used against Blob afterwards, and Cabinet is not involved in, and cannot
observe, whether the user really fetched or changed the document.

Enable it in the bundle configuration:

```yaml
dbp_relay_cabinet:
    audit_logging: true
```

Records contain the authenticated user identifier (`relay-cabinet-user-id`) and
the validated Blob URL inputs under `query-parameters`. Existing Blob resources
are identified separately by `relay-cabinet-blob-id`, and the bucket is recorded
as `relay-cabinet-blob-bucket-id`. Relay Core adds request and session
correlation fields.

The records contain metadata instead of the signed URL, keeping the granted
capability secret. The channel is unmasked and contains personally identifiable
information, so it should use appropriate access controls and retention.

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        file-log:
            type: rotating_file
            level: notice
            path: '%kernel.logs_dir%/%kernel.environment%.log'
            max_files: 10
            channels: ['!dbp_relay_cabinet_audit']
        dbp_relay_cabinet_audit:
            type: rotating_file
            level: debug
            date_format: 'Y-m'
            path: '%kernel.logs_dir%/dbp_relay_cabinet_audit-%kernel.environment%.log'
            channels: ['dbp_relay_cabinet_audit']
```
