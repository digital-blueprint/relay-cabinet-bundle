#!/usr/bin/env sh
#
# Seed the files collection in Typesense with demo data
#

# Set the Typesense host and API key
#TYPESENSE_HOST="http://127.0.0.1:8108"
TYPESENSE_HOST="http://typesense.localhost:8000"
TYPESENSE_API_KEY="xyz"
TYPESENSE_COLLECTION="cabinet-files"

printf "Deleting the files collection...\n\n"

curl -X DELETE "${TYPESENSE_HOST}/collections/${TYPESENSE_COLLECTION}" \
     -H 'Content-Type: application/json' \
     -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}"

printf "\n\nCreating the ${TYPESENSE_COLLECTION} collection...\n\n"

curl -X POST "${TYPESENSE_HOST}/collections" \
    -H 'Content-Type: application/json' \
    -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}" \
    -d '{
      "name": "cabinet-files",
      "fields": [
        {"name": "filename", "type": "string"},
        {"name": "filetype", "type": "string"},
        {"name": "filesize", "type": "int32"}
      ],
      "default_sorting_field": "filesize"
    }'

printf "\n\nImporting ${TYPESENSE_COLLECTION} data...\n\n"

curl -X POST "${TYPESENSE_HOST}/collections/${TYPESENSE_COLLECTION}/documents/import" \
     -H 'Content-Type: application/json' \
     -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}" \
     --data-binary '@files.jsonl'
