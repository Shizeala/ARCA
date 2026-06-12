<?php
// =============================================================
//  process.php — CLEAN BARCODE PROCESSOR (FINAL VERSION)
// =============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const BATCH_SIZE = 20;

// ─────────────────────────────────────────────
// Logger
// ─────────────────────────────────────────────
function log_msg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// ─────────────────────────────────────────────
// CLEAN BARCODE OUTPUT (IMPORTANT FIX)
// ─────────────────────────────────────────────
function clean_barcode($raw): ?string
{
    if (!$raw) return null;

    // remove HTML tags (<pre>, <td>, etc.)
    $clean = strip_tags($raw);

    // remove all whitespace (spaces, tabs, newlines, NBSP)
    $clean = preg_replace('/\s+/u', '', $clean);

    // remove anything not numeric (barcode = digits only)
    $clean = preg_replace('/[^0-9]/', '', $clean);

    return $clean ?: null;
}

// ─────────────────────────────────────────────
// ZXING BARCODE DECODER
// ─────────────────────────────────────────────
function decode_barcode($image_path)
{
    $fullPath = __DIR__ . '/' . $image_path;

    if (!file_exists($fullPath)) {
        return null;
    }

    $ch = curl_init("https://zxing.org/w/decode");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            "file" => new CURLFile($fullPath)
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return null;

    // safer parsing (handles <pre>, spaces, formatting changes)
    if (preg_match('/Raw text.*?<td[^>]*>(.*?)<\/td>/s', $response, $m)) {
        return html_entity_decode($m[1]);
    }

    return null;
}

// ─────────────────────────────────────────────
// MAIN
// ─────────────────────────────────────────────
try {
    $pdo = db();

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, image_path
        FROM submissions
        WHERE status = 'pending'
        ORDER BY uploaded_at ASC
        LIMIT :limit
        FOR UPDATE
    ");

    $stmt->bindValue(':limit', BATCH_SIZE, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (!$rows) {
        $pdo->rollBack();
        log_msg("No pending submissions.");
        exit;
    }

    $ids = array_column($rows, 'id');

    $pdo->prepare("
        UPDATE submissions
        SET status = 'processing'
        WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
    ")->execute($ids);

    $pdo->commit();

    log_msg("Claimed " . count($rows) . " submission(s)");

} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    log_msg("DB ERROR: " . $e->getMessage());
    exit;
}

// ─────────────────────────────────────────────
// UPDATE QUERY
// ─────────────────────────────────────────────
$update = $pdo->prepare("
    UPDATE submissions
    SET status = :status,
        student_name = :name,
        student_id = :sid,
        section = :section,
        ai_confidence = :conf,
        ai_raw = :raw,
        processed_at = NOW()
    WHERE id = :id
");

// ─────────────────────────────────────────────
// PROCESS LOOP
// ─────────────────────────────────────────────
foreach ($rows as $row) {

    $id   = $row['id'];
    $path = $row['image_path'];

    log_msg("Processing #$id");

    try {

        // STEP 1: decode barcode
        $raw = decode_barcode($path);
        $student_id = clean_barcode($raw);

        log_msg("  RAW   : [" . ($raw ?? 'NULL') . "]");
        log_msg("  CLEAN : [" . ($student_id ?? 'NULL') . "]");

        $name = "Unnamed";
        $section = null;
        $confidence = 0.0;

        // STEP 2: lookup student
        if ($student_id) {

            $stmt = $pdo->prepare("
                SELECT first_name, last_name, middle_initial, section
                FROM students
                WHERE student_id = ?
            ");

            $stmt->execute([$student_id]);
            $student = $stmt->fetch();

            if ($student) {

                $name = "{$student['first_name']} {$student['middle_initial']}. {$student['last_name']}";
                $section = $student['section'];
                $confidence = 100.0;

                log_msg("  ✓ FOUND: $name | $section");

            } else {
                $name = "Unknown Student";
                $confidence = 50.0;

                log_msg("  ⚠ NOT FOUND: $student_id");
            }
        }

        // STEP 3: save result
        $update->execute([
            ':status'  => 'done',
            ':name'    => $name,
            ':sid'     => $student_id,
            ':section' => $section,
            ':conf'    => $confidence,
            ':raw'     => json_encode([
                'raw'   => $raw,
                'clean' => $student_id
            ]),
            ':id' => $id
        ]);

        log_msg("  DONE ✓");

    } catch (Throwable $e) {

        $pdo->prepare("
            UPDATE submissions
            SET status = 'error', processed_at = NOW()
            WHERE id = ?
        ")->execute([$id]);

        log_msg("  ERROR: " . $e->getMessage());
    }
}

log_msg("Batch complete.");
exit;