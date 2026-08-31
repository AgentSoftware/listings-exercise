## Event vs a command
command + cron avoids the "fire an alert every time a listing event happens" spam problem entirely, and the dedup logic falls out naturally rather than needing separate anti-spam bookkeeping



## Running Saved search alerts

Run `php artisan alerts:send` to check all saved searches for newly-live
matching listings and notify users (currently logs a digest per user —
see the command's docblock for the production notification design).

This is intended to run on a schedule, not on-demand — in production it
would be wired up via a cron job (or Laravel's scheduler) to run once a
day around midday, e.g.:

    0 12 * * *  cd /path-to-app && php artisan alerts:send

Scheduling isn't configured in this exercise; run the command manually
to see it work.

## In production 
The command would actually generate an email and then would send to AWS SQS to be processed. 
Depending on how many alerts would be sent on average you could fan the alerts batched using SQS Batch Actions. 
