# Testing the Localization API

This document provides examples of how to test the localization API endpoints using cURL commands.

## Prerequisites

- Your Laravel application should be running
- You should have created the language files in `resources/lang/en/messages.php` and `resources/lang/ar/messages.php`

## API Endpoints

### 1. Get Translations for a Specific Locale

**Endpoint:** `GET /api/language/translations/{locale}`

**Example (English):**

```bash
curl -X GET "http://localhost:8000/api/language/translations/en" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

**Example (Arabic):**

```bash
curl -X GET "http://localhost:8000/api/language/translations/ar" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

**Expected Response:**

```json
{
  "locale": "ar",
  "translations": {
    "welcome": "مرحبا بكم في وجهة",
    "home": "الرئيسية",
    "dashboard": "لوحة التحكم",
    ...
  }
}
```

### 2. Switch Locale

**Endpoint:** `POST /api/language/switch`

**Example (Switch to Arabic):**

```bash
curl -X POST "http://localhost:8000/api/language/switch" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"locale": "ar"}'
```

**Example (Switch to English):**

```bash
curl -X POST "http://localhost:8000/api/language/switch" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"locale": "en"}'
```

**Expected Response:**

```json
{
  "locale": "en",
  "translations": {
    "welcome": "Welcome to Wejha",
    "home": "Home",
    "dashboard": "Dashboard",
    ...
  }
}
```

### 3. Using Accept-Language Header

You can also use the `Accept-Language` header to automatically set the locale for any API request:

```bash
curl -X GET "http://localhost:8000/api/some-endpoint" \
  -H "Accept: application/json" \
  -H "Accept-Language: ar" \
  -H "Content-Type: application/json"
```

## Testing with Postman

1. Create a new request in Postman
2. Set the URL to `http://localhost:8000/api/language/translations/ar`
3. Set the method to `GET`
4. Add header `Accept: application/json`
5. Send the request

For the switch endpoint:
1. Create a new request in Postman
2. Set the URL to `http://localhost:8000/api/language/switch`
3. Set the method to `POST`
4. Add header `Content-Type: application/json`
5. Add request body: `{"locale": "ar"}`
6. Send the request

## Troubleshooting

If you encounter issues:

1. Make sure your API routes are registered correctly in `routes/api.php`
2. Check that the `ApiLocalization` middleware is registered in `bootstrap/app.php`
3. Verify that your language files exist in the correct locations
4. Check Laravel logs for any errors 