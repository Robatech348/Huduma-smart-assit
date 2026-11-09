<?php
/**
 * Scheduled housekeeping. Run every 15–60 minutes:
 *   C:\xamppp\php\php.exe "C:\xamppp\htdocs\huduma smart assist\cron\run.php"
 * (Windows Task Scheduler) or `php cron/run.php` from cron on Linux.
 *
 * - Retries queued/failed emails
 * - Sends reminders for tomorrow's appointments (once each)
 * - Marks yesterday's un-attended bookings as no-shows
 * - Cleans up old login attempts
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$reminders = send_due_reminders();
$noShows = mark_past_no_shows();
$mail = Mailer::processQueue();
q('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');

printf(
    "[%s] reminders=%d no_shows=%d mail_processed=%d mail_delivered=%d\n",
    date('Y-m-d H:i:s'), $reminders, $noShows, $mail['processed'], $mail['delivered']
);
