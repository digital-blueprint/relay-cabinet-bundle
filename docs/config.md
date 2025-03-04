# Configuration

## Bundle Configuration

Created via `./bin/console config:dump-reference DbpRelayCabinetBundle | sed '/^$/d'`

```yaml
dbp_relay_cabinet:
    # The database DSN
    database_url:         ~ # Required
    blob:
        # URL for blob storage API
        api_url:              ~ # Required
        # Bucket id for blob storage
        bucket_id:            ~ # Required
        # Secret key for blob storage
        bucket_key:           ~ # Required
        # If the HTTP API should be used for communicating with blob
        use_api:              false
        # URL for blob storage API when connecting internally (defaults to url)
        api_url_internal:     ~
        # IDP URL for authenticating with blob
        idp_url:              ~
        # Client ID for authenticating with blob
        idp_client_id:        ~
        # Client secret for authenticating with blob
        idp_client_secret:    ~
    typesense:
        # URL for the Typesense server
        api_url:              ~ # Required
        # API key for the Typesense server
        api_key:              ~ # Required
    authorization:
        roles:
            # Returns true if the user is allowed to use the cabinet API.
            ROLE_USER:            'false'
        resource_permissions: []
        attributes:           []
```
