# PAAN API v1

Base URL: `http://paan-api.test/api/v1`

Use `Accept: application/json` and `Content-Type: application/json`. Protected routes require `Authorization: Bearer <token>`.

## Response envelope

```json
{"success":true,"message":"OK","data":{}}
```

Errors use `{"success":false,"message":"...","errors":{"field":["..."]}}`. Paginated results add `meta`: `current_page`, `last_page`, `per_page`, and `total`. Expected errors: `401`, `403`, `404`, `409`, `422`, `429`.

## Authentication

Development mock OTP is `123456` when `OTP_MOCK=true`; mock OTP is inactive in production.

| Method | Path | Auth | Body |
|---|---|---|---|
| POST | `/auth/otp/send` | No | `phone`, `purpose` (`register` or `login`) |
| POST | `/auth/register` | No | `name`, `phone`, `otp` |
| POST | `/auth/login` | No | `phone`, `otp` |
| POST | `/admin/login` | No | `phone`, `password` |
| POST | `/auth/logout` | Token | none |
| GET | `/auth/me` | Token | none |

Successful login/register returns `data.user` and `data.token`. Customer tokens carry `customer`; admin tokens carry `admin`. Admin accounts must use password login.

## Public catalog

| Method | Path | Parameters |
|---|---|---|
| GET | `/shop` | none |
| GET | `/categories` | none |
| GET | `/categories/{category}/products` | catalog filters |
| GET | `/products` | `search`, `category`, `featured`, `available`, `sort`, `per_page`, `page` |
| GET | `/products/{product}` | none |

`sort`: `default`, `name`, `-name`, `price`, `-price`, `newest`; `per_page`: 1–50. Products include server-authoritative numeric price, category, allowed add-ons, availability, and image URL. Unavailable products remain listed with `is_available:false`.

## Customer API

Customer token required unless noted.

| Method | Path | Parameters |
|---|---|---|
| POST | `/orders` | `items` (1–20); each item has `product_id`, `quantity` 1–50, optional `addon_ids`; optional `comment` max 200; `payment_method:mock` |
| GET | `/orders` | optional enum `status`, `per_page`, `page` |
| GET | `/orders/{order}` | none |
| GET | `/orders/{order}/status` | none |
| GET | `/profile` | none |
| PUT | `/profile` | `name` required, optional nullable `email` |
| GET | `/notifications` | paginated, newest first |
| POST | `/notifications/{id}/read` | none |
| POST | `/notifications/read-all` | none |
| POST | `/device-tokens` | `token` max 191, `platform:"android"` |
| DELETE | `/device-tokens/{token}` | none |

Never submit a price or total: the server calculates all money values. Order results contain snapshots so old orders remain readable. A customer trying to read another customer’s order gets `404`. Profile cannot change phone, role, or active status. Device-token registration upserts per user/token.

### Exact customer response shapes

`POST /auth/otp/verify` accepts `{"phone":"9876543210","otp":"123456"}` and returns:

```json
{"success":true,"message":"Signed in successfully.","data":{"token":"...","is_new_user":true,"user":{"id":7,"name":null,"phone":"9876543210","email":null}}}
```

Product list items and product detail have the same shape:

```json
{"id":2,"name":"Shahi Meetha Paan","slug":"shahi-meetha-paan","description":"Gulkand, glazed cherries, tutty-fruitty, green cardamom, silver leaf.","price":40,"category":{"id":1,"name":"Sweet Paan (Meetha)"},"image_url":null,"thumbnail_url":null,"is_available":true,"is_featured":false,"tag":"Chandi Vark","prep_minutes":3,"ingredients":["Magahi Betel Leaf","Gulkand","Glazed Cherries"],"addons":[]}
```

`GET /categories` returns active categories ordered by `sort_order`:

```json
{"success":true,"message":"Categories retrieved successfully.","data":[{"id":1,"name":"Sweet Paan (Meetha)","slug":"sweet-paan-meetha","description":null,"image_url":null,"sort_order":1}]}
```

