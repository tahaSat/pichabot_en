# Cryptomus payment integration — planning notes

Shared notes before we implement. Source: [Cryptomus Merchant API](https://doc.cryptomus.com/).  
This app already has two crypto gateways (Plisio, NowPayments). Cryptomus would be a **new third method**, following the same pattern: create invoice → user pays → auto-confirm → `DirectPayment()`.

Prices in this app are in **USD**. Cryptomus can take USD and convert to crypto itself. We should **not** convert locally (unlike the current Plisio / NowPayments code, which still divides by a USD rate).

---

## 1. What Cryptomus gives us

Merchant invoices for a **fixed amount**. Two integration styles:

| Style | When to use | What the user sees |
| --- | --- | --- |
| **Invoice** (recommended) | Product purchase / wallet top-up with a known USD price | Cryptomus hosted page (`https://pay.cryptomus.com/pay/{uuid}`). User picks coin + network, or we lock one. |
| **Static wallet** | Recurring top-up to one address | One address per user. Any amount sent is credited. Not a good fit for priced products. |

We should use **invoices**.

After the customer pays, Cryptomus:

1. Watches the blockchain until the required confirmations land.
2. Marks the invoice `paid` / `paid_over`.
3. POSTs a webhook to our `url_callback`.
4. Optionally auto-converts the received coin to USDT in **your** merchant wallet (dashboard setting, not an API field).

That webhook is how auto-confirm works. We fulfill the order only when status is final and paid.

Important distinction:

- **Payment confirmation** means Cryptomus has accepted the customer's blockchain payment (`paid` / `paid_over`).
- **Auto-convert** means Cryptomus may then convert the received asset into USDT in the merchant wallet.

Auto-convert is not required for automatic order confirmation.

---

## 2. Credentials we need

Two different API keys exist. For **receiving** money we only need the payment pair.

| Item | Where | Used for |
| --- | --- | --- |
| **Merchant UUID** | Merchant settings | HTTP header `merchant` |
| **Payment API key** | Same merchant, after domain verification + moderation | Request + webhook signatures |
| **Payout API key** | Personal account → Business settings | Withdrawals only. Not needed now. |

The merchant setup requires entering and verifying a domain, followed by moderation. Use HTTPS for the production webhook. The public API pages do not clearly state that every `url_callback` must be on that exact verified domain, so confirm any callback-domain restriction in the merchant account before deployment.

Auth on every request:

```
POST https://api.cryptomus.com/v1/...
Headers:
  merchant: <MERCHANT_UUID>
  sign:     md5(base64(json_body) + PAYMENT_API_KEY)
  Content-Type: application/json
```

PHP:

```php
$json = json_encode($data, JSON_UNESCAPED_UNICODE);
$sign = md5(base64_encode($json) . $API_KEY);
```

Empty body → sign an empty string.

The signature must be calculated from the **exact JSON string sent in the HTTP body**. Build the JSON once, sign that string, and pass that same string to curl. Re-encoding or changing formatting after signing can invalidate the signature.

Official PHP SDK: `composer require cryptomus/api-php-sdk`  
We can also call the HTTP API with curl, same as Zaripal / Plisio.

---

## 3. USD → crypto (they convert, we do not)

Create invoice with the product price in USD. Cryptomus applies its rate.

### Option A — user chooses coin (simplest)

```json
{
  "amount": "15.00",
  "currency": "USD",
  "order_id": "a1b2c3d4e5",
  "url_callback": "https://YOUR_DOMAIN/payment/cryptomus.php"
}
```

User opens `result.url` and picks BTC / USDT-TRC20 / etc. Cryptomus converts 15 USD to that coin.

### Option B — lock to one coin, any network

```json
{
  "amount": "15.00",
  "currency": "USD",
  "to_currency": "USDT",
  "order_id": "a1b2c3d4e5",
  "url_callback": "https://YOUR_DOMAIN/payment/cryptomus.php"
}
```

Customer pays USDT. Network still chosen on the page unless we also pass `network`.

### Option C — lock coin + network (address ready immediately)

```json
{
  "amount": "15.00",
  "currency": "USD",
  "to_currency": "USDT",
  "network": "tron",
  "order_id": "a1b2c3d4e5",
  "url_callback": "https://YOUR_DOMAIN/payment/cryptomus.php"
}
```

Invoice already has a Tron USDT address. We can still send the hosted page URL, or show address + QR ourselves.

`to_currency` must be a **crypto** code, never fiat.

Optional: `course_source` = `Binance` | `BinanceP2P` | `Exmo` | `Kucoin`. Default = Cryptomus rates.

**Recommendation:** start with **Option A** (USD invoice, user picks coin). Same UX as Plisio. Lock to USDT-TRC20 later if you want cheaper / faster confirms.

The available choices are controlled by the merchant's Cryptomus settings. We can further restrict them with `currencies` / `except_currencies`. We should not hard-code availability, minimums, maximums, or fees: query `POST /v1/payment/services`, because those values are returned per currency and network and may change.

For example, this keeps the USD-priced invoice but permits only USDT on Tron:

```json
"currencies": [
  {
    "currency": "USDT",
    "network": "tron"
  }
]
```

### What we store vs what they pay

| Field | Meaning |
| --- | --- |
| `amount` + `currency` | Our price: e.g. `15.00` USD |
| `payer_amount` + `payer_currency` | What the customer must send in crypto |
| `payment_amount` | What they actually sent |
| `payment_amount_usd` | Actual payment in USD (webhook) |
| `merchant_amount` | Credited to you after Cryptomus fee |

For a fixed-price purchase, we credit/deliver against the **USD invoice amount** (`Payment_report.price`), not the crypto amount. Same as Plisio / NowPayments today.

Before fulfillment, bind the callback to our stored order:

- `type` must be `payment`.
- `order_id` must identify an existing `Payment_report` whose method is `cryptomus`.
- Callback `uuid` must equal the Cryptomus UUID stored when the invoice was created.
- Stored USD price must equal the original invoice amount/currency. Use decimal-safe comparison, not binary floating point.
- Status must be `paid` or `paid_over`, and `is_final` must be true.

For extra assurance, retrieve `/v1/payment/info` and validate its `uuid`, `order_id`, amount, currency, status, and final flag before delivering.

---

## 4. Auto-confirmation — how it actually works

Cryptomus confirms on-chain. We confirm in our bot from the webhook. No admin tap.

### Status timeline

```
invoice created
    → check              waiting for a tx on-chain
    → process            payment processing
    → confirm_check      tx seen, waiting for network confirmations
    → paid               exact amount, confirmed          ✅ fulfill
    → paid_over          overpaid, confirmed              ✅ fulfill
    → wrong_amount_waiting underpaid, can still top up    ⏳ wait
    → wrong_amount       underpaid/final                   ⚠️ manual handling
    → cancel             never paid, expired              ❌
    → fail / system_fail error                            ❌
    → locked             AML hold                         ⚠️ wait/support
```

**Fulfill only when `status` is `paid` or `paid_over`.**  
Also check `is_final === true` so the invoice cannot receive more money.

`confirm_check` is **not** paid yet. Do not deliver the product there.

Confirmation count is per network (USDT-TRC20 is fast, BTC is slower). We do not set that in the API; Cryptomus waits, then sends `paid`.

`wrong_amount` is not equivalent to “no payment.” The customer sent funds but sent too little. Keep the record for support/reconciliation and notify an administrator rather than silently treating it like `cancel`. Cryptomus also provides `POST /v1/payment/mark-as-paid` for accepting `wrong_amount` / `wrong_amount_waiting`; it queues an asynchronous operation. This must be a deliberate admin action, never automatic.

If `is_payment_multiple` is false, the first payment finalizes the invoice even when it is insufficient. With the default `true`, an underpayment can remain `wrong_amount_waiting` until the customer completes it; otherwise it becomes final `wrong_amount` at expiry.

The global status page contains statuses that the webhook page's shorter list omits. Handle the complete status set when polling, and fail closed on unknown future statuses.

### Webhook (this is the auto-confirm path)

On every status change, POST to `url_callback` from invoice creation.

Webhook IP (whitelist if we can): **`91.227.144.54`**

Always verify the signature:

1. Read JSON body.
2. Pull out `sign`, remove it from the array.
3. `hash = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $PAYMENT_API_KEY)`
4. Compare with `hash_equals`.
5. PHP `json_encode` escapes slashes (`\/`). Cryptomus expects that. Do not use `JSON_UNESCAPED_SLASHES`.

Then:

- Look up `Payment_report` by `order_id` (our invoice id).
- Validate method, stored UUID, original USD amount, and currency—not only `order_id`.
- If already `paid`, acknowledge and stop (idempotent).
- If `paid` / `paid_over` → `DirectPayment($order_id)` + cashback + report, then mark `paid`.
- If `cancel` → mark expired and tell the user.
- If `wrong_amount` → preserve as underpaid, alert support/admin, and do not deliver.
- If `fail`, `system_fail`, or `locked` → do not deliver; preserve diagnostics and alert admin where appropriate.
- Ignore `check`, `process`, `confirm_check`, and `wrong_amount_waiting` for fulfillment (optionally show progress).

Recommended: double-check with `POST /v1/payment/info` using the stored `uuid` before fulfilling, same as NowPayments does with `StatusPayment()`.

`txid` is not required for a legitimate payment. Internal Cryptomus P2P payments do not use the blockchain and may omit `txid`; their `transfer_id` identifies the internal transfer. A manually accepted payment can also lack `txid`. Never reject an otherwise verified `paid` event only because `txid` is absent.

Test webhooks without real money: `POST /v1/test-webhook/payment`  
Resend a missed webhook: `POST /v2/payment/resend` (only for finalized invoices; max 10 times).

The current webhook page documents when callbacks are sent and how to verify them, but it does **not clearly document automatic retry count, retry schedule, or response-body contract**. Our endpoint should still return HTTP 200 quickly after successful durable processing and a non-2xx response on invalid/failed processing. We cannot assume Cryptomus will retry automatically; reconciliation must cover missed callbacks.

### Idempotency and concurrency

The handler must assume duplicate, replayed, overlapping, or out-of-order callbacks. A simple “read status, then deliver” check is not concurrency-safe. The Cryptomus signature has no timestamp or nonce, so it authenticates payload contents but does not itself prevent replay.

Before `DirectPayment()`, atomically claim the order in the database (transaction, row lock, or conditional status update). Only the request that successfully changes `Unpaid`/pending to a processing state may deliver. Prefer a unique fulfillment record or durable outbox keyed by `cryptomus:{uuid}`, then make delivery retry-safe. This avoids duplicate delivery if the process crashes between the external action and the final `paid` update.

Webhook processing should be quick. Avoid returning success before the payment state has been durably stored.

### Merchant auto-convert to USDT (your wallet, not the customer)

Dashboard: Business → Merchants → Merchant Settings → **Auto-Convert**.

When enabled, after the customer pays BTC (or whatever), Cryptomus converts it to USDT in your merchant balance. Webhook may include:

```json
"convert": {
  "to_currency": "USDT",
  "commission": null,
  "rate": "0.07700000",
  "amount": "0.22638000"
}
```

This is independent of invoice currency. Customer still pays whatever coin they chose; you receive the configured settlement asset.

The webhook documentation describes conversion from `payer_currency` to USDT and includes a `commission` field. A Cryptomus blog says auto-convert has no extra fee, but we should not encode that claim into business logic; confirm the current fee shown in your merchant account before launch.

---

## 5. Invoice fields we care about

`POST https://api.cryptomus.com/v1/payment`

| Field | Required | Suggested value |
| --- | --- | --- |
| `amount` | yes | USD price as string, e.g. `"15.00"` |
| `currency` | yes | `"USD"` |
| `order_id` | yes | Our `Payment_report.id_order` (alphanumeric, dash, underscore; unique) |
| `url_callback` | yes for auto-confirm | `https://{domainhosts}/payment/cryptomus.php` |
| `url_return` | optional | Telegram bot deep link / site |
| `url_success` | optional | Same |
| `lifetime` | optional | 3600 default (5 min–12 h). Match “valid for 1 hour” copy. |
| `is_payment_multiple` | optional | `true` default. Underpay can be topped up. Set `false` if we want first tx to close the invoice. |
| `accuracy_payment_percent` | optional | 0–5. e.g. `1` = accept 99% as paid. Helps with dust / fee rounding. |
| `additional_data` | optional | Telegram user id (not shown to customer) |
| `to_currency` / `network` | optional | Only if we lock coin/network |
| `currencies` | optional | Restrict coins, e.g. only USDT |
| `subtract` | optional | 0–100. % of Cryptomus fee paid by customer. 0 = we pay the fee. |
| `discount_percent` | optional | -99–100. Positive = customer discount; negative = surcharge. Applied only when the invoice has a specific cryptocurrency. Separate from `subtract`. |
| `is_refresh` | optional | Refresh an expired invoice using the same `order_id`. Only address, payment status, and expiration change; other new parameter values are ignored. |

`order_id` must be unique. If we send an existing one, they return the old invoice instead of creating a new one.

For exact-price digital goods, keep `accuracy_payment_percent: 0` unless the business explicitly accepts receiving less. At `5`, Cryptomus can mark an invoice paid after receiving only 95%, and only the amount actually received reaches the merchant.

Response we need:

- `uuid` — store in `dec_not_confirmed` (same as Plisio `txn_id` / NowPayments `id`)
- `url` — pay button
- `expired_at` — unix timestamp
- `address` / `network` / `payer_amount` — only if coin+network locked

### API response and retry handling

- Success uses `state: 0` with a `result` object. Do not consider HTTP 200 alone a successful invoice.
- Validation/business failures generally use `state: 1`; documented examples include unsupported currency/network, service not found, min/max violations, missing merchant wallet, blocked payments, conversion failure, gateway/terminal/server errors.
- Validation errors are documented as HTTP 422; internal failures as HTTP 500.
- The docs do not publish a numerical API rate limit.
- Use finite connect/read timeouts and log a redacted response.
- If invoice creation times out or the response is lost, **query `/v1/payment/info` with the same `order_id` before retrying**. `order_id` is idempotent: an existing invoice is returned rather than a second one being created.
- Reusing an `order_id` with a different cart, amount, callback, or currency does not update the old invoice. Never reuse an order ID for a changed obligation.
- Persist the pending `Payment_report` before (or in coordination with) invoice creation so an exceptionally fast callback always has an order to find.

### Reconciliation backup

A small scheduled job should query `/v1/payment/info` for Cryptomus records that remain pending beyond a short delay. It should use the same verification and atomic fulfillment path as the webhook.

`POST /v1/payment/list` also provides cursor-paginated invoice history with optional `date_from` / `date_to`, useful for broader reconciliation. Those filters use invoice **creation time**, not update time, so history scans alone can miss an old invoice that changed recently. Poll known local pending UUIDs individually through `/v1/payment/info`.

Cryptomus `expired_at` / remote final status should be authoritative for this gateway. The app currently has a generic 24-hour unpaid-payment expiry job while the proposed Cryptomus invoice lasts one hour. A delayed, verified `paid` event for a locally expired order must not be silently discarded: either safely restore and fulfill it, or put it into a paid-but-needs-support state if the original product can no longer be delivered.

### Refunds and QR codes

- `POST /v1/payment/refund` refunds a completed payment to an explicitly supplied address. It needs `uuid` or `order_id`, `address`, and `is_subtract` (whether refund network commission comes from merchant balance or reduces the refund). Refunds can fail when payouts are blocked, merchant wallet is missing, funds are insufficient, or the invoice is not completed.
- The documented refund request has no `amount` parameter. Do not promise partial refunds or automatic refund of only the excess from `paid_over`; confirm that workflow with Cryptomus support first.
- Refunds should be an authenticated admin operation. Never infer a refund destination from untrusted callback input; verify currency/network and have the customer/admin confirm the address.
- Refund statuses are `refund_process`, `refund_paid`, and `refund_fail`. They must not trigger product delivery or a second balance adjustment.
- If we later show payment details inside Telegram, `POST /v1/payment/qr` returns a base64 QR image for an invoice UUID. The hosted payment page already handles this, so it is optional.

### Testing limitations

`POST /v1/test-webhook/payment` tests callback reachability and signature validation only. It does not persist an invoice or test USD conversion, exchange rates, blockchain confirmations, merchant credit, reconciliation, or real fulfillment ordering.

The simulator also cannot generate every real status (notably `confirm_check`, `wrong_amount_waiting`, and `locked`). Cover those with local synthetic tests or controlled real payments. Its validation limits differ from production: callback URL is limited to 150 characters and test `order_id` to 32, while invoice creation permits 255 and 128 respectively.

Where an endpoint accepts multiple identifiers, send exactly one—prefer the stored UUID. The documented precedence differs between payment info, refund, resend, and webhook testing.

---

## 6. How this maps onto pichabot

Existing crypto flow (Plisio as example):

1. User picks method in the pay keyboard (`keyboard.php` → `callback_data: "plisio"`).
2. `index.php` checks min/max USD, creates `Payment_report` (`Unpaid`), calls gateway, sends pay URL.
3. Confirm happens later:
   - **NowPayments:** webhook `payment/nowpayment.php` when `payment_status == finished`, then `DirectPayment()`.
   - **Plisio:** cron `cronbot/plisio.php` polls until `completed`, then `DirectPayment()`.
4. `DirectPayment($order_id)` delivers the product or credits the wallet.

Cryptomus should use a webhook as the primary path, like NowPayments, plus a narrow reconciliation cron as a reliability backup.

### Files we will touch (when we implement)

| File | Change |
| --- | --- |
| `panel/inc/payments_lib.php` | New gateway in `PAYMENT_GATEWAYS` |
| `table.php` | Default `PaySetting` rows |
| `keyboard.php` | Button when gateway is on |
| `index.php` | `elseif ($datain == "cryptomus")` create invoice |
| `function.php` | `createPayCryptomus()` + optional `cryptomusPaymentInfo()` |
| `payment/cryptomus.php` | Webhook handler (new file, clone of `payment/nowpayment.php`) |
| `cronbot/cryptomus.php` | Reconcile stale pending invoices via payment info (recommended) |
| `admin.php` + panel payment methods UI | Toggle, API key, merchant UUID, min/max, cashback, help text |
| `textbot` / `text.json` | Button label + user copy |

`mirzabot/` is a parallel copy. Same changes if that bot should accept Cryptomus too.

### Settings to store in `PaySetting`

| Key | Purpose |
| --- | --- |
| `statuscryptomus` | on/off |
| `merchant_cryptomus` | Merchant UUID |
| `apicryptomus` | Payment API key |
| `minbalancecryptomus` / `maxbalancecryptomus` | USD limits |
| `chashbackcryptomus` | Cashback % |
| `helpcryptomus` | Pre-pay help (text/photo/video) |
| `textcryptomus` | Button label in `textbot` |

Do **not** commit real keys. Put them in the admin panel / PaySetting only.

### `Payment_report` usage

Same columns as other gateways:

- `id_order` → Cryptomus `order_id`
- `price` → USD amount we credit
- `Payment_Method` → `cryptomus`
- `payment_Status` → `Unpaid` → `paid` / `expire`
- `id_invoice` → existing `"getconfigafterpay|{username}"` or wallet-topup payload
- `dec_not_confirmed` → Cryptomus invoice `uuid`

---

## 7. Recommended default behavior

Until we decide otherwise:

1. Create invoice with `currency: USD`, `amount: product USD`, no `to_currency` (user picks coin).
2. `url_callback` = our webhook.
3. `lifetime: 3600`.
4. Fulfill on `paid` or `paid_over` only, after signature check, order/UUID/amount binding, payment-info verification, and an atomic idempotency claim.
5. Credit `Payment_report.price` (USD), ignore crypto dust / overpay for wallet credit. Overpay stays in your Cryptomus balance.
6. Enable Auto-Convert to USDT in the Cryptomus dashboard so merchant balance is stable.
7. Query `/v1/payment/info` before `DirectPayment()` as a second check.
8. Reconcile stale pending invoices on a cron because callback retry behavior is not fully documented.
9. Keep underpayments in a distinct support-visible status; never silently discard them.
10. Keep `accuracy_payment_percent: 0` initially.

---

## 8. Open decisions (discuss before coding)

1. **Coin choice:** free (any coin) vs lock USDT vs lock USDT + TRON?
2. **Who pays Cryptomus fee?** `subtract: 0` (we pay) vs `100` (customer pays ~1–2% extra).
3. **Partial payments:** keep `is_payment_multiple: true` or close after first tx?
4. **Underpay tolerance:** `accuracy_payment_percent` 0 or 1–2%?
5. **Reconciliation frequency:** recommended webhook + cron; choose how often the backup runs.
6. **Which bots:** `pichabot` only, or `mirzabot` too?
7. **Hosted page vs in-Telegram address/QR?** Hosted page is less work and matches Plisio. Address+QR needs `to_currency` + `network`.
8. **Min invoice:** services have per-coin/network minimums. Query the merchant-specific service list; do not rely on sample values in the docs.
9. **Underpayment operations:** admin mark-as-paid, manual refund/support, or never accept?
10. **Refund UI:** include it in the first release or handle refunds manually in Cryptomus initially?

---

## 9. Security checklist

- Verify webhook `sign` on every request.
- Whitelist `91.227.144.54` if the host allows it.
- Treat the IP allowlist as defense-in-depth, not a replacement for signature verification.
- Bind `order_id`, stored Cryptomus `uuid`, method, original amount, and currency.
- Never fulfill on `confirm_check`.
- Use an atomic database claim; a read-only “already paid” check is insufficient under concurrent callbacks.
- Prefer protected environment/secret storage or encrypted-at-rest configuration. At minimum, never put the API key in git, logs, bot messages, or broadly readable admin output.
- Use an HTTPS callback URL; confirm any same-domain restriction in the merchant account because the public invoice page does not document one.
- Reject non-POST requests, malformed JSON, missing fields, invalid signatures, and unknown orders.
- Log statuses and identifiers, but redact API keys and avoid logging full sensitive payloads unnecessarily.
- Use constant-time `hash_equals` for signatures.
- Return success only after durable processing; add reconciliation for missed webhooks.
- Do not require `txid`; accept verified internal P2P payments and store `transfer_id` when present.
- Treat unknown statuses as non-payable and alert rather than guessing.

---

## 10. Official links

- Overview: https://doc.cryptomus.com/
- API keys: https://doc.cryptomus.com/merchant-api/getting-api-keys
- Request format / sign: https://doc.cryptomus.com/merchant-api/request-format
- Create invoice: https://doc.cryptomus.com/merchant-api/payments/creating-invoice
- Payment info: https://doc.cryptomus.com/merchant-api/payments/payment-information
- Payment history: https://doc.cryptomus.com/merchant-api/payments/payment-history
- Webhook: https://doc.cryptomus.com/merchant-api/payments/webhook
- Statuses: https://doc.cryptomus.com/merchant-api/payments/payment-statuses
- Test webhook: https://doc.cryptomus.com/merchant-api/payments/testing-webhook
- Resend webhook: https://doc.cryptomus.com/merchant-api/payments/resend-webhook
- Services / limits / fees: https://doc.cryptomus.com/merchant-api/payments/list-of-services
- Refund: https://doc.cryptomus.com/merchant-api/payments/refund
- QR code: https://doc.cryptomus.com/merchant-api/payments/qr-code-pay-form
- PHP SDK: https://doc.cryptomus.com/sdks-and-modules/php-sdk
- Auto-convert (dashboard): https://cryptomus.com/blog/how-to-use-the-auto-convert-option-a-step-by-step-guide
