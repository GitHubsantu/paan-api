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

## Order workflow

```text
pending -> making -> ready -> completed
   |          |        |
   +----------+--------+-> admin_cancelled
```

Admins transition orders. Cancelling a paid mock order changes payment status to `refunded`. Customer database notifications are generated for making, ready, completed, and cancellation.

## Admin API

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
