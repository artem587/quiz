# QuizSpace deployment environment

## Donatello Premium

Set these values on the server `.env`:

```text
DONATELLO_PAGE_URL=https://donatello.to/YOUR_PAGE
DONATELLO_API_TOKEN=YOUR_DONATELLO_API_TOKEN
DONATELLO_SUB_PRICE=50
DONATELLO_SUB_CURRENCY=UAH
```

QuizSpace uses Donatello as a creator-page payment/matching flow. Each pending Premium payment gets a unique `QS-...` code. The teacher puts this code into the Donatello donation message. QuizSpace reads recent donations via Donatello's API (`GET /api/v1/donates`, authenticated with `X-Token`) and activates Premium only after matching the code, amount and currency.

Do not rely on an invented webhook signature or on URL parameters to identify the user: the payment is reconciled server-side against the unique code stored in `donatello_payments`.


### Premium price source

The Premium monthly price is stored in `app_settings.premium_price_uah`.
`DONATELLO_SUB_PRICE` is used only as the initial bootstrap value when the setting
does not exist yet. Existing payment rows keep their own `expected_amount`.

```sql
UPDATE app_settings
SET setting_value = '50.00'
WHERE setting_key = 'premium_price_uah';
```
