# Configuration

## Bundle Configuration

Created via `./bin/console config:dump-reference DbpRelayCabinetBundle | sed '/^$/d'`

```yaml
# Default configuration for "DbpRelayCabinetBundle"
dbp_relay_cabinet:
    # The database DSN
    database_url:         ~ # Required
    sync:
        # Cron expression for when normal/incremental syncs should run
        schedule:             '*/60 * * * *'
        # The time after the last full sync after which a full sync is forced
        full_sync_interval:   P1W
    typesense:
        # URL for the Typesense server
        api_url:              ~ # Required
        # API key for the Typesense server
        api_key:              ~ # Required
        # Number of partitions the query is split into
        search_partitions:    1
        # Whether the collection should be split for partitioning (requires a full sync on partition changes)
        search_partitions_split_collection: false
        # Number of seconds to cache search results at most
        search_cache_ttl:     3600
    authorization:
        policies:             []
        roles:
            # Returns true if the user is allowed to use the cabinet API.
            ROLE_USER:            'false'
        resource_permissions: []
        attributes:           []
    blob_library:
        # Whether to use the HTTP mode, i.e. the Blob HTTP (REST) API. If false, a custom Blob API implementation will be used.
        use_http_mode:        true
        # The fully qualified name or alias of the service to use as custom Blob API implementation. Default is the PHP Blob File API, which comes with the Relay Blob bundle and talks to Blob directly over PHP.
        custom_file_api_service: dbp.relay.blob.file_api
        # The identifier of the Blob bucket
        bucket_identifier:    ~ # Required
        http_mode:
            # The signature key of the Blob bucket. Required for HTTP mode.
            bucket_key:           ~
            # The base URL of the HTTP Blob API. Required for HTTP mode.
            blob_base_url:        ~
            # Whether to use OpenID connect authentication. Optional for HTTP mode.
            oidc_enabled:         true
            # Required for HTTP mode when oidc_enabled is true.
            oidc_provider_url:    ~
            # Required for HTTP mode when oidc_enabled is true.
            oidc_client_id:       ~
            # Required for HTTP mode when oidc_enabled is true.
            oidc_client_secret:   ~
            # Whether to send file content and metadata checksums for Blob to check
            send_checksums:       true

```
