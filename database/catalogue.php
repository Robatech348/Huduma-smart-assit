<?php
declare(strict_types=1);

/**
 * Adds the eCitizen service catalogue (database/ecitizen_services.php) and
 * official "apply online" links. Only inserts services whose slug is missing,
 * so edits made by admins are never overwritten. Safe to run repeatedly.
 *
 * @return int number of services added
 */
function import_ecitizen_catalogue(PDO $pdo): int
{
    $rows = require __DIR__ . '/ecitizen_services.php';

    // Official links for the original sample services (only fills blanks).
    $links = [
        'birth-certificate' => ECITIZEN, 'passport' => ECITIZEN, 'good-conduct' => ECITIZEN,
        'driving-licence' => ECITIZEN, 'business-name' => ECITIZEN, 'marriage-certificate' => ECITIZEN,
        'kra-pin' => 'https://itax.kra.go.ke', 'sha-registration' => 'https://sha.go.ke',
        'nssf-registration' => 'https://www.nssf.or.ke', 'helb' => 'https://www.helb.co.ke',
    ];
    $upd = $pdo->prepare("UPDATE services SET apply_url = ? WHERE slug = ? AND (apply_url IS NULL OR apply_url = '')");
    foreach ($links as $slug => $link) {
        $upd->execute([$link, $slug]);
    }

    $exists = $pdo->prepare('SELECT id FROM services WHERE slug = ?');
    $ins = $pdo->prepare('INSERT INTO services (slug, name, agency, category, summary, requirements, steps, fee, processing_time, keywords, bookable, apply_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $centreIds = $pdo->query('SELECT id FROM centres')->fetchAll(PDO::FETCH_COLUMN);
    $link = $pdo->prepare('INSERT IGNORE INTO centre_services (centre_id, service_id) VALUES (?,?)');
    $added = 0;
    foreach ($rows as $r) {
        $exists->execute([$r[0]]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $ins->execute($r);
        $sid = (int)$pdo->lastInsertId();
        // Assisted help for eCitizen services is offered at every centre.
        foreach ($centreIds as $cid) {
            $link->execute([$cid, $sid]);
        }
        $added++;
    }
    return $added;
}
