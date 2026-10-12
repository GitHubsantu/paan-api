# PAAN API v1

Base URL: [https://paan-api.piratemods.com/api/v1/](https://paan-api.piratemods.com/api/v1/)

Send `Accept: application/json`. Send `Content-Type: application/json` unless an endpoint accepts an image upload, in which case use `multipart/form-data`. Protected endpoints require `Authorization: Bearer <token>`.

Responses use `{"success":true,"message":"...","data":...}`. Validation errors use `{"success":false,"message":"...","errors":{"field":["..."]}}`. Paginated responses also include `meta`.

## Authentication

| Method | Path | Authentication | Notes |
| --- | --- | --- | --- |
| POST | `/auth/otp/send` | None | Send an OTP; accepts `phone` and optional `purpose`. |
| POST | `/auth/otp/verify` | None | Verify an OTP and sign in/register; accepts `phone` and `otp`. |
| POST | `/auth/register` | None | Register with `name`, `phone`, and `otp`. |
| POST | `/auth/login` | None | Sign in with `phone` and `otp`. |
| POST | `/admin/login` | None | Admin password login; accepts `phone` and `password`. |
| POST | `/auth/logout` | Token | Revoke the current token. |
| GET | `/auth/me` | Token | Return the authenticated user. |

### OTP mock configuration

`OTP_MOCK` enables mock OTPs only outside production. `OTP_MOCK_PHONES` is a comma-separated allowlist: a mock OTP is used only when the requested phone is listed there. Mock OTPs never work when `APP_ENV=production`, even if `OTP_MOCK=true`.

## Public catalog

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/shop` | Shop information and current status. |
| GET | `/categories` | Active categories. |
| GET | `/categories/{category}/products` | Products in a category. |
| GET | `/products` | Supports catalog filters and pagination. |
| GET | `/products/{product}` | Product detail. |

## Customer account, orders, and notifications

Customer token required.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/profile` | Customer profile. |
| PUT | `/profile` | Update the customer profile. |
| POST | `/orders` | Create an order. |
| GET | `/orders` | List the customer's orders. |
| GET | `/orders/{order}` | Order detail. |
| GET | `/orders/{order}/status` | Order status. |
| GET | `/notifications` | List notifications, newest first; `meta` includes `unread_count`. |
| POST | `/notifications/{id}/read` | Mark one notification read. |
| POST | `/notifications/read-all` | Mark every notification read. |
| POST | `/device-tokens` | Register or move an Android FCM token to the authenticated user. |
| DELETE | `/device-tokens/{token}` | Idempotently remove the authenticated user's token. |

`POST /device-tokens` requires `{"token":"...","platform":"android","app":"customer"}` for customer tokens, or `app:"admin"` for admin tokens. A token is globally unique: registering it under another account moves it to the current account. Mismatched role/app pairs return `422`.

Customer order-status notifications return this shape:

```json
{"success":true,"data":[{"id":"uuid","type":"order_status","title":"Your order is ready for pickup","body":"Token #47 - show it at the counter.","order_id":12,"status":"ready","read_at":null,"created_at":"2026-10-13T10:00:00+05:30"}],"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1,"unread_count":1}}
```

## Firebase push notifications

FCM uses HTTP v1 service-account credentials. Set `FIREBASE_CREDENTIALS` to the deployed service-account JSON path and `FIREBASE_PROJECT_ID` to its Firebase project ID. Do not commit the JSON file or put credential contents in `.env`.

Pushes are queued with three attempts and 10/30-second backoff. Run a worker in deployed environments with `php artisan queue:work`. `QUEUE_CONNECTION=sync` is suitable for local development. To verify delivery for a user, run `php artisan paan:push-test {phone} {customer|admin}`; it reports token use and tokens removed after an invalid FCM response.

## Admin dashboard and settings

Active admin token required for every endpoint in this and the remaining admin areas.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/admin/dashboard` | Dashboard summary. |
| GET | `/admin/settings` | Shop settings. |
| PUT | `/admin/settings` | Update shop settings. |

## Admin orders

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/admin/orders` | List orders. |
| GET | `/admin/orders/{order}` | Order detail. |
| PATCH | `/admin/orders/{order}/status` | Update order status. |
| POST | `/admin/orders/{order}/cancel` | Cancel an order. |

## Customer administration

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/admin/customers` | List customers. |
| POST | `/admin/customers` | Create a customer. |
| GET | `/admin/customers/{customer}` | Customer detail. |
| PUT | `/admin/customers/{customer}` | Update a customer. |
| PATCH | `/admin/customers/{customer}/block` | Block or unblock a customer. |
| DELETE | `/admin/customers/{customer}` | Delete a customer. |
| GET | `/admin/customers/deleted` | List only soft-deleted customers; accepts `search`, `per_page` (maximum 50), and `page`. |
| POST | `/admin/customers/{customer}/restore` | Restore a deleted customer as active. A non-bin customer returns `404` with code `NOT_IN_BIN`; a phone now used by another customer returns `409` with code `PHONE_TAKEN` and message `This phone number is now used by another customer.` |
| DELETE | `/admin/customers/{customer}/force` | Permanently delete a deleted customer. A non-bin customer returns `404` with code `NOT_IN_BIN`. |

`GET /admin/customers/deleted` returns only deleted customers, newest deletion first:

```json
{"success":true,"message":"Deleted customers retrieved successfully.","data":[{"id":7,"name":"Rahul Das","phone":"9876543210","email":null,"orders_count":5,"deleted_at":"2026-10-12T10:00:00+05:30"}],"meta":{"current_page":1,"last_page":1,"per_page":15,"total":1}}
```

## Catalog administration

Catalog writes use `multipart/form-data`. The API accepts `PUT` for category and product updates. Android clients must instead send a `POST` request with `_method=PUT` in the form data.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/admin/categories` | List categories. |
| POST | `/admin/categories` | Create a category. |
| POST | `/admin/categories/{category}` | Update a category; Android must include `_method=PUT`. |
| PUT | `/admin/categories/{category}` | Update a category; Android: `POST` with `_method=PUT`. |
| DELETE | `/admin/categories/{category}` | Delete a category. |
| GET | `/admin/addons` | List add-ons. |
| POST | `/admin/addons` | Create an add-on. |
| PUT | `/admin/addons/{addon}` | Update an add-on. |
| DELETE | `/admin/addons/{addon}` | Delete an add-on. |
| GET | `/admin/products` | List products. |
| POST | `/admin/products` | Create a product. |
| GET | `/admin/products/{product}` | Product detail. |
| POST | `/admin/products/{product}` | Update a product; Android must include `_method=PUT`. |
| PUT | `/admin/products/{product}` | Update a product; Android: `POST` with `_method=PUT`. |
| PATCH | `/admin/products/{product}/availability` | Update product availability. |
| DELETE | `/admin/products/{product}` | Delete a product. |
