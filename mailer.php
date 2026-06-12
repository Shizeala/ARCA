<?php
// ================================================================
//  mailer.php — PHPMailer SMTP wrapper
//  Requires PHPMailer in /vendor (see README for Composer command)
// ================================================================
require_once __DIR__ . '/config.php';

// PHPMailer autoload — adjust path to match your setup
$phpmailer_path = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
if (!file_exists($phpmailer_path . 'PHPMailer.php')) {
    // Fallback: silently skip email (log the attempt)
    function send_submission_email(array $submission, ?array $student, ?array $teacher): bool {
        error_log('[ARCA Mailer] PHPMailer not found. Email skipped for submission #' . $submission['id']);
        return false;
    }
    return;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require $phpmailer_path . 'Exception.php';
require $phpmailer_path . 'PHPMailer.php';
require $phpmailer_path . 'SMTP.php';

function _make_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = MAIL_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = MAIL_PORT;
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    return $mail;
}

function _email_body(array $sub, ?array $student): string {
    $name     = htmlspecialchars($sub['student_name'] ?? 'Unknown');
    $sid      = htmlspecialchars($sub['student_id']   ?? 'N/A');
    $section  = htmlspecialchars($sub['section']      ?? 'N/A');
    $status   = strtoupper($sub['status']);
    $ts       = htmlspecialchars($sub['processed_at'] ?? $sub['uploaded_at']);
    $conf     = $sub['ai_confidence'] ? number_format($sub['ai_confidence'], 1) . '%' : 'N/A';

    return <<<HTML
    <div style="font-family:'DM Sans',Arial,sans-serif;max-width:520px;margin:0 auto;background:#f8fafc;border-radius:12px;overflow:hidden">
      <div style="background:#0f1c2e;padding:28px 32px">
        <div style="font-size:28px;color:#fff;font-weight:700;letter-spacing:-.01em">ARCA</div>
        <div style="font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-top:4px">
          Automated Record &amp; Classification Assistant
        </div>
      </div>
      <div style="padding:32px">
        <p style="color:#0f172a;font-size:15px;font-weight:600;margin-bottom:20px">
          Submission Processed
        </p>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <tr><td style="padding:8px 0;color:#64748b;width:40%">Student Name</td><td style="color:#0f172a;font-weight:500">{$name}</td></tr>
          <tr><td style="padding:8px 0;color:#64748b">Student ID</td><td style="color:#0f172a;font-family:monospace">{$sid}</td></tr>
          <tr><td style="padding:8px 0;color:#64748b">Section</td><td style="color:#0f172a">{$section}</td></tr>
          <tr><td style="padding:8px 0;color:#64748b">Status</td><td><span style="background:#dcfce7;color:#14532d;padding:2px 8px;border-radius:4px;font-size:11px">{$status}</span></td></tr>
          <tr><td style="padding:8px 0;color:#64748b">Confidence</td><td style="color:#0f172a;font-family:monospace">{$conf}</td></tr>
          <tr><td style="padding:8px 0;color:#64748b">Timestamp</td><td style="color:#0f172a;font-family:monospace;font-size:12px">{$ts}</td></tr>
        </table>
      </div>
      <div style="padding:16px 32px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8">
        This is an automated message from ARCA. Do not reply to this email.
      </div>
    </div>
    HTML;
}

/**
 * Send notification to student + teacher after processing.
 * Returns true if at least one email was sent successfully.
 */
function send_submission_email(array $submission, ?array $student, ?array $teacher): bool {
    $body    = _email_body($submission, $student);
    $subject = sprintf('[ARCA] Submission Processed — %s', $submission['student_name'] ?? 'Unknown');
    $sent    = false;

    $recipients = [];
    if ($student && !empty($student['email'])) {
        $recipients[] = [$student['email'], $student['first_name'] . ' ' . $student['last_name']];
    }
    if ($teacher && !empty($teacher['email'])) {
        $recipients[] = [$teacher['email'], $teacher['full_name'] ?? 'Teacher'];
    }

    foreach ($recipients as [$addr, $name]) {
        try {
            $mail = _make_mailer();
            $mail->addAddress($addr, $name);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);
            $mail->send();
            $sent = true;
        } catch (Exception $e) {
            error_log('[ARCA Mailer] Failed to send to ' . $addr . ': ' . $e->getMessage());
        }
    }
    return $sent;
}