<?php
declare(strict_types=1);

final class BookingException extends RuntimeException {}

function active_services(bool $bookableOnly = false): array
{
    return q_all('SELECT * FROM services WHERE active = 1' . ($bookableOnly ? ' AND bookable = 1' : '') . ' ORDER BY category, name');
}

function centres_for_service(int $serviceId): array
{
    return q_all(
        'SELECT c.* FROM centres c JOIN centre_services cs ON cs.centre_id = c.id
          WHERE cs.service_id = ? AND c.active = 1 ORDER BY c.name',
        [$serviceId]
    );
}

function centre_offers_service(int $centreId, int $serviceId): bool
{
    return (bool)q_val(
        'SELECT 1 FROM centre_services cs JOIN centres c ON c.id = cs.centre_id JOIN services s ON s.id = cs.service_id
          WHERE cs.centre_id = ? AND cs.service_id = ? AND c.active = 1 AND s.active = 1 AND s.bookable = 1',
        [$centreId, $serviceId]
    );
}

/** Earliest moment a slot may still be booked. */
function booking_cutoff(): DateTimeImmutable
{
    return (new DateTimeImmutable())->modify('+' . (int)config('app.booking_cutoff_minutes', 60) . ' minutes');
}

/**
 * Upcoming slots for a centre grouped by date:
 * ['2026-11-07' => [ ['id'=>..,'start_time'=>..,'remaining'=>..,'available'=>bool], ...], ...]
 */
function upcoming_slots(int $centreId, ?int $days = null): array
{
    $days ??= (int)config('app.booking_window_days', 21);
    $rows = q_all(
        'SELECT id, slot_date, start_time, end_time, capacity, booked, status
           FROM slots
          WHERE centre_id = ? AND slot_date BETWEEN CURDATE() AND CURDATE() + INTERVAL ? DAY
          ORDER BY slot_date, start_time',
        [$centreId, $days]
    );
    $cutoff = booking_cutoff();
    $out = [];
    foreach ($rows as $r) {
        $start = new DateTimeImmutable($r['slot_date'] . ' ' . $r['start_time']);
        $remaining = max(0, (int)$r['capacity'] - (int)$r['booked']);
        $r['remaining'] = $remaining;
        $r['available'] = $r['status'] === 'open' && $remaining > 0 && $start > $cutoff;
        $r['past'] = $start <= $cutoff;
        if ($r['past']) {
            continue;
        }
        $out[$r['slot_date']][] = $r;
    }
    return $out;
}

/**
 * Atomically book a slot. Locks the slot row so concurrent bookings can never
 * exceed the capacity set by the centre.
 *
 * @param array $data full_name, email, phone, needs_assistance
 * @return int appointment id
 */
