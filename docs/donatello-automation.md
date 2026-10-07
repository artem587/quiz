# Donatello payment reconciliation

QuizSpace checks the read-only Donatello donations API and matches each donation to a pending payment using the unique `QS-...` code in its message. A match must meet the configured amount and currency. The database transaction marks the payment paid and extends the teacher's Premium expiry once.

## Required setup

Set `DONATELLO_PAGE_URL` and `DONATELLO_API_TOKEN` in `.env`. The token must have Donatello API access to read donations. Configure price, duration and currency at **Admin → Premium**. Teachers still complete payment on the configured Donatello creator page and include the displayed payment code in the donation message.

## Scheduled sync

Run `php cron/donatello-sync.php` from the project directory. The script is CLI-only, processes pending payments against up to the 100 most recent API donations, and uses a MySQL advisory lock to prevent concurrent scheduled runs. Configure the host's scheduler to run it every few minutes. For local XAMPP on Windows, create a Windows Task Scheduler task that runs `C:\xampp\php\php.exe` with the script's absolute path as its argument.

Admins can also trigger a sync from **Admin → Premium**. The teacher's subscription page continues to sync that teacher's own pending payment while open.

Payment records and their reconciliation status are visible in the Admin → Premium table. Sync/API errors are written to `storage/donatello_payments.log`.
