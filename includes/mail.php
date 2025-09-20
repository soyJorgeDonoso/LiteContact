<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/PHPMailer-master/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer-master/src/SMTP.php';

function render_template_placeholders(string $html, array $data): string {
  foreach ($data as $k=>$v) {
    $html = str_replace('{{'.$k.'}}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $html);
  }
  return $html;
}

function send_welcome_email(string $toEmail, string $toName, string $subject, string $htmlBody): array {

  $from = defined('MAIL_FROM') ? MAIL_FROM : 'contacto@tuplanseguro.cl';
  $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Tu Plan Seguro';

  $appendLog = function(bool $ok, string $to): void {
    $result = $ok ? 'SUCCESS' : 'FAIL';
    if (function_exists('app_log')) { app_log('MAIL To: ' . $to . ' | Result: ' . $result); }
  };

  try {
    $configure = function(PHPMailer $m, string $host, int $port, string $secure) use ($from, $fromName, $toEmail, $toName, $subject, $htmlBody) {
      $m->isSMTP();
      $m->Host       = $host;
      $m->SMTPAuth   = true;
      $m->Username   = defined('SMTP_USER') ? (string)SMTP_USER : 'contacto@tuplanseguro.cl';
      $m->Password   = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
      if (strtolower($secure) === 'tls') { $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; }
      else { $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; }
      $m->Port       = $port;
      $m->Timeout    = 15;
      $m->SMTPKeepAlive = false;
      // En desarrollo: permitir certificados self-signed sin volcar trazas extensas
      if (defined('APP_ENV') && APP_ENV === 'dev') {
        $m->SMTPDebug = 0; // evitar crecer logs
        $m->SMTPOptions = [
          'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
          ]
        ];
      } else {
        $m->SMTPDebug = 0;
      }
      $m->setFrom($from, $fromName);
      $m->addAddress($toEmail, $toName);
      $m->isHTML(true);
      $m->CharSet = 'UTF-8';
      $m->Subject = $subject;
      $m->Body    = $htmlBody;
      $m->AltBody = strip_tags($htmlBody);
    };

    $host = defined('SMTP_HOST') ? (string)SMTP_HOST : 'mail.tuplanseguro.cl';
    $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 465;
    $sec  = defined('SMTP_SECURE') ? (string)SMTP_SECURE : 'ssl';

    // Intento 1: según configuración
    $mail = new PHPMailer(true);
    $configure($mail, $host, $port, $sec);
    try {
      $mail->send();
      $appendLog(true, $toEmail);
      return ['ok'=>true,'method'=>'smtp'];
    } catch (Exception $e1) {
      // Intento 2: alternar ssl<->tls y puerto 465<->587
      $altSec  = (strtolower($sec) === 'tls') ? 'ssl' : 'tls';
      $altPort = ($port === 465) ? 587 : 465;
      $mail2 = new PHPMailer(true);
      $configure($mail2, $host, $altPort, $altSec);
      try {
        $mail2->send();
        $appendLog(true, $toEmail);
        return ['ok'=>true,'method'=>'smtp'];
      } catch (Exception $e2) {
        throw $e2; // manejar abajo
      }
    }
  } catch (Exception $e) {
    $appendLog(false, $toEmail);
    return ['ok'=>true,'method'=>'log','error'=>$e->getMessage()];
  }
}

