# Logging

The Cabinet bundle provides the `dbp_relay_cabinet_audit` channel. It records
successful issuance of signed Blob URLs for `POST`, `PATCH`, and `DELETE`, which
is the point where Cabinet authorizes a user to perform a write operation.

Records contain the authenticated user identifier (`relay-cabinet-user-id`) and
the validated Blob URL inputs under `query-parameters`. Existing Blob resources
are identified separately by `relay-cabinet-blob-id`, and the bucket is recorded
as `relay-cabinet-blob-bucket-id`. Relay Core adds request and session
correlation fields.

The records contain metadata instead of the signed URL, keeping the write
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
