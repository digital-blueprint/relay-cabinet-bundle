#!/usr/bin/env sh
#
# Seed the documents collection in Typesense with demo data
#

# Set the Typesense host and API key
#TYPESENSE_HOST="http://127.0.0.1:8108"
TYPESENSE_HOST="http://typesense.localhost:8000"
TYPESENSE_API_KEY="xyz"
TYPESENSE_COLLECTION="cabinet-documents"

printf "Deleting the documents collection...\n\n"

curl -X DELETE "${TYPESENSE_HOST}/collections/${TYPESENSE_COLLECTION}" \
     -H 'Content-Type: application/json' \
     -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}"

printf "\n\nCreating the ${TYPESENSE_COLLECTION} collection...\n\n"

curl -X POST "${TYPESENSE_HOST}/collections" \
    -H 'Content-Type: application/json' \
    -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}" \
    -d '{
          "name": "cabinet-documents",
          "fields": [
            {"name": "objectType", "type": "string" },
            {"name": "type", "type": "string" },
            {"name": "name", "type": "string" },
            {"name": "file-filename", "type": "string" },
            {"name": "file-mimetype", "type": "string" },
            {"name": "file-filesize", "type": "int32" },
            {"name": "person-firstname", "type": "string" },
            {"name": "person-lastname", "type": "string" },
            {"name": "person-birthday", "type": "string" },
            {"name": "person-address", "type": "string" },
            {"name": "person-zip", "type": "string" },
            {"name": "person-city", "type": "string" },
            {"name": "person-country", "type": "string" }
          ]
        }'

printf "\n\nImporting ${TYPESENSE_COLLECTION} data...\n\n"

curl -X POST "${TYPESENSE_HOST}/collections/${TYPESENSE_COLLECTION}/documents/import" \
     -H 'Content-Type: application/json' \
     -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}" \
     --data-binary '@documents.jsonl'