`GET /shop` includes `shop_name`, `shop_address`, `shop_phone`, `opening_time`, `closing_time`, `pickup_message`, and `shop_status` (legacy shop keys remain for compatibility).

`POST /orders` accepts `payment_channel` (`upi`/`card`) and item `options`; create/list use the following order shape (detail additionally includes `history`):

```json
{"id":12,"order_number":"PAAN-10012","token":47,"status":"pending","payment_status":"paid","payment_method":"mock","payment_channel":"upi","subtotal":160,"total":160,"comment":"Less sweet please","items":[{"id":31,"product_id":2,"name":"Shahi Meetha Paan","unit_price":40,"quantity":2,"line_total":80,"image_url":null,"options":{"sweetness":"mild","supari":"kacha"}}],"created_at":"2026-10-10T16:05:00+05:30","updated_at":"2026-10-10T16:05:00+05:30"}
```

`GET /orders/{id}/status` includes lightweight `id`, `status`, `payment_status`, and `updated_at` (legacy keys remain). `GET /orders?status=active` combines pending, making, and ready. `GET`/`PUT /profile` returns `{"id":7,"name":"Rahul Das","phone":"9876543210","email":null}`.

## Order workflow

```text
pending -> making -> ready -> completed
   |          |        |
   +----------+--------+-> admin_cancelled
```

Admins transition orders. Cancelling a paid mock order changes payment status to `refunded`. Customer database notifications are generated for making, ready, completed, and cancellation.

## Admin API

All `/admin/*` endpoints require an active Sanctum token with the `admin` ability. A customer token receives `403`.

### Admin login

`POST /api/v1/admin/login`

```json
{"phone":"9876543210","password":"a-password-with-10-characters"}
```

```json
{"success":true,"message":"Admin logged in successfully.","data":{"user":{"id":1,"name":"Shop Admin","phone":"9876543210"},"token":"1|..."}}
```

Invalid credentials return `422` with `{"success":false,"message":"Incorrect phone number or password."}`. Login is limited to five attempts per minute for each phone and IP address.

### Admin orders

Every admin order endpoint returns this order shape. `history` is oldest first; `admin` is `null` for the initial customer-created history entry.

```json
{"id":12,"order_number":"PAAN-10012","token":47,"status":"pending","payment_status":"paid","payment_channel":"upi","total":160,"comment":"Less sweet please","customer":{"id":7,"name":"Rahul Das","phone":"9876543210"},"items":[{"id":31,"product_id":2,"name":"Shahi Meetha Paan","unit_price":40,"quantity":2,"line_total":80,"options":{"sweetness":"mild","supari":"kacha"}}],"created_at":"2026-10-11T16:05:00+05:30","history":[{"status":"pending","note":null,"created_at":"2026-10-11T16:05:00+05:30","changed_by":null,"admin":null}]}
```

`GET /api/v1/admin/orders` accepts `status` (`pending`, `making`, `ready`, `completed`, `admin_cancelled`, `active`, or `history`), `sort` (`newest` default or `oldest`), `payment_status`, `from`, `to`, `updated_after`, `search`, `per_page` (maximum `100`), and `page`. `active` means pending, making, or ready; `history` means completed or admin-cancelled. It returns the standard pagination envelope.

`GET /api/v1/admin/orders/{id}` returns the order JSON above. For backwards compatibility it also includes the legacy `order` and `status_history` keys.

`PATCH /api/v1/admin/orders/{id}/status`

```json
{"status":"making","note":"Started preparing"}
```

Only `pending → making`, `making → ready`, and `ready → completed` are permitted. Repeating the current status is successful and does not create another history row. An invalid transition returns `422`, for example `{"success":false,"message":"This order can't move from ready to making."}`.

`POST /api/v1/admin/orders/{id}/cancel`

```json
{"reason":"Out of stock"}
```

Cancellation is allowed only while pending, making, or ready. Paid mock orders are marked refunded. Both mutations return the order JSON above.

### Admin dashboard, products, settings, and device tokens

`GET /api/v1/admin/dashboard` returns:

