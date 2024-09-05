# CLI Commands

For development purposes, the following CLI commands are available:

```bash
# Delete all cabinet blob files
./console dbp:relay:cabinet:delete-all-files

# Generate typesense collection
./console dbp:relay:cabinet:sync --full

# Generate files for the typesense documents
./console tugraz:relay:tugraz:cabinet:add-file-fixtures --number 50
```
