#!/usr/bin/env sh
#
# Seed the documents collection on the Typesense dev-server with demo data
#

# Set the Typesense host and API key
TYPESENSE_HOST="https://toolbox-backend-dev.tugraz.at"
TYPESENSE_COLLECTION="cabinet-documents"

# Check if TYPESENSE_API_KEY is set
if [ -z "$TYPESENSE_API_KEY" ]; then
  echo "Please set the TYPESENSE_API_KEY environment variable"
  exit 1
fi

## Generate search-only API key
#printf "Creating search-only API key for ${TYPESENSE_COLLECTION} collection...\n\n"
#curl "${TYPESENSE_HOST}/keys" \
#    -X POST \
#    -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}" \
#    -H 'Content-Type: application/json' \
#    -d '{"description":"Search-only companies key.","actions": ["documents:search"], "collections": ["cabinet-documents"]}'

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
            {"name": "student-firstname", "type": "string" },
            {"name": "student-lastname", "type": "string" },
            {"name": "student-birthday", "type": "string" },
            {"name": "student-address", "type": "string" },
            {"name": "student-zip", "type": "string" },
            {"name": "student-city", "type": "string" },
            {"name": "student-country", "type": "string" }
          ]
        }'

printf "\n\nImporting ${TYPESENSE_COLLECTION} data...\n\n"

curl -X POST "${TYPESENSE_HOST}/collections/${TYPESENSE_COLLECTION}/documents/import" \
     -H 'Content-Type: application/json' \
     -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}" \
     --data-binary '@documents.jsonl'
