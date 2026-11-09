<?php
declare(strict_types=1);

/**
 * Email templates for citizen notifications. Each function queues and delivers
 * through Mailer. Messages never include sensitive documents or ID numbers.
 */

function email_layout(string $heading, string $intro, array $rows = [], ?array $cta = null, array $list = [], string $listTitle = '', string $footerNote = ''): array
{
    $app = config('app.name');
    $rowsHtml = '';
    $rowsText = '';
    foreach ($rows as $label => $value) {
        $rowsHtml .= '<tr><td style="padding:6px 12px 6px 0;color:#64716B;font-size:14px;white-space:nowrap;vertical-align:top">' . e($label) . '</td><td style="padding:6px 0;font-size:14px;color:#17251F;font-weight:600">' . e($value) . '</td></tr>';
        $rowsText .= $label . ': ' . $value . "\n";
    }
    $listHtml = '';
    $listText = '';
    if ($list) {
        $listHtml = '<p style="margin:20px 0 6px;font-weight:700;color:#17251F">' . e($listTitle) . '</p><ul style="margin:0;padding-left:20px;color:#17251F;font-size:14px;line-height:1.6">';
        $listText = "\n" . $listTitle . "\n";
        foreach ($list as $item) {
            $listHtml .= '<li>' . e($item) . '</li>';
            $listText .= ' - ' . $item . "\n";
        }
        $listHtml .= '</ul>';
    }
    $ctaHtml = $cta ? '<p style="margin:24px 0"><a href="' . e($cta[1]) . '" style="background:#087F68;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;display:inline-block">' . e($cta[0]) . '</a></p>' : '';
    $ctaText = $cta ? "\n" . $cta[0] . ': ' . $cta[1] . "\n" : '';
    $note = $footerNote ?: 'Do not reply with documents or personal identification numbers. For help call ' . config('app.support_phone') . '.';

    $html = '<!doctype html><html><body style="margin:0;background:#F5F4EE;font-family:Segoe UI,Arial,sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F4EE;padding:24px 0"><tr><td align="center">'
        . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border-radius:12px;overflow:hidden">'
        . '<tr><td style="background:#17251F;padding:18px 24px;color:#D9EF7A;font-weight:700;letter-spacing:1px;font-size:13px">' . e(strtoupper($app)) . '</td></tr>'
        . '<tr><td style="padding:24px">'
        . '<h1 style="margin:0 0 12px;font-size:22px;color:#17251F">' . e($heading) . '</h1>'
        . '<p style="margin:0 0 16px;color:#17251F;font-size:15px;line-height:1.5">' . nl2br(e($intro)) . '</p>'
        . ($rowsHtml ? '<table cellpadding="0" cellspacing="0" style="border-top:1px solid #D8D9D2;border-bottom:1px solid #D8D9D2;width:100%;padding:8px 0">' . $rowsHtml . '</table>' : '')
        . $listHtml . $ctaHtml
        . '<p style="margin:24px 0 0;color:#64716B;font-size:12px;line-height:1.5">' . e($note) . '</p>'
        . '</td></tr></table></td></tr></table></body></html>';

    $text = strtoupper($app) . "\n\n" . $heading . "\n\n" . $intro . "\n\n" . $rowsText . $listText . $ctaText . "\n" . $note . "\n";
    return [$text, $html];
}

function appointment_details(int $appointmentId): ?array
{
    return q_one(
        'SELECT a.*, s.slot_date, s.start_time, s.end_time, c.name AS centre_name, c.address AS centre_address,
                sv.name AS service_name, sv.requirements, sv.fee, sv.slug AS service_slug
           FROM appointments a
           JOIN slots s ON s.id = a.slot_id
           JOIN centres c ON c.id = a.centre_id
           JOIN services sv ON sv.id = a.service_id
          WHERE a.id = ?',
        [$appointmentId]
    );
}

function notify_booking_confirmed(int $appointmentId): void
{
    $a = appointment_details($appointmentId);
    if (!$a || !$a['email']) {
        return;
    }
    [$text, $html] = email_layout(
        'Your appointment is confirmed',
        "Hello {$a['full_name']},\nYour Huduma Centre appointment is booked. Please arrive 10 minutes early and show this reference at the service desk.",
        [
            'Reference' => $a['reference'],
            'Service'   => $a['service_name'],
            'Centre'    => $a['centre_name'] . ' — ' . $a['centre_address'],
            'Date'      => fmt_date($a['slot_date']),
            'Time'      => fmt_time($a['start_time']) . ' – ' . fmt_time($a['end_time']),
            'Fee'       => $a['fee'],
        ],
        ['View or cancel appointment', abs_url('booking.php', ['token' => $a['manage_token']])],
        lines($a['requirements']),
        'What to bring'
    );
    Mailer::queue($a['email'], "Appointment confirmed — {$a['reference']}", $text, $html, 'booking_confirmed', $a['reference']);
}