```json
{"success":true,"message":"Dashboard retrieved successfully.","data":{"today_orders":18,"status_counts":{"pending":2,"making":1,"ready":1,"completed":13,"admin_cancelled":1},"today_sales":2450,"total_sales":98200,"total_customers":143}}
```

`GET /api/v1/admin/products?search=&per_page=&page=` returns every product, including unavailable products. `search` matches product name and `per_page` is at most `50`. It returns the standard pagination envelope:

```json
{"success":true,"message":"Products retrieved successfully.","data":[{"id":2,"name":"Shahi Meetha Paan","price":40,"category":{"id":1,"name":"Sweet Paan (Meetha)"},"image_url":"http://HOST/storage/products/shahi.webp","thumbnail_url":"http://HOST/storage/products/shahi-thumb.webp","is_available":true,"is_featured":false,"tag":null}],"meta":{"current_page":1,"last_page":1,"per_page":15,"total":1}}
```

For compatibility, product objects also retain the existing `category_id`, `slug`, `description`, `image`, `prep_minutes`, `ingredients`, and `addons` keys.

`PATCH /api/v1/admin/products/{id}/availability`

```json
{"is_available":true}
```

Returns the updated product object in `data`; sending its current availability is a successful no-op. An unknown product returns `404`.

`GET /api/v1/admin/settings` returns:

```json
{"success":true,"message":"Settings retrieved successfully.","data":{"shop_name":"Paan Sutra","shop_address":"Main Road","shop_phone":"9876543210","opening_time":"09:00","closing_time":"22:00","pickup_message":"Collect at the counter.","shop_status":"open"}}
```

`PUT /api/v1/admin/settings` accepts those same fields and returns the saved object in the same shape. Times are always 24-hour `HH:MM`; `shop_phone` must be exactly ten digits beginning with `6` through `9`; `shop_status` is `open` or `closed`. Validation failures use `422` with readable messages. While closed, customer `POST /api/v1/orders` returns `409` with code `SHOP_CLOSED`.

`GET /api/v1/admin/customers?search=&per_page=&page=` is newest first; search matches customer name or phone and `per_page` is at most `50`:

```json
{"success":true,"message":"Customers retrieved successfully.","data":[{"id":7,"name":"Rahul Das","phone":"9876543210","is_active":true,"orders_count":5,"created_at":"2026-10-01T12:00:00+05:30"}],"meta":{"current_page":1,"last_page":1,"per_page":15,"total":1}}
```

`POST /api/v1/device-tokens` and `DELETE /api/v1/device-tokens/{token}` accept either customer or admin tokens. Registration accepts `{"token":"...","platform":"android","app":"admin"}`; `app` may also be `customer` and is stored for future notification routing.

All routes require an active admin token.

| Method | Path | Parameters |
|---|---|---|
| GET | `/admin/dashboard` | none |
| GET | `/admin/orders` | `status`, `payment_status`, `from`, `to`, `search`, `per_page`, `page` |
| GET | `/admin/orders/{order}` | none |
| PATCH | `/admin/orders/{order}/status` | `status`: `making`, `ready`, or `completed`; optional `note` max 255 |
| POST | `/admin/orders/{order}/cancel` | required `reason` max 255 |
| GET | `/admin/customers` | `search`, `per_page`, `page` |
| GET | `/admin/settings` | none |
| PUT | `/admin/settings` | shop fields below |

Dashboard includes today’s order count, status counts, today/total paid sales excluding cancellations, and total customers. Order detail includes customer name/phone, snapshots, payment state, comment, and history. Customer results expose `id`, `name`, `phone`, `is_active`, `orders_count`, `created_at`.

Settings fields: `shop_name`, `shop_address`, `shop_phone` (10-digit Indian), `opening_time`/`closing_time` (`HH:MM`), `pickup_message`, `shop_status` (`open`/`closed`).

### Catalog administration

Category and product writes use `multipart/form-data`. Android should send updates as `POST` with `_method=PUT`; boolean fields may be `"1"` or `"0"`, and empty text fields become `null`. Images accept jpg/jpeg/png/webp up to 2 MB, preserve the original, are EXIF auto-oriented, and generate fingerprinted WebP large (1080px) and thumb (300px) files. `remove_image=1` clears every stored variant. `image_url` is the large conversion and `thumbnail_url` is the thumb; both are `null` when no image is set.

