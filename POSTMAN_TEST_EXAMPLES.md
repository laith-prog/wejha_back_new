# Testing Real Estate API Endpoints in Postman

Here are the step-by-step instructions and JSON bodies for testing each endpoint of the Real Estate API in Postman:

## Authentication Setup

Before testing protected endpoints, you need to get an authentication token:

1. **Login Request:**
   - Method: `POST`
   - URL: `{{base_url}}/api/v1/auth/login` (adjust based on your auth endpoint)
   - Headers:
     - Accept: `application/json`
     - Content-Type: `application/json`

   **JSON Body:**
   ```json
   {
     "email": "user@example.com",
     "password": "password"
   }
   ```

2. **Save the token from the response**
3. **Create an environment variable in Postman named `token` with the value of your authentication token**

## 1. List All Real Estate Listings

**Request:**
- Method: `GET`
- URL: `{{base_url}}/api/real-estate`
- Headers:
  - Accept: `application/json`

**No body required**

## 2. Search Real Estate Listings

**Request:**
- Method: `GET`
- URL: `{{base_url}}/api/real-estate/search`
- Headers:
  - Accept: `application/json`

**Query Parameters:**
```
property_type=apartment
offer_type=rent
min_price=500
max_price=2000
min_area=50
max_area=200
rooms=2
bathrooms=1
is_room_rental=false
sort_by=price
sort_direction=asc
per_page=10
```

## 3. View Specific Real Estate Listing

**Request:**
- Method: `GET`
- URL: `{{base_url}}/api/real-estate/1` (replace `1` with actual listing ID)
- Headers:
  - Accept: `application/json`

**No body required**

## 4. Get Form Data for Creating Listings (Requires Auth)

**Request:**
- Method: `GET`
- URL: `{{base_url}}/api/real-estate/create`
- Headers:
  - Accept: `application/json`
  - Authorization: `Bearer {{token}}`

**No body required**

## 5. Create New Real Estate Listing (Requires Auth)

### JSON Request:

**Request:**
- Method: `POST`
- URL: `{{base_url}}/api/real-estate`
- Headers:
  - Accept: `application/json`
  - Content-Type: `application/json`
  - Authorization: `Bearer {{token}}`

**JSON Body:**
```json
{
  "title": "Modern Apartment in City Center",
  "description": "Beautiful modern apartment with 2 bedrooms and a spacious living room. Recently renovated with high-quality materials.",
  "price": 1200,
  "price_type": "fixed",
  "currency": "USD",
  "phone_number": "+1234567890",
  "purpose": "rent",
  "property_type": "apartment",
  "offer_type": "rent",
  "location": "{\"lat\": 34.0522, \"lng\": -118.2437, \"address\": \"123 Main St\", \"city\": \"Los Angeles\", \"state\": \"CA\", \"country\": \"USA\"}",
  "room_number": 2,
  "bathrooms": 1,
  "area": 85,
  "floors": 1,
  "floor_number": 3,
  "has_parking": true,
  "has_garden": false,
  "balcony": 1,
  "has_pool": false,
  "has_elevator": true,
  "furnished": "fully",
  "year_built": 2018,
  "ownership_type": "freehold",
  "legal_status": "ready",
  "amenities": "[\"air_conditioning\", \"heating\", \"wifi\", \"dishwasher\", \"washing_machine\"]",
  "is_room_rental": false,
  "features": "[\"near_metro\", \"pet_friendly\", \"security_system\"]"
}
```

### Form Data Request (with Images):

**Request:**
- Method: `POST`
- URL: `{{base_url}}/api/real-estate`
- Headers:
  - Accept: `application/json`
  - Authorization: `Bearer {{token}}`
  - Content-Type: `multipart/form-data`

**Form Data:**
- All the fields from the JSON example above
- Add files with key `images[]` (multiple files can be selected)

## 6. Delete Real Estate Listing (Requires Auth)

**Request:**
- Method: `DELETE`
- URL: `{{base_url}}/api/real-estate/1` (replace `1` with actual listing ID)
- Headers:
  - Accept: `application/json`
  - Authorization: `Bearer {{token}}`

**No body required**

## 7. Toggle Favorite Status (Requires Auth)

**Request:**
- Method: `POST`
- URL: `{{base_url}}/api/real-estate/1/favorite` (replace `1` with actual listing ID)
- Headers:
  - Accept: `application/json`
  - Authorization: `Bearer {{token}}`

**No body required**

## 8. Report a Listing (Any Type - Requires Auth)

**Request:**
- Method: `POST`
- URL: `{{base_url}}/api/listings/1/report` (replace `1` with actual listing ID)
- Headers:
  - Accept: `application/json`
  - Content-Type: `application/json`
  - Authorization: `Bearer {{token}}`

**JSON Body:**
```json
{
  "reason": "misleading",
  "details": "The listing claims to have 2 bedrooms but photos show only 1 bedroom.",
  "evidence": [
    "https://example.com/evidence1.jpg",
    "https://example.com/evidence2.jpg"
  ]
}
```

## 9. View User's Reports (Requires Auth)

**Request:**
- Method: `GET`
- URL: `{{base_url}}/api/listings/reports`
- Headers:
  - Accept: `application/json`
  - Authorization: `Bearer {{token}}`

**No body required**

## 10. View All Reports for a Listing (Admin Only)

**Request:**
- Method: `GET`
- URL: `{{base_url}}/api/admin/listings/1/reports` (replace `1` with actual listing ID)
- Headers:
  - Accept: `application/json`
  - Authorization: `Bearer {{token}}` (must be an admin token)

**No body required**

## Setting Up Environment Variables

1. Create a new environment in Postman
2. Add variable `base_url` with your API base URL (e.g., `http://localhost:8000`)
3. Add variable `token` after successful login

This setup will allow you to easily test all the Real Estate API endpoints with the correct request format and data. 