# CLI Commands

For development purposes, the following CLI commands are available:

```bash
# Delete all cabinet blob files
./console dbp:relay:cabinet:delete-all-files

# Generate typesense collection
./console dbp:relay:cabinet:sync --full --ask

# Generate files for the typesense documents
./console dbp:relay:cabinet:add-fake-files --count 50
```

## MiddlewareAPI specific

If you have [just](https://github.com/casey/just) installed, you can also use the following command in the `docker` folder:

```bash
just cabinet-dev-setup
```