function book_appointment(int $slotId, int $serviceId, array $data, string $channel = 'online', ?int $staffId = null): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $slot = q_one('SELECT * FROM slots WHERE id = ? FOR UPDATE', [$slotId]);
        if (!$slot) {
            throw new BookingException('That time slot no longer exists. Please choose another.');
        }
        if (!centre_offers_service((int)$slot['centre_id'], $serviceId)) {
            throw new BookingException('This centre does not offer the selected service.');
        }
        $start = new DateTimeImmutable($slot['slot_date'] . ' ' . $slot['start_time']);
        // Staff doing assisted booking can book a same-day walk-in slot up to its start time.
        $cutoff = $channel === 'assisted' ? new DateTimeImmutable() : booking_cutoff();
        if ($start <= $cutoff) {
            throw new BookingException('That time slot has closed for booking. Please choose a later time.');
        }
        if ($slot['status'] !== 'open') {
            throw new BookingException('That time slot is closed. Please choose another.');
        }
        if ((int)$slot['booked'] >= (int)$slot['capacity']) {
            throw new BookingException('Sorry, that time slot has just been filled. Please choose another time.');
        }

        // One active booking per person per service, to keep capacity fair.
        if (!empty($data['email'])) {
            $dup = q_val(
                "SELECT a.reference FROM appointments a JOIN slots s ON s.id = a.slot_id
                  WHERE a.email = ? AND a.service_id = ? AND a.status = 'booked' AND s.slot_date >= CURDATE()",
                [$data['email'], $serviceId]
            );
            if ($dup) {
                throw new BookingException("You already have an upcoming appointment for this service (reference {$dup}). Cancel it first if you need a different time.");
            }
        }

        $ref = make_reference('HSA', 'appointments');
        q(
            'INSERT INTO appointments (reference, manage_token, slot_id, centre_id, service_id, full_name, email, phone, channel, needs_assistance, created_by, citizen_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $ref, make_token(), $slotId, $slot['centre_id'], $serviceId,
                $data['full_name'], $data['email'] ?: null, $data['phone'] ?: null,
                $channel, !empty($data['needs_assistance']) ? 1 : 0, $staffId,
                !empty($data['citizen_id']) ? (int)$data['citizen_id'] : null,
            ]
        );
        $id = (int)$pdo->lastInsertId();
        q('UPDATE slots SET booked = booked + 1 WHERE id = ?', [$slotId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    notify_booking_confirmed($id);
    return $id;
}

function cancel_appointment(int $appointmentId, bool $notify = true): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $a = q_one('SELECT * FROM appointments WHERE id = ? FOR UPDATE', [$appointmentId]);
        if (!$a || $a['status'] !== 'booked') {
            $pdo->rollBack();
            return false;
        }
        q("UPDATE appointments SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$appointmentId]);
        q('UPDATE slots SET booked = GREATEST(booked - 1, 0) WHERE id = ?', [$a['slot_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    if ($notify) {
        notify_booking_cancelled($appointmentId);
    }
    return true;
}

/** Validate citizen details from a booking form. Returns [data, errors]. */
function validate_booking_details(array $src, bool $emailRequired = true): array
{
    $errors = [];
    $name = trim((string)($src['full_name'] ?? ''));
    $email = strtolower(trim((string)($src['email'] ?? '')));
    $phone = trim((string)($src['phone'] ?? ''));

    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
        $errors['full_name'] = 'Enter your full name (3–120 characters).';
    }
    if ($email === '' && $emailRequired) {
        $errors['email'] = 'Enter an email address so we can send your confirmation.';
    } elseif ($email !== '' && !valid_email($email)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($phone !== '' && !valid_phone($phone)) {
        $errors['phone'] = 'Enter a valid phone number, e.g. 0712 345 678.';
    }
    return [[
        'full_name'        => $name,
        'email'            => $email,
        'phone'            => $phone !== '' ? normalise_phone($phone) : '',
        'needs_assistance' => !empty($src['needs_assistance']),
    ], $errors];
}

/**
 * Generate slots for a centre over a date range.
 * @return array{created:int, skipped:int}
 */
function generate_slots(int $centreId, string $from, string $to, array $weekdays, string $startTime, string $endTime, int $intervalMinutes, int $capacity): array
{
    $created = 0;
    $skipped = 0;
    $day = new DateTimeImmutable($from);
    $last = new DateTimeImmutable($to);
    $stmt = db()->prepare('INSERT IGNORE INTO slots (centre_id, slot_date, start_time, end_time, capacity) VALUES (?,?,?,?,?)');
    while ($day <= $last) {
        if (in_array((int)$day->format('N'), $weekdays, true)) {
            $t = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $startTime);
            $end = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $endTime);
            while ($t->modify("+{$intervalMinutes} minutes") <= $end) {
                $next = $t->modify("+{$intervalMinutes} minutes");
                $stmt->execute([$centreId, $day->format('Y-m-d'), $t->format('H:i:s'), $next->format('H:i:s'), $capacity]);
                $stmt->rowCount() ? $created++ : $skipped++;
                $t = $next;
            }
        }
        $day = $day->modify('+1 day');
    }
    return ['created' => $created, 'skipped' => $skipped];
}
