# API

## `/cabinet/signature`

| Endpoint                   | Method | Description                                                                                           | Always required Url Parameters | Url Parameters (POST) | Url Parameters (GET)                   | Url Parameters (DELETE) | Url Parameters (PATCH)                               |
|----------------------------|--------|-------------------------------------------------------------------------------------------------------|--------------------------------|-----------------------|----------------------------------------|-------------------------|------------------------------------------------------|
| `/cabinet/signature`       | GET    | Used to get a signed url for blob. Depending on which urls to get, different parameters are required. | `method`                       | `type`, `prefix`      | `identifier`, `includeData` (optional) | `identifier`            | `identifier`, `type` (optional), `prefix` (optional) |


### Parameters

| Parameter     | Description                                                                         | Type   | Possible values                              |
|---------------|-------------------------------------------------------------------------------------|--------|----------------------------------------------|
| `method`      | HTTP method which will be used on blob                                              | string | `POST`, `GET`, `DELETE`, `PATCH`, `DOWNLOAD` |
| `type`        | Type of the metadata                                                                | string | all in the blob bucket defined `types`       |
| `prefix`      | Prefix stored in blob                                                               | string | all possible strings, including an empty string |
| `identifier`  | Identifier of the blob resource                                                     | string | a valid blob identifier in uuidv7 format     |
| `includeData` | If given, the GET request will return base64 encoded data in the `contentUrl` field | string | `1` or omit the parameter completely         |
