# API

## `/cabinet/signature`

| Endpoint                   | Method | Description                                                                                           | Always required Url Parameters | Url Parameters (POST) | Url Parameters (GET)                   | Url Parameters (DELETE) | Url Parameters (PATCH)                               |
|----------------------------|--------|-------------------------------------------------------------------------------------------------------|--------------------------------|-----------------------|----------------------------------------|-------------------------|------------------------------------------------------|
| `/cabinet/signature`       | GET    | Used to get a signed url for blob. Depending on which urls to get, different parameters are required. | `method`                       | `type`, `prefix`      | `identifier`, `includeData` (optional) | `identifier`            | `identifier`, `type` (optional), `prefix` (optional) |


### Parameters

| Parameter     | Description                                                                         | Type   | Possible values                                 |
|---------------|-------------------------------------------------------------------------------------|--------|-------------------------------------------------|
| `method`      | HTTP method which will be used on blob                                              | string | `POST`, `GET`, `DELETE`, `PATCH`, `DOWNLOAD`    |
| `type`        | Type of the metadata                                                                | string | all in the blob bucket defined `types`          |
| `prefix`      | Prefix stored in blob                                                               | string | all possible strings, including an empty string |
| `identifier`  | Identifier of the blob resource                                                     | string | a valid blob identifier in uuidv7 format        |
| `includeData` | If given, the GET request will return base64 encoded data in the `contentUrl` field | string | `1` or omit the parameter completely            |

### Uploading and updating files

For POST/PATCH, use the optional `updateFile` query parameter when requesting a signed URL:

- Omit it or set `updateFile=0` to submit metadata without uploading file content or changing the filename.
- Set `updateFile=1` to allow file content and filename changes as well. This is also required when uploading a new file.

Submit `metadata` in the signing request's body, then send the exact same metadata string when using the returned URL. File uploads and filename changes must be submitted to the returned URL, not the signing endpoint. Requests are subject to your access permissions.

`updateFile=0` does not prevent changes requested through `prefix`, `type`, or `deleteIn`. Omit those parameters on PATCH to leave them unchanged. To cancel scheduled deletion, set `deleteIn=null` or `deleteIn=`; a duration such as `deleteIn=P1D` schedules deletion relative to when the returned URL is used.