function notify_booking_cancelled(int $appointmentId): void
{
    $a = appointment_details($appointmentId);
    if (!$a || !$a['email']) {
        return;
    }
    [$text, $html] = email_layout(
        'Your appointment was cancelled',
        "Hello {$a['full_name']},\nYour appointment {$a['reference']} for {$a['service_name']} on " . fmt_date($a['slot_date']) . ' at ' . fmt_time($a['start_time']) . ' has been cancelled. The slot has been released for others.',
        [],
        ['Book a new appointment', abs_url('book.php', ['service' => $a['service_id']])]
    );
    Mailer::queue($a['email'], "Appointment cancelled — {$a['reference']}", $text, $html, 'booking_cancelled', $a['reference']);
}

function notify_booking_reminder(int $appointmentId): bool
{
    $a = appointment_details($appointmentId);
    if (!$a || !$a['email']) {
        return false;
    }
    [$text, $html] = email_layout(
        'Reminder: your appointment is tomorrow',
        "Hello {$a['full_name']},\nThis is a reminder of your Huduma Centre appointment. If you can no longer attend, please cancel so someone else can use the slot.",
        [
            'Reference' => $a['reference'],
            'Service'   => $a['service_name'],
            'Centre'    => $a['centre_name'] . ' — ' . $a['centre_address'],
            'Date'      => fmt_date($a['slot_date']),
            'Time'      => fmt_time($a['start_time']),
        ],
        ['View or cancel appointment', abs_url('booking.php', ['token' => $a['manage_token']])],
        lines($a['requirements']),
        'Preparation checklist'
    );
    Mailer::queue($a['email'], "Reminder: appointment tomorrow — {$a['reference']}", $text, $html, 'reminder', $a['reference']);
    return true;
}

function notify_feedback_request(int $appointmentId): void
{
    $a = appointment_details($appointmentId);
    if (!$a || !$a['email']) {
        return;
    }
    [$text, $html] = email_layout(
        'How was your visit?',
        "Hello {$a['full_name']},\nThank you for visiting {$a['centre_name']}. It takes less than a minute to tell us how it went, and it helps us improve.",
        ['Reference' => $a['reference'], 'Service' => $a['service_name']],
        ['Rate your visit', abs_url('feedback.php', ['token' => $a['manage_token']])]
    );
    Mailer::queue($a['email'], 'How was your Huduma visit?', $text, $html, 'feedback_request', $a['reference']);
    q('UPDATE appointments SET feedback_requested_at = NOW() WHERE id = ?', [$appointmentId]);
}

function notify_request_status(int $requestId, string $publicNote = ''): void
{
    $r = q_one(
        'SELECT r.*, sv.name AS service_name, c.name AS centre_name
           FROM service_requests r JOIN services sv ON sv.id = r.service_id JOIN centres c ON c.id = r.centre_id
          WHERE r.id = ?',
        [$requestId]
    );
    if (!$r || !$r['email']) {
        return;
    }
    [$label, , $explain] = REQUEST_STATUSES[$r['status']];
    $rows = [
        'Reference' => $r['reference'],
        'Service'   => $r['service_name'],
        'Centre'    => $r['centre_name'],
        'Status'    => $label,
    ];
    if ($publicNote !== '') {
        $rows['Message from staff'] = $publicNote;
    }
    $isNew = $r['status'] === 'submitted';
    [$text, $html] = email_layout(
        $isNew ? 'Your service request has been received' : 'Your request status has changed',
        "Hello {$r['full_name']},\n{$explain}",
        $rows,
        ['Track your request', abs_url('track.php', ['token' => $r['track_token']])]
    );
    $subject = $isNew ? "Request received — {$r['reference']}" : "Request update: {$label} — {$r['reference']}";
    Mailer::queue($r['email'], $subject, $text, $html, 'request_status', $r['reference']);
}

/** Send reminders for tomorrow's appointments. Returns number sent. */
function send_due_reminders(): int
{
    $rows = q_all(
        "SELECT a.id FROM appointments a JOIN slots s ON s.id = a.slot_id
          WHERE a.status = 'booked' AND a.reminder_sent_at IS NULL AND a.email IS NOT NULL
            AND s.slot_date = CURDATE() + INTERVAL 1 DAY"
    );
    $n = 0;
    foreach ($rows as $row) {
        if (notify_booking_reminder((int)$row['id'])) {
            q('UPDATE appointments SET reminder_sent_at = NOW() WHERE id = ?', [$row['id']]);
            $n++;
        }
    }
    return $n;
}

/** Mark past, un-attended bookings as no-shows (end of day housekeeping). */
function mark_past_no_shows(): int
{
    return q(
        "UPDATE appointments a JOIN slots s ON s.id = a.slot_id
            SET a.status = 'no_show'
          WHERE a.status = 'booked' AND s.slot_date < CURDATE()"
    )->rowCount();
}