`GET /api/v1/admin/categories` includes inactive records in `sort_order` order:

```json
{"success":true,"message":"Categories retrieved successfully.","data":[{"id":1,"name":"Sweet Paan (Meetha)","slug":"sweet-paan-meetha","description":null,"image_url":null,"thumbnail_url":null,"sort_order":1,"is_active":true,"products_count":3}]}
```

`POST /api/v1/admin/categories` accepts `name` (unique, max 60), `description` (max 255), `sort_order`, `is_active`, `image`, and `remove_image`; it returns `201`. `POST /api/v1/admin/categories/{id}` with `_method=PUT` updates the same fields. Deleting a non-empty category returns:

```json
{"success":false,"message":"This category still has 3 products. Move or delete them first.","errors":{},"code":"CATEGORY_HAS_PRODUCTS"}
```

Product list, detail, create, and update responses include `id`, `name`, `description`, numeric `price`, `category_id`, `category`, `image_url`, `thumbnail_url`, booleans `is_available`/`is_featured`, `tag`, `prep_minutes`, `ingredients`, and `sort_order`. Product writes accept the listed fields plus `ingredients_json`, a JSON array string with at most ten strings of at most 40 characters. `[]` clears ingredients. `DELETE /api/v1/admin/products/{id}` soft deletes the product; order snapshots remain available.

### Customer administration

All customer-admin endpoints require an active admin token. Customer objects include `id`, nullable `name`, `phone`, nullable `email`, boolean `is_active`, nullable `blocked_reason`, `orders_count`, and ISO-8601 `created_at`.

`POST /api/v1/admin/customers` accepts `{ "name": "Rahul Das", "phone": "9876543210", "email": null }` and returns `201`. Phone numbers are unique Indian mobile numbers and cannot belong to an admin. A phone retained by a soft-deleted customer returns `409` with code `CUSTOMER_DELETED`.

`GET /api/v1/admin/customers/{id}` adds `recent_orders` (the latest ten summaries) and numeric `total_spent`, excluding admin-cancelled orders. `PUT /api/v1/admin/customers/{id}` updates name, email, and phone.

`PATCH /api/v1/admin/customers/{id}/block` accepts `{ "is_active": false, "reason": "Repeated abuse" }`. Blocking revokes all tokens and makes OTP sign-in and new orders fail with `403` code `CUSTOMER_BLOCKED` and message `This account is blocked. Please contact the shop.` Unblocking uses `is_active: true`.

`DELETE /api/v1/admin/customers/{id}` soft deletes the customer and removes all API/device tokens. Customers with pending, making, or ready orders return `409` code `CUSTOMER_HAS_ACTIVE_ORDERS`. Admin customer/category/product changes are recorded in the audit log.

| Resource | Endpoints | Input |
|---|---|---|
| Categories | `GET/POST /admin/categories`, `PUT/DELETE /admin/categories/{category}` | `name`, optional `description`, `image`, `sort_order`, `is_active` |
| Products | `GET/POST /admin/products`, `PUT/DELETE /admin/products/{product}` | `category_id`, `name`, non-negative `price`, optional `description`, `image`, `is_available`, `is_featured`, `sort_order`, `addon_ids[]` |
| Add-ons | `GET/POST /admin/addons`, `PUT/DELETE /admin/addons/{addon}` | `name`, non-negative `price`, optional `is_active` |

Catalog images use multipart form data and accept jpg/jpeg/png/webp up to 2 MB. Category/product slugs are generated. Category deletion returns `409` if products exist; all catalog deletes are soft deletes.

## Android / Retrofit

- Retrofit base URL: `http://paan-api.test/api/v1/` (trailing slash required).
- Add the token with an OkHttp bearer-token interceptor after login.
- Model pagination as `success`, `message`, `data`, `meta`.
- For `422`, map field arrays under `errors`; for other failures show `message`.
- The server is authoritative for totals, availability, payment, and order status.
