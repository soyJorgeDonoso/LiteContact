<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

// Utilidad global para normalizar fecha/hora desde inputs del popup
if (!function_exists('normalize_datetime_input')) {
  function normalize_datetime_input(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;
    $v = str_replace('T', ' ', $raw);
    $v = preg_replace('/\s+/', ' ', $v ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $v)) { $v .= ':00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) { $v .= ' 00:00:00'; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $v)) return null;
    return $v;
  }
}

// Expande una consulta con placeholders "?" reemplazándolos por valores ya citados
if (!function_exists('expand_sql')) {
  function expand_sql(string $sql, array $params, PDO $pdo): string {
    foreach ($params as $value) {
      if ($value === null) {
        $rep = 'NULL';
      } else if (is_int($value) || is_float($value)) {
        $rep = (string)$value;
      } else {
        $rep = $pdo->quote((string)$value);
      }
      $sql = preg_replace('/\?/', $rep, $sql, 1);
    }
    return $sql;
  }
}

$pdo = getPDO();
$tbl = contacts_table($pdo);

// Acciones POST
$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
    $notice = 'Token inválido';
  } else {
    $action = (string)($_POST['action'] ?? '');
    // Acciones masivas por AJAX
    if ($action === 'bulk_action') {
      header('Content-Type: application/json; charset=UTF-8');
      try {
        $bulk = (string)($_POST['bulk'] ?? '');
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        if (!$bulk || !$ids) { echo json_encode(['ok'=>false,'message'=>'Selecciona registros y acción']); exit; }
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($bulk === 'update_next_contact') {
          $dlRaw = trim((string)($_POST['next_contact'] ?? ''));
          if ($dlRaw === '') { echo json_encode(['ok'=>false,'message'=>'Debes indicar fecha/hora']); exit; }
          $dlSan = preg_replace('/[^0-9T:\-]/','', $dlRaw); $dlSan = str_replace('T',' ', $dlSan); if (strlen($dlSan)===16) { $dlSan .= ':00'; }
          $sql = 'UPDATE ' . $tbl['name'] . ' SET deadline_at=?, updated_at=NOW() WHERE id IN (' . $in . ')';
          $st = $pdo->prepare($sql); $st->execute(array_merge([$dlSan], $ids));
          if (function_exists('app_log')) { app_log('BULK update_next_contact ids=' . implode(',', $ids) . ' -> ' . $dlSan); }
          echo json_encode(['ok'=>true,'message'=>'Próximo contacto actualizado']); exit;
        } else if ($bulk === 'change_state') {
          $newStatus = (string)($_POST['state'] ?? 'Nuevo');
          $allowed = ['Nuevo','En proceso','Pospuesto','Convertido','Cerrado'];
          if (!in_array($newStatus, $allowed, true)) { $newStatus = 'Nuevo'; }
          $sql = 'UPDATE ' . $tbl['name'] . ' SET status=?, updated_at=NOW() WHERE id IN (' . $in . ')';
          $st = $pdo->prepare($sql); $st->execute(array_merge([$newStatus], $ids));
          if (function_exists('app_log')) { app_log('BULK change_state ids=' . implode(',', $ids) . ' -> ' . $newStatus); }
          echo json_encode(['ok'=>true,'message'=>'Estado actualizado']); exit;
        } else if ($bulk === 'delete') {
          // Borrado lógico: desactivar contactos y su historial
          $pdo->beginTransaction();
          try {
            $sql1 = 'UPDATE ' . $tbl['name'] . ' SET is_active=0, updated_at=NOW() WHERE id IN (' . $in . ')';
            $st1 = $pdo->prepare($sql1); $st1->execute($ids);
            $sql2 = 'UPDATE contact_history SET is_active=0 WHERE contact_id IN (' . $in . ')';
            $st2 = $pdo->prepare($sql2); $st2->execute($ids);
            $pdo->commit();
          } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
          }
          if (function_exists('app_log')) { app_log('BULK soft-delete ids=' . implode(',', $ids)); }
          echo json_encode(['ok'=>true,'message'=>'Contactos desactivados']); exit;
        }
        echo json_encode(['ok'=>false,'message'=>'Acción no reconocida']); exit;
      } catch (Throwable $e) {
        if (function_exists('app_log')) { app_log('BULK error: ' . $e->getMessage()); }
        echo json_encode(['ok'=>false,'message'=>'Error interno']); exit;
      }
    }
    if ($action === 'set_status') {
      // Normalizador robusto para fecha/hora (acepta "YYYY-MM-DDTHH:MM", "YYYY-MM-DD HH:MM[:SS]")
      if (!function_exists('normalize_datetime_input')) {
        function normalize_datetime_input(string $raw): ?string {
          $raw = trim($raw);
          if ($raw === '') return null;
          $v = str_replace('T', ' ', $raw);
          $v = preg_replace('/\s+/', ' ', $v ?? '');
          // Añadir segundos si faltan
          if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $v)) { $v .= ':00'; }
          // Solo fecha
          if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) { $v .= ' 00:00:00'; }
          // Validar formato final
          if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $v)) return null;
          return $v;
        }
      }
      $id = (int)($_POST['id'] ?? 0);
      $statusIn = (string)($_POST['status'] ?? 'Nuevo');
      $allowed = ['Nuevo','En proceso','Pospuesto','Convertido','Cerrado'];
      $newStatus = in_array($statusIn, $allowed, true) ? $statusIn : 'Nuevo';
      $noteIn = trim((string)($_POST['note'] ?? ''));
      $dlRaw = trim((string)($_POST['deadline_at'] ?? ($_POST['next_contact_at'] ?? '')));
      $deadlineAt = normalize_datetime_input($dlRaw ?? '');
      try {
        // Estado anterior
        $st = $pdo->prepare('SELECT status FROM contact_status WHERE id=?');
        $st->execute([$id]);
        $oldStatus = (string)$st->fetchColumn();
        // Persistir contacto
        $upd = $pdo->prepare('UPDATE contact_status SET status=?, deadline_at=?, last_contact_at=NOW(), alert_3d_sent=0, alert_1d_sent=0, alert_2h_sent=0 WHERE id=?');
        $upd->execute([$newStatus, $deadlineAt, $id]);
        // Historial
        $userName = isset($_SESSION['admin_id']) ? ('admin#' . (int)$_SESSION['admin_id']) : null;
        $h = $pdo->prepare('INSERT INTO contact_history (contact_id, old_status, new_status, note, user) VALUES (?,?,?,?,?)');
        $h->execute([$id, $oldStatus !== '' ? $oldStatus : null, $newStatus, $noteIn !== '' ? $noteIn : null, $userName]);
        $notice = 'Contacto actualizado';
      } catch (Throwable $e) { log_error('set_status persist: ' . $e->getMessage()); $notice = 'Error al actualizar contacto'; }
    } else if ($action === 'log_mail') {
      $id = (int)($_POST['id'] ?? 0);
      try {
        $hist = $pdo->prepare('INSERT INTO contact_interactions (source, contact_id, type, note) VALUES (?,?,?,?)');
        $hist->execute([$tbl['source'], $id, 'email', trim((string)($_POST['note'] ?? 'correo enviado'))]);
        if (function_exists('app_log')) { app_log('ADMIN registró interacción de correo para contacto #' . $id); }
        // Historial también
        $st = $pdo->prepare('SELECT status FROM contact_status WHERE id=?');
        $st->execute([$id]);
        $cur = (string)$st->fetchColumn();
        $h = $pdo->prepare('INSERT INTO contact_history (contact_id, old_status, new_status, note, user) VALUES (?,?,?,?,?)');
        $userName = isset($_SESSION['admin_id']) ? ('admin#' . (int)$_SESSION['admin_id']) : null;
        $h->execute([$id, $cur, $cur, 'Correo registrado: ' . trim((string)($_POST['note'] ?? '')), $userName]);
        if (function_exists('app_log')) { app_log('ADMIN actualizó historial para contacto #' . $id); }
        $notice = 'Registrada interacción de correo';
      } catch (Throwable $e) { log_error('log_mail persist: ' . $e->getMessage()); $notice = 'Error al registrar'; }
    } else if ($action === 'export_csv') {
      header('Content-Type: text/csv; charset=UTF-8');
      header('Content-Disposition: attachment; filename="contactos.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, ['id','created_at','full_name','rut','age','phone','email','commune','income','isapre','charges','status','deadline_at']);
      $sql = 'SELECT id, created_at, full_name, rut, age, phone, email, commune, income, isapre, charges, status, deadline_at FROM ' . $tbl['name'] . ' ORDER BY created_at DESC';
      foreach ($pdo->query($sql) as $r) { fputcsv($out, $r); }
      fclose($out);
      exit;
    } else if ($action === 'log_contact') {
      $id = (int)($_POST['id'] ?? 0);
      $type = trim((string)($_POST['contact_type'] ?? 'Otro'));
      $type_detail = trim((string)($_POST['contact_type_detail'] ?? ''));
      $noteIn = trim((string)($_POST['note'] ?? ''));
      $doChange = (int)($_POST['do_change'] ?? 0) === 1;
      $newStatus = (string)($_POST['new_status'] ?? '');
      $allowed = ['Nuevo','En proceso','Pospuesto','Convertido','Cerrado'];
      if ($doChange && !in_array($newStatus, $allowed, true)) { $newStatus = 'Nuevo'; }
      $dlRaw = trim((string)($_POST['deadline_at'] ?? ($_POST['next_contact_at'] ?? '')));
      $deadlineAt = normalize_datetime_input($dlRaw ?? '');
      // Nota opcional; tipo "Otro" también opcional
      try {
        // Estado actual
        $st = $pdo->prepare('SELECT status FROM contact_status WHERE id=?');
        $st->execute([$id]);
        $oldStatus = (string)$st->fetchColumn();
        $finalStatus = $doChange ? $newStatus : $oldStatus;
        // Persistir deadline y, si corresponde, cambio de estado
        if ($doChange) {
          $upd = $pdo->prepare('UPDATE contact_status SET status=?, deadline_at=?, last_contact_at=NOW(), alert_3d_sent=0, alert_1d_sent=0, alert_2h_sent=0 WHERE id=?');
          $upd->execute([$finalStatus, $deadlineAt, $id]);
        } else if ($deadlineAt !== null || ((int)($_POST['deadline_defined'] ?? 1) === 0)) {
          // Si el usuario desmarca "Definir próxima fecha", limpiar (NULL) deadline
          $upd = $pdo->prepare('UPDATE contact_status SET deadline_at=?, last_contact_at=NOW(), alert_3d_sent=0, alert_1d_sent=0, alert_2h_sent=0 WHERE id=?');
          $upd->execute([$deadlineAt, $id]);
        } else {
          $upd = $pdo->prepare('UPDATE contact_status SET last_contact_at=NOW() WHERE id=?');
          $upd->execute([$id]);
        }
        // Registrar historial con tipo, nota y próxima fecha
        $typeStr = $type . ($type_detail ? ' (' . $type_detail . ')' : '');
        $summary = 'Tipo: ' . $typeStr . ($deadlineAt ? ' · Próximo: ' . $deadlineAt : '') . ' · Nota: ' . $noteIn;
        $userName = isset($_SESSION['admin_id']) ? ('admin#' . (int)$_SESSION['admin_id']) : null;
        $h = $pdo->prepare('INSERT INTO contact_history (contact_id, old_status, new_status, note, tipo_contacto, user) VALUES (?,?,?,?,?,?)');
        $h->execute([$id, $oldStatus !== '' ? $oldStatus : null, $finalStatus, $summary, $typeStr, $userName]);
        // Respuesta JSON si es AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest') {
          header('Content-Type: application/json; charset=UTF-8');
          echo json_encode(['ok'=>true,'message'=>'Contacto registrado','status'=>$finalStatus,'deadline_at'=>$deadlineAt]);
          exit;
        }
        $notice = 'Contacto registrado correctamente';
      } catch (Throwable $e) { log_error('log_contact persist: ' . $e->getMessage()); $notice = 'Error al registrar contacto'; }
    }
  }
}

// Filtros
$q = trim((string)($_GET['q'] ?? ''));
$isapre = trim((string)($_GET['isapre'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$deadlineDateEq = trim((string)($_GET['deadline_date'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$overdue = (int)($_GET['overdue'] ?? 0) === 1;
$upcoming = (int)($_GET['upcoming'] ?? 0) === 1;
$hasFilters = ($q !== '' || $isapre !== '' || $status !== '' || $deadlineDateEq !== '' || $from !== '' || $to !== '' || $overdue || $upcoming || ((int)($_GET['lvl3'] ?? 0) === 1));

$limit = 20; $page = max(1, (int)($_GET['page'] ?? 1)); $offset = ($page-1)*$limit;
// Ordenamiento
$allowedSort = [
  'id' => 's.id',
  'created_at' => 's.created_at',
  'updated_at' => 's.updated_at',
  'full_name' => 's.full_name',
  'age' => 's.age',
  'whatsapp' => 'ccd.whatsapp',
  'isapre' => 's.isapre',
  // alias calculado en SELECT
  'next_contact' => 'deadline_efectivo',
  'deadline_at' => 'deadline_efectivo'
];
$sort = strtolower((string)($_GET['sort'] ?? 'next_contact'));
$order = strtolower((string)($_GET['order'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
if (!isset($allowedSort[$sort])) { $sort = 'next_contact'; }

// Consulta
$where = []; $params = [];
$lvl3 = (int)($_GET['lvl3'] ?? 0);
if ($q !== '') {
  $where[] = '(
    s.full_name LIKE ? OR s.email LIKE ? OR ccd.email LIKE ?
    OR s.commune LIKE ? OR s.phone LIKE ? OR ccd.phone LIKE ? OR ccd.whatsapp LIKE ?
    OR s.rut LIKE ?
    OR EXISTS (
      SELECT 1 FROM contact_contact_extra cce
      WHERE cce.contact_id = s.id AND (cce.label LIKE ? OR cce.value LIKE ?)
    )
  )';
  $params[] = "%$q%"; // nombre
  $params[] = "%$q%"; // email original
  $params[] = "%$q%"; // email confirmado
  $params[] = "%$q%"; // comuna
  $params[] = "%$q%"; // teléfono original
  $params[] = "%$q%"; // teléfono confirmado
  $params[] = "%$q%"; // whatsapp confirmado
  $params[] = "%$q%"; // RUT
  $params[] = "%$q%"; // extra label
  $params[] = "%$q%"; // extra value
}
if ($isapre !== '') { $where[] = 's.isapre = ?'; $params[] = $isapre; }
if ($status !== '') { $where[] = 's.status = ?'; $params[] = $status; }
if ($deadlineDateEq !== '') { $where[] = "s.deadline_at IS NOT NULL"; $where[] = "s.deadline_at > '1000-01-01 00:00:00'"; $where[] = "DATE(s.deadline_at) = ?"; $params[] = $deadlineDateEq; }
// Rango de creación desde Dashboard (hoy/semana/mes)
$fromDate = $from; $toDate = $to;
if ($fromDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) { $fromDate = ''; }
if ($toDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) { $toDate = ''; }
if ($fromDate !== '' && $toDate !== '') { $where[] = "DATE(s.created_at) BETWEEN ? AND ?"; $params[] = $fromDate; $params[] = $toDate; }
else if ($fromDate !== '') { $where[] = "DATE(s.created_at) >= ?"; $params[] = $fromDate; }
else if ($toDate !== '') { $where[] = "DATE(s.created_at) <= ?"; $params[] = $toDate; }
if ($overdue) { $where[] = "s.deadline_at IS NOT NULL AND s.deadline_at > '1000-01-01 00:00:00' AND TIMESTAMPDIFF(SECOND, NOW(), s.deadline_at) < 0"; }
if ($upcoming) { $where[] = "s.deadline_at IS NOT NULL AND s.deadline_at > '1000-01-01 00:00:00' AND TIMESTAMPDIFF(SECOND, NOW(), s.deadline_at) BETWEEN 0 AND 432000"; }
if ($lvl3 === 1) { $where[] = "s.deadline_at IS NOT NULL AND s.deadline_at > '1000-01-01 00:00:00' AND TIMESTAMPDIFF(HOUR, NOW(), s.deadline_at) BETWEEN 0 AND 12"; }
  // Solo activos por defecto
  $where[] = 's.is_active = 1';
  $whereSql = 'WHERE ' . implode(' AND ', $where);

$total = 0; $rows = [];
try {
  $sub = 'contact_status';
  $fromJoin = ' FROM ' . $sub . ' s LEFT JOIN contact_contact_data ccd ON ccd.contact_id = s.id ';
  $sqlCount = 'SELECT COUNT(*)' . $fromJoin . $whereSql;
  if (function_exists('app_log')) { app_log('[contacts] COUNT SQL=' . $sqlCount . ' | params=' . json_encode($params)); }
  $sqlCountExpanded = expand_sql($sqlCount, $params, $pdo);
  $stmt = $pdo->prepare($sqlCount);
  $stmt->execute($params);
  $total = (int)$stmt->fetchColumn();

  // ORDER BY: próximo contacto nulos al final
  $deadlineExpr = "CASE WHEN s.deadline_at IS NULL THEN NULL WHEN TRIM(s.deadline_at) = '' THEN NULL WHEN s.deadline_at <= '1000-01-01 00:00:00' THEN NULL ELSE STR_TO_DATE(s.deadline_at, '%Y-%m-%d %H:%i:%s') END";
  $orderBy = ($sort === 'next_contact')
    ? 'CASE WHEN deadline_efectivo IS NULL THEN 1 ELSE 0 END, TIMESTAMPDIFF(SECOND, NOW(), deadline_efectivo) ' . $order . ', s.id DESC'
    : $allowedSort[$sort] . ' ' . $order . ', s.id DESC';
  $sqlList = 'SELECT s.*, s.id AS id, ccd.whatsapp AS whatsapp_confirmed, ' . $deadlineExpr . ' AS deadline_efectivo, ' .
             'TIMESTAMPDIFF(DAY, NOW(), ' . $deadlineExpr . ') AS dias_restantes, TIMESTAMPDIFF(HOUR, NOW(), ' . $deadlineExpr . ') AS horas_restantes ' .
             $fromJoin . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
  $sqlListExpanded = expand_sql($sqlList, $params, $pdo);
  if (function_exists('app_log')) { app_log('[contacts] LIST SQL=' . $sqlList . ' | params=' . json_encode($params)); }
  if (isset($_GET['debug']) && $_GET['debug'] === 'sql') {
    echo '<pre style="white-space:pre-wrap">SQL (raw): ' . htmlspecialchars($sqlList) . "\nparams=" . htmlspecialchars(json_encode($params)) . "\nSQL (expanded): " . htmlspecialchars($sqlListExpanded) . "\nCOUNT SQL: " . htmlspecialchars($sqlCount) . "\nCOUNT params=" . htmlspecialchars(json_encode($params)) . "\nCOUNT SQL (expanded): " . htmlspecialchars($sqlCountExpanded) . '</pre>';
  }
  $stmt = $pdo->prepare($sqlList);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();
} catch (Throwable $e) { log_error('load contacts: ' . $e->getMessage()); }

// Inicializa paginación segura
$pages = 1;
if ($limit > 0) { $pages = max(1, (int)ceil($total / $limit)); }

// Cargar umbrales desde settings (valor + unidad + color)
$rem = [
  'r1v'=>5, 'r1u'=>'d', 'c1'=>'#fde68a',
  'r2v'=>2, 'r2u'=>'d', 'c2'=>'#fb923c',
  'r3v'=>12,'r3u'=>'h', 'c3'=>'#ef4444',
];
try {
  // Intentar con esquema key_name/value (no sobrescribir el filtro $q)
  $sqlSettings = "SELECT key_name AS k, value AS v FROM settings WHERE key_name IN ('reminder_1_value','reminder_1_unit','reminder_2_value','reminder_2_unit','reminder_3_value','reminder_3_unit','reminder_1_color','reminder_2_color','reminder_3_color')";
  $stSet = $pdo->query($sqlSettings);
  if ($stSet) {
    while ($row = $stSet->fetch()) {
      switch ($row['k']) {
        case 'reminder_1_value': $rem['r1v'] = (int)$row['v']; break;
        case 'reminder_1_unit':  $rem['r1u'] = (string)$row['v']; break;
        case 'reminder_2_value': $rem['r2v'] = (int)$row['v']; break;
        case 'reminder_2_unit':  $rem['r2u'] = (string)$row['v']; break;
        case 'reminder_3_value': $rem['r3v'] = (int)$row['v']; break;
        case 'reminder_3_unit':  $rem['r3u'] = (string)$row['v']; break;
        case 'reminder_1_color': $rem['c1']  = (string)$row['v']; break;
        case 'reminder_2_color': $rem['c2']  = (string)$row['v']; break;
        case 'reminder_3_color': $rem['c3']  = (string)$row['v']; break;
      }
    }
  }
} catch (Throwable $e) {
  try {
    // Fallback a esquema k/v
    $stSet = $pdo->query("SELECT k,v FROM settings WHERE k IN ('reminder_1_value','reminder_1_unit','reminder_2_value','reminder_2_unit','reminder_3_value','reminder_3_unit','reminder_1_color','reminder_2_color','reminder_3_color')");
    while ($row = $stSet->fetch()) {
      switch ($row['k']) {
        case 'reminder_1_value': $rem['r1v'] = (int)$row['v']; break;
        case 'reminder_1_unit':  $rem['r1u'] = (string)$row['v']; break;
        case 'reminder_2_value': $rem['r2v'] = (int)$row['v']; break;
        case 'reminder_2_unit':  $rem['r2u'] = (string)$row['v']; break;
        case 'reminder_3_value': $rem['r3v'] = (int)$row['v']; break;
        case 'reminder_3_unit':  $rem['r3u'] = (string)$row['v']; break;
        case 'reminder_1_color': $rem['c1']  = (string)$row['v']; break;
        case 'reminder_2_color': $rem['c2']  = (string)$row['v']; break;
        case 'reminder_3_color': $rem['c3']  = (string)$row['v']; break;
      }
    }
  } catch (Throwable $e2) { if (function_exists('app_log')) { app_log('load settings reminders failed: ' . $e2->getMessage()); } }
}
// Convertir a segundos
$thr1 = ($rem['r1u']==='h' ? $rem['r1v']*3600 : $rem['r1v']*86400);
$thr2 = ($rem['r2u']==='h' ? $rem['r2v']*3600 : $rem['r2v']*86400);
$thr3 = ($rem['r3u']==='h' ? $rem['r3v']*3600 : $rem['r3v']*86400);
// Ventana para campana: usar exactamente el umbral nivel 3
$thr3Window = $thr3;

// Conteo de alarmas nivel 3 (más urgentes): total de registros cuyo próximo contacto
// ocurre dentro del umbral r3 (en segundos) y aún no ha vencido (>= 0)
try {
  $subAlerts = "(SELECT s1.*, CASE WHEN s1.deadline_at IS NULL THEN NULL WHEN s1.deadline_at = '' THEN NULL WHEN s1.deadline_at <= '1000-01-01 00:00:00' THEN NULL ELSE s1.deadline_at END AS deadline_efectivo FROM contact_status s1)";
  $deadlineExpr = 's.deadline_efectivo';
  // Estados activos parametrizados
  $activeStatuses = ['Nuevo','En proceso','Pospuesto'];
  $in = implode(',', array_fill(0, count($activeStatuses), '?'));
  $sqlAlertsCount = 'SELECT COUNT(*) FROM ' . $subAlerts . ' s '
    . 'WHERE s.status IN (' . $in . ') '
    . 'AND ' . $deadlineExpr . ' IS NOT NULL '
    . 'AND TIMESTAMPDIFF(SECOND, NOW(), ' . $deadlineExpr . ') BETWEEN 0 AND ?';
  $alertsParams = array_merge($activeStatuses, [(int)$thr3]);
  $sqlAlertsCountExpanded = expand_sql($sqlAlertsCount, $alertsParams, $pdo);
  $stCnt = $pdo->prepare($sqlAlertsCount);
  $stCnt->execute($alertsParams);
  $alertsCount = (int)$stCnt->fetchColumn();
  // IDs para desplegable (máx 20)
  $sqlAlertIds = 'SELECT s.id FROM ' . $subAlerts . ' s '
    . 'WHERE s.status IN (' . $in . ') '
    . 'AND ' . $deadlineExpr . ' IS NOT NULL '
    . 'AND TIMESTAMPDIFF(SECOND, NOW(), ' . $deadlineExpr . ') BETWEEN 0 AND ? '
    . 'ORDER BY TIMESTAMPDIFF(SECOND, NOW(), ' . $deadlineExpr . ') ASC LIMIT 20';
  $sqlAlertIdsExpanded = expand_sql($sqlAlertIds, $alertsParams, $pdo);
  $stIds = $pdo->prepare($sqlAlertIds);
  $stIds->execute($alertsParams);
  $alerts = array_map('intval', $stIds->fetchAll(PDO::FETCH_COLUMN));
  if (isset($_GET['debug']) && in_array($_GET['debug'], ['sql','alerts'], true)) {
    echo '<pre style="white-space:pre-wrap">ALERTS COUNT SQL (raw): ' . htmlspecialchars($sqlAlertsCount) . "\nparams=" . htmlspecialchars(json_encode($alertsParams)) . "\nALERTS COUNT SQL (expanded): " . htmlspecialchars($sqlAlertsCountExpanded) . "\ncount=" . (int)$alertsCount . "\nALERT IDS SQL (raw): " . htmlspecialchars($sqlAlertIds) . "\nparams=" . htmlspecialchars(json_encode($alertsParams)) . "\nALERT IDS SQL (expanded): " . htmlspecialchars($sqlAlertIdsExpanded) . "\nids=" . htmlspecialchars(json_encode($alerts)) . '</pre>';
  }
} catch (Throwable $e) { $alerts = []; $alertsCount = 0; log_error('alerts count: ' . $e->getMessage()); }

// Interacciones + historial para timeline
$interactionsById = [];
try{
  $ids = array_map(function($r){ return (int)$r['id']; }, $rows);
  if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtI = $pdo->prepare('SELECT contact_id, type, note FROM contact_interactions WHERE source=? AND contact_id IN (' . $placeholders . ') ORDER BY id ASC');
    $stmtI->execute(array_merge([$tbl['source']], $ids));
    while($it = $stmtI->fetch()){
      $cid = (int)$it['contact_id'];
      if (!isset($interactionsById[$cid])) $interactionsById[$cid] = [];
      $interactionsById[$cid][] = ['type'=>$it['type'], 'note'=>$it['note']];
    }
    $stmtH = $pdo->prepare('SELECT contact_id, old_status, new_status, note, created_at FROM contact_history WHERE contact_id IN (' . $placeholders . ') ORDER BY id ASC');
    $stmtH->execute($ids);
    while($ht = $stmtH->fetch()){
      $cid = (int)$ht['contact_id'];
      if (!isset($interactionsById[$cid])) $interactionsById[$cid] = [];
      $label = 'estado';
      $note = '[' . (string)$ht['created_at'] . '] ' . (string)$ht['old_status'] . ' → ' . (string)$ht['new_status'] . ($ht['note']? (' · ' . $ht['note']) : '');
      $interactionsById[$cid][] = ['type'=>$label, 'note'=>$note];
    }
  }
} catch (Throwable $e) { log_error('load interactions/history: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Contactos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <?php $adminCss = (file_exists(__DIR__ . '/../assets/css/admin.min.css') ? '../assets/css/admin.min.css' : '../assets/css/admin.css'); $adminCssVer = @filemtime(__DIR__ . '/../' . basename($adminCss)) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminCssVer ?>" />
</head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>
  

  <main class="container-fluid my-3">
    <?php if ($notice): ?><div class="alert alert-info"><?= e($notice) ?></div><?php endif; ?>
    <?php if (isset($_GET['debug']) && in_array($_GET['debug'], ['sql','alerts'], true) && isset($sqlList)): ?>
      <div class="alert alert-info" style="white-space:pre-wrap">
        <strong>SQL:</strong> <?= e($sqlList) ?>
        <?php if (!empty($params)): ?>
        \n<strong>params:</strong> <?= e(json_encode($params)) ?>
        <?php endif; ?>
        <?php if (isset($sqlAlertsCount)): ?>
        \n<strong>ALERTS COUNT SQL:</strong> <?= e($sqlAlertsCount) ?>
        \n<strong>ALERT IDS SQL:</strong> <?= e($sqlAlertIds ?? '') ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <form id="filtersForm" class="row g-2 align-items-end mb-3" method="get">
      <div class="col-12 col-md-6 col-lg-4"><input class="form-control input-sm" type="text" name="q" placeholder="Buscar (nombre/email/comuna)" value="<?= e($q) ?>" /></div>
      <div class="col-6 col-md-3 col-lg-2"><input class="form-control input-sm" type="text" name="isapre" placeholder="Isapre" value="<?= e($isapre) ?>" /></div>
      <div class="col-6 col-md-3 col-lg-2">
        <select class="form-select select-sm" name="status">
          <option value="">Estado</option>
          <?php foreach (['Nuevo','En proceso','Pospuesto','Convertido','Cerrado'] as $st): ?>
            <option value="<?= e($st) ?>" <?= $status===$st?'selected':'' ?>><?= e($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label" for="f_deadline_date">Próximo contacto (exacto)</label>
        <input id="f_deadline_date" class="form-control input-sm" type="date" name="deadline_date" value="<?= e($deadlineDateEq) ?>" placeholder="Próximo contacto en fecha" />
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label" for="f_from">Ingreso desde</label>
        <input id="f_from" class="form-control input-sm" type="date" name="from" value="<?= e($from) ?>" placeholder="Desde" />
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label" for="f_to">Ingreso hasta</label>
        <input id="f_to" class="form-control input-sm" type="date" name="to" value="<?= e($to) ?>" placeholder="Hasta" />
      </div>
      <div class="col-6 col-md-3 col-lg-1 d-grid"><button class="btn btn-sm" type="submit" title="Filtrar" aria-label="Filtrar">
        <svg aria-hidden="true" viewBox="0 0 24 24" style="margin-right:6px"><path fill="#374151" d="M3 4h18v2H3m4 7h10v2H7m-2 7h14v2H5"/></svg>Filtrar
      </button></div>
      <div class="col-6 col-md-3 col-lg-1 d-grid"><button id="clearFiltersBtn" class="btn btn-sm" type="button" onclick="window.location='contacts.php'" title="Limpiar filtros" aria-label="Limpiar filtros">Limpiar filtros</button></div>
    </form>

    <form method="post" class="mb-2">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
      <input type="hidden" name="action" value="export_csv" />
      <button class="btn btn-sm" title="Exportar CSV" aria-label="Exportar CSV">
        <svg aria-hidden="true" viewBox="0 0 24 24" style="margin-right:6px"><path fill="#374151" d="M5 20h14v-2H5v2zm7-18l-5.5 5.5h4V15h3V7.5h4L12 2z"/></svg>Exportar CSV
      </button>
    </form>

    <!-- Acciones masivas -->
    <div class="mb-3 d-flex flex-wrap align-items-end gap-2">
      <div class="form-text">Acciones masivas para seleccionados:</div>
      <div class="d-flex gap-2">
        <select id="bulk_state" class="select-sm" aria-label="Asignar estado">
          <?php foreach (['Nuevo','En proceso','Pospuesto','Convertido','Cerrado'] as $st): ?>
          <option value="<?= e($st) ?>"><?= e($st) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm" type="button" onclick="bulkUpdate('change_state')" title="Asignar estado" aria-label="Asignar estado">
          <svg aria-hidden="true" viewBox="0 0 24 24" style="margin-right:6px"><path fill="#374151" d="M3 5h18v2H3V5m0 6h18v2H3v-2m0 6h18v2H3v-2z"/></svg>Asignar estado
        </button>
      </div>
      <div class="ms-auto">
        <button class="btn btn-sm" type="button" onclick="if(confirm('¿Desactivar seleccionados?')) bulkUpdate('delete')" title="Desactivar" aria-label="Desactivar">
          <svg aria-hidden="true" viewBox="0 0 24 24" style="margin-right:6px"><path fill="#374151" d="M6 7h12v2H6V7m2 3h8l-1 9H9l-1-9M9 4h6v2H9V4z"/></svg>Desactivar
        </button>
      </div>
    </div>

    <?php if (!$rows): ?>
      <?php if ($hasFilters): ?>
        <div class="alert alert-info">No hay resultados con los filtros aplicados. <a href="contacts.php" class="btn btn-sm" title="Limpiar filtros" aria-label="Limpiar filtros">Limpiar</a></div>
      <?php else: ?>
        <div class="alert alert-info">No hay registros todavía.</div>
      <?php endif; ?>
    <?php else: ?>
      <?php if ((int)($_GET['lvl3'] ?? 0) === 1): ?>
        <div class="alert alert-warning" role="alert">⚠️ Contactos en alerta nivel 3 (próximo contacto en menos de 12 horas)</div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th><input type="checkbox" id="chk_all" aria-label="Seleccionar todos" title="Seleccionar todos" /></th>
            <th>
              <?php $ordNext = ($sort==='id' && $order==='ASC')?'desc':'asc'; $cls = 'table-sort'; if($sort==='id'){ $cls .= ' ' . strtolower($order) . ' active'; } $title = ($sort==='id' ? ($order==='ASC'?'Ordenado ascendente (clic para descendente)':'Ordenado descendente (clic para ascendente)') : 'Ordenar ascendente'); ?>
              <a class="<?= $cls ?>" data-col="id" title="<?= e($title) ?>" aria-label="<?= e($title) ?>" href="?<?= http_build_query(array_merge($_GET,[ 'sort'=>'id','order'=> $ordNext ])) ?>">ID
                <svg class="icon-asc" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="icon-desc" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
              </a>
            </th>
            <th>
              <?php $ordNext = ($sort==='created_at' && $order==='ASC')?'desc':'asc'; $cls = 'table-sort'; if($sort==='created_at'){ $cls .= ' ' . strtolower($order) . ' active'; } $title = ($sort==='created_at' ? ($order==='ASC'?'Ordenado ascendente (clic para descendente)':'Ordenado descendente (clic para ascendente)') : 'Ordenar ascendente'); ?>
              <a class="<?= $cls ?>" data-col="created_at" title="<?= e($title) ?>" aria-label="<?= e($title) ?>" href="?<?= http_build_query(array_merge($_GET,[ 'sort'=>'created_at','order'=> $ordNext ])) ?>">Fecha ingreso
                <svg class="icon-asc" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="icon-desc" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
              </a>
            </th>
            <th>
              <?php $ordNext = ($sort==='full_name' && $order==='ASC')?'desc':'asc'; $cls = 'table-sort'; if($sort==='full_name'){ $cls .= ' ' . strtolower($order) . ' active'; } $title = ($sort==='full_name' ? ($order==='ASC'?'Ordenado ascendente (clic para descendente)':'Ordenado descendente (clic para ascendente)') : 'Ordenar ascendente'); ?>
              <a class="<?= $cls ?>" data-col="full_name" title="<?= e($title) ?>" aria-label="<?= e($title) ?>" href="?<?= http_build_query(array_merge($_GET,[ 'sort'=>'full_name','order'=> $ordNext ])) ?>">Nombre
                <svg class="icon-asc" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="icon-desc" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
              </a>
            </th>
            <th class="contacts-table-hide-sm">
              <?php $ordNext = ($sort==='age' && $order==='ASC')?'desc':'asc'; $cls = 'table-sort'; if($sort==='age'){ $cls .= ' ' . strtolower($order) . ' active'; } $title = ($sort==='age' ? ($order==='ASC'?'Ordenado ascendente (clic para descendente)':'Ordenado descendente (clic para ascendente)') : 'Ordenar ascendente'); ?>
              <a class="<?= $cls ?>" data-col="age" title="<?= e($title) ?>" aria-label="<?= e($title) ?>" href="?<?= http_build_query(array_merge($_GET,[ 'sort'=>'age','order'=> $ordNext ])) ?>">Edad
                <svg class="icon-asc" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="icon-desc" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
              </a>
            </th>
            <th>
              <?php $ordNext = ($sort==='whatsapp' && $order==='ASC')?'desc':'asc'; $cls = 'table-sort'; if($sort==='whatsapp'){ $cls .= ' ' . strtolower($order) . ' active'; } $title = ($sort==='whatsapp' ? ($order==='ASC'?'Ordenado ascendente (clic para descendente)':'Ordenado descendente (clic para ascendente)') : 'Ordenar ascendente'); ?>
              <a class="<?= $cls ?>" data-col="whatsapp" title="<?= e($title) ?>" aria-label="<?= e($title) ?>" href="?<?= http_build_query(array_merge($_GET,[ 'sort'=>'whatsapp','order'=> $ordNext ])) ?>">WhatsApp
                <svg class="icon-asc" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="icon-desc" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
              </a>
            </th>
            <th class="contacts-table-hide-md">
              <?php $ordNext = ($sort==='isapre' && $order==='ASC')?'desc':'asc'; $cls = 'table-sort'; if($sort==='isapre'){ $cls .= ' ' . strtolower($order) . ' active'; } $title = ($sort==='isapre' ? ($order==='ASC'?'Ordenado ascendente (clic para descendente)':'Ordenado descendente (clic para ascendente)') : 'Ordenar ascendente'); ?>
              <a class="<?= $cls ?>" data-col="isapre" title="<?= e($title) ?>" aria-label="<?= e($title) ?>" href="?<?= http_build_query(array_merge($_GET,[ 'sort'=>'isapre','order'=> $ordNext ])) ?>">Isapre
                <svg class="icon-asc" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="icon-desc" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
              </a>
            </th>
            <th>Estado</th>
            <th class="contacts-table-hide-md">
              <?php $ordNext = ($sort==='deadline_at' && $order==='ASC')?'desc':'asc'; $cls = 'table-sort'; if($sort==='deadline_at'){ $cls .= ' ' . strtolower($order) . ' active'; } $title = ($sort==='deadline_at' ? ($order==='ASC'?'Ordenado ascendente (clic para descendente)':'Ordenado descendente (clic para ascendente)') : 'Ordenar ascendente'); ?>
              <a class="<?= $cls ?>" data-col="deadline_at" title="<?= e($title) ?>" aria-label="<?= e($title) ?>" href="?<?= http_build_query(array_merge($_GET,[ 'sort'=>'deadline_at','order'=> $ordNext ])) ?>">Fecha próximo contacto (fecha)
                <svg class="icon-asc" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="icon-desc" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
              </a>
            </th>
            <th>
              <?php $ordNext = ($sort==='next_contact' && $order==='ASC')?'desc':'asc'; $cls = 'table-sort'; if($sort==='next_contact'){ $cls .= ' ' . strtolower($order) . ' active'; } $title = ($sort==='next_contact' ? ($order==='ASC'?'Ordenado por próximo contacto (ascendente)':'Ordenado por próximo contacto (descendente)') : 'Ordenar ascendente'); ?>
              <a class="<?= $cls ?>" data-col="next_contact" title="<?= e($title) ?>" aria-label="<?= e($title) ?>" href="?<?= http_build_query(array_merge($_GET,[ 'sort'=>'next_contact','order'=> $ordNext ])) ?>">Fecha próximo contacto
                <svg class="icon-asc" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="icon-desc" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
              </a>
            </th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $deadlineBadge = '';
            $deadlineIcon = '';
            if (!empty($r['deadline_at'])) {
              $dl = strtotime($r['deadline_at']);
              if ($dl !== false) {
                $diff = $dl - time();
                if ($dl < time()) { $deadlineBadge = '<span class="badge" style="background:' . e($rem['c3']) . '">Vencido</span>'; $deadlineIcon = '<i class="fa-solid fa-triangle-exclamation" style="color:' . e($rem['c3']) . '" title="Plazo vencido" aria-label="Plazo vencido"></i>'; }
                else if ($diff <= $thr3) { $deadlineBadge = '<span class="badge" style="background:' . e($rem['c3']) . '">3</span>'; }
                else if ($diff <= $thr2) { $deadlineBadge = '<span class="badge" style="background:' . e($rem['c2']) . '">2</span>'; }
                else if ($diff <= $thr1) { $deadlineBadge = '<span class="badge" style="background:' . e($rem['c1']) . ';color:#111">1</span>'; }
              }
            }
            // Map isapre legible
            $isapreMap = [
              '1'=>'Colmena Golden Cross','3'=>'Consalud','4'=>'Banmédica','5'=>'Cruz Blanca','6'=>'Nueva Masvida','7'=>'VidaTres','8'=>'Fonasa','9'=>'Esencial','0'=>'Ninguna'
            ];
            $isapreName = isset($isapreMap[(string)$r['isapre']]) ? $isapreMap[(string)$r['isapre']] : (string)$r['isapre'];
            // Teléfono limpio para wa.me (sin + ni espacios)
            $phoneDigits = preg_replace('/\D+/','', (string)$r['phone'] ?? '');
            $phoneForWa = '56' . ltrim($phoneDigits, '0');
          ?>
          <?php $isLevel3 = isset($r['horas_restantes']) && (int)$r['horas_restantes'] >= 0 && (int)$r['horas_restantes'] <= 12; ?>
          <tr class='<?= $isLevel3 ? "row-level3" : "" ?>' data-id='<?= (int)$r['id'] ?>' data-interactions='<?= e(json_encode($interactionsById[(int)$r['id']] ?? [], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>'>
            <td><input type="checkbox" class="rowchk" value="<?= (int)$r['id'] ?>" aria-label="Seleccionar contacto <?= (int)$r['id'] ?>" title="Seleccionar" /></td>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e((string)$r['created_at']) ?></td>
            <td><?= e((string)$r['full_name']) ?></td>
            <td class="contacts-table-hide-sm"><?= e((string)$r['age']) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php
                  $waNum = (string)($r['whatsapp_confirmed'] ?? '');
                  $waDigits = preg_replace('/\D+/', '', $waNum);
                  if ($waNum === '' && !empty($r['phone'])) { $waDigits = preg_replace('/\D+/', '', (string)$r['phone']); if ($waDigits) { $waNum = '+56' . ltrim($waDigits,'0'); } }
                  $waLink  = $waDigits ? ('https://wa.me/' . (strpos($waNum,'+')===0?substr($waNum,1):$waNum) . '?text=' . rawurlencode('Hola ' . (string)$r['full_name'] . ', te contactamos de Tu Plan Seguro')) : '';
                ?>
                <?php if ($waLink): ?>
                  <a class="text-decoration-none" href="<?= e($waLink) ?>" target="_blank" rel="noopener" aria-label="Contactar por WhatsApp" title="Contactar por WhatsApp">
                    <?= e($waNum ?: '—') ?>
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle;margin-left:6px">
                      <path fill="#25D366" d="M12 2a10 10 0 0 0-8.94 14.56L2 22l5.6-1.47A10 10 0 1 0 12 2Z"/>
                      <path fill="#fff" d="M16.71 14.27c-.27.77-1.34 1.41-2.03 1.5-.54.07-1.24.1-2.01-.13-3.53-1.08-5.83-4.08-6.01-4.28-.18-.2-1.43-1.9-1.43-3.62 0-1.72.9-2.56 1.22-2.91.31-.34.68-.43.91-.43.22 0 .45 0 .65.01.2.01.49-.08.77.59.27.67.92 2.3 1 2.47.08.17.13.37.02.6-.1.22-.15.36-.3.55-.15.18-.32.41-.46.55-.15.15-.3.32-.13.62.18.3.8 1.32 1.72 2.14 1.18 1.04 2.18 1.36 2.49 1.51.31.15.49.13.67-.08.18-.21.77-.9.98-1.21.2-.31.41-.26.68-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.34.07.12.07.71-.2 1.48Z"/>
                    </svg>
                  </a>
                <?php else: ?>
                  <?= e($waNum ?: '—') ?>
                <?php endif; ?>
              </div>
          </td>
            <td class="contacts-table-hide-md"><?= e($isapreName) ?></td>
            <td>
              <?php
                $statusText = (string)($r['status'] ?? '');
                $statusClass = 'badge--status-closed';
                switch ($statusText) {
                  case 'Nuevo': $statusClass = 'badge--status-new'; break;
                  case 'En proceso': $statusClass = 'badge--status-progress'; break;
                  case 'Pospuesto': $statusClass = 'badge--status-hold'; break;
                  case 'Convertido': $statusClass = 'badge--status-won'; break;
                  case 'Cerrado': default: $statusClass = 'badge--status-closed'; break;
                }
              ?>
              <span class="badge <?= e($statusClass) ?>" title="<?= e($statusText) ?>" aria-label="<?= e($statusText) ?>"><?= e($statusText ?: '—') ?></span>
            </td>
            <td class="contacts-table-hide-md">
              <?= e((string)($r['deadline_efectivo'] ?? '')) ?: '—' ?>
            </td>
            <td class="cell-next-contact" data-next-contact="<?= e(str_replace(' ','T', (string)($r['deadline_efectivo'] ?? ''))) ?>" title="Fecha próximo contacto: <?= e((string)($r['deadline_efectivo'] ?? '')) ?>">
              <?php
                $badge = '<span class="badge badge--muted">—</span>';
                if (!empty($r['deadline_efectivo'])) {
                  $d = isset($r['dias_restantes']) ? (int)$r['dias_restantes'] : null;
                  $h = isset($r['horas_restantes']) ? (int)$r['horas_restantes'] : null;
                  if ($h !== null && $h < 0) {
                    $badge = '<span class="badge badge--overdue">Vencido</span>';
                  } else {
                    $txt = ($d !== null && $d >= 1) ? ($d . 'd') : (($h !== null ? $h : 0) . 'h');
                    // Mapear a r1/r2/r3
                    $seconds = ($h !== null ? $h*3600 : ($d !== null ? $d*86400 : 0));
                    if ($seconds <= $thr3)            $badge = '<span class="badge badge--danger">' . $txt . '</span>';
                    else if ($seconds <= $thr2)       $badge = '<span class="badge badge--alert">' . $txt . '</span>';
                    else if ($seconds <= $thr1)       $badge = '<span class="badge badge--warn">' . $txt . '</span>';
                    else                               $badge = '<span class="badge badge--muted">' . $txt . '</span>';
                  }
                }
                echo $badge;
              ?>
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-sm--icon" title="Contactar" aria-label="Contactar" onclick='openContact(<?= json_encode([
                'id'=>(int)$r['id'],
                'created_at'=>(string)$r['created_at'],
                'name'=>(string)$r['full_name'],
                'rut'=>(string)$r['rut'],
                'age'=>(string)$r['age'],
                'phone'=>(string)$r['phone'],
                'email'=>(string)$r['email'],
                'commune'=>(string)$r['commune'],
                'isapre'=>(string)$r['isapre'],
                'income'=>isset($r['income'])?(string)$r['income']:'',
                'charges'=>isset($r['charges'])?(string)$r['charges']:'',
                'comments'=>isset($r['comments'])?(string)$r['comments']:'',
                'deadline_at'=>isset($r['deadline_at'])?(string)$r['deadline_at']:'',
                'status'=>(string)$r['status'],
              ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>, this.closest("tr")); return false;'>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path fill="#374151" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.05-.24 11.36 11.36 0 0 0 3.56.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 7a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.36 11.36 0 0 0 .57 3.56 1 1 0 0 1-.24 1.05Z"/></svg>
              </button>
              <?php $returnQuery = http_build_query($_GET); ?>
              <a class="btn btn-sm btn-sm--icon ms-1" href="view.php?id=<?= (int)$r['id'] ?>&return=<?= urlencode((string)$returnQuery) ?>" title="Ver" aria-label="Ver">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 6.5C7 6.5 2.7 10 1 12c1.7 2 6 5.5 11 5.5S21.3 14 23 12c-1.7-2-6-5.5-11-5.5m0 9A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5Z" fill="#374151"/></svg>
              </a>
              <button type="button" class="btn btn-sm btn-sm--icon ms-1" onclick="confirmDelete(<?= (int)$r['id'] ?>, this)" title="Eliminar" aria-label="Eliminar">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path fill="#ef4444" d="M6 7h12v2H6V7m2 3h8l-1 9H9l-1-9M9 4h6v2H9V4z"/></svg>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <nav>
      <ul class="pagination">
        <?php for ($i=1; $i<=$pages; $i++): ?>
          <li class="page-item <?= $i===$page ? 'active' : '' ?>"><a class="page-link" href="?q=<?= urlencode($q) ?>&isapre=<?= urlencode($isapre) ?>&status=<?= urlencode($status) ?>&deadline_date=<?= urlencode($deadlineDateEq) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&overdue=<?= $overdue?1:0 ?>&upcoming=<?= $upcoming?1:0 ?>&page=<?= $i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
      </ul>
    </nav>

    <!-- Modal Contactar -->
    <div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Registrar contacto <small id="contactHeaderInfo" class="text-muted ms-2"></small><span id="contactStatusBadge" class="badge rounded-pill bg-secondary ms-2" style="font-size:.9rem;padding:.45em .8em;vertical-align:middle">—</span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post" id="contactForm">
            <div class="modal-body">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="log_contact" />
              <input type="hidden" name="id" id="c_id" />
              <div class="mb-2">
                <label class="form-label">Tipo de contacto</label>
                <select name="contact_type" id="c_type" class="form-select">
                  <option>Teléfono 📞</option>
                  <option>Correo electrónico 📧</option>
                  <option>WhatsApp 💬</option>
                  <option>En persona 🤝</option>
                  <option>Otro</option>
                </select>
                <input type="text" name="contact_type_detail" id="c_type_detail" class="form-control mt-2" placeholder="Especifica el detalle (opcional, obligatorio en 'Otro')" />
              </div>
              <div class="mb-2">
                <label class="form-label">Nota</label>
                <textarea name="note" id="c_note" class="form-control" rows="3" placeholder="Detalle de lo conversado"></textarea>
              </div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="c_define_deadline">
                <label class="form-check-label" for="c_define_deadline">Definir próxima fecha de contacto</label>
              </div>
              <div class="mb-2" id="c_deadline_wrap" style="display:none">
                <label class="form-label">Próximo contacto (fecha/hora)</label>
                <input type="text" name="deadline_at" id="c_deadline" class="form-control" placeholder="Selecciona fecha y hora" />
              </div>
              <input type="hidden" name="deadline_defined" id="c_deadline_defined" value="0" />
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="c_do_change" name="do_change" value="1">
                <label class="form-check-label" for="c_do_change">Cambiar estado</label>
              </div>
              <div id="c_change_wrap" class="mb-2" style="display:none">
                <label class="form-label">Nuevo estado</label>
                <select name="new_status" id="c_status" class="form-select">
                  <?php foreach (['Nuevo','En proceso','Pospuesto','Convertido','Cerrado'] as $st): ?>
                  <option value="<?= e($st) ?>"><?= e($st) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div id="history" class="list-group"></div>
            </div>
            <div class="modal-footer">
              <?php $returnQuery = http_build_query($_GET); ?>
              <a id="contactViewLink" href="#" class="btn btn-sm btn-outline-secondary me-auto" target="_blank" rel="noopener" title="Ver detalle" aria-label="Ver detalle" data-return="<?= e((string)$returnQuery) ?>">Ver detalle</a>
              <button type="button" class="btn btn-sm" data-bs-dismiss="modal" title="Cancelar" aria-label="Cancelar">Cancelar</button>
              <button type="submit" class="btn btn-sm" title="Guardar" aria-label="Guardar">Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    // Selección masiva
    const chkAll = document.getElementById('chk_all');
    chkAll?.addEventListener('change', function(){
      document.querySelectorAll('.rowchk').forEach(c=>{ c.checked = chkAll.checked; });
    });

    async function bulkUpdate(kind){
      const ids = Array.from(document.querySelectorAll('.rowchk:checked')).map(c=>c.value);
      if (!ids.length){ Swal?.fire({icon:'info', title:'Selecciona registros'}); return; }
      const form = new FormData();
      form.append('csrf','<?= e(csrf_token()) ?>');
      form.append('action','bulk_action');
      form.append('bulk', kind);
      ids.forEach(id=>form.append('ids[]', id));
      if (kind==='update_next_contact'){
        const nc = document.getElementById('bulk_next').value;
        form.append('next_contact', nc);
      }
      if (kind==='change_state'){
        const st = document.getElementById('bulk_state').value;
        form.append('state', st);
      }
      try{
        const r = await fetch('contacts.php', { method:'POST', body: form, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'} });
        const json = await r.json();
        if (json.ok){ Swal?.fire({icon:'success', title:'Hecho', text: json.message||'Actualizado'}).then(()=>location.reload()); }
        else { Swal?.fire({icon:'error', title:'Error', text: json.message||'No fue posible completar la acción'}); }
      }catch(err){ Swal?.fire({icon:'error', title:'Error', text:'No fue posible completar la acción'}); }
    }
    const contactModal = new bootstrap.Modal(document.getElementById('contactModal'));
    const fp = flatpickr('#c_deadline', {enableTime:true, dateFormat:'Y-m-d H:i', time_24hr:true});
    const defCb = document.getElementById('c_define_deadline');
    const deadlineInput = document.getElementById('c_deadline');
    const deadlineDefined = document.getElementById('c_deadline_defined');
    function toggleDeadline(){
      const on = !!defCb?.checked;
      const wrap = document.getElementById('c_deadline_wrap');
      if (wrap) wrap.style.display = on ? '' : 'none';
      if (deadlineInput) deadlineInput.disabled = !on;
      if (!on){ try{ fp.clear(); }catch(e){} if (deadlineInput) deadlineInput.value=''; }
      if (deadlineDefined) deadlineDefined.value = on ? '1' : '0';
    }
    defCb?.addEventListener('change', toggleDeadline);
    document.getElementById('c_do_change')?.addEventListener('change', function(){
      document.getElementById('c_change_wrap').style.display = this.checked ? '' : 'none';
    });
    document.getElementById('c_type')?.addEventListener('change', function(){
      const detail = document.getElementById('c_type_detail');
      if (this.value.indexOf('Otro')!==-1){
        detail.required = true; detail.placeholder = "Especifica el detalle (obligatorio)";
      } else {
        detail.required = false; detail.placeholder = "Especifica el detalle (opcional)";
      }
    });

    function openContact(data, row){
      document.getElementById('c_id').value = data.id;
      document.getElementById('c_status').value = data.status || 'Nuevo';
      document.getElementById('c_note').value = '';
      // Inicializa switch de deadline según datos
      if (defCb){ defCb.checked = !!(data.deadline_at && String(data.deadline_at).trim() !== ''); }
      toggleDeadline();
      if (defCb?.checked){ fp.setDate(data.deadline_at || null, true, 'Y-m-d H:i'); } else { try{ fp.clear(); }catch(e){} }
      document.getElementById('c_do_change').checked = false;
      document.getElementById('c_change_wrap').style.display = 'none';
      // Encabezado con id - nombre, badge de estado y link Ver detalle
      try{
        const head = document.getElementById('contactHeaderInfo');
        if (head){
          const nm = (data.name||'').toString();
          head.textContent = `#${data.id} - ${nm}`;
        }
        const vlink = document.getElementById('contactViewLink');
        if (vlink){
          const ret = vlink.getAttribute('data-return') || '';
          const href = 'view.php?id=' + encodeURIComponent(data.id) + (ret? ('&return=' + encodeURIComponent(ret)) : '');
          vlink.href = href;
        }
        const badge = document.getElementById('contactStatusBadge');
        if (badge){
          const st = (data.status||'Nuevo').toString();
          let cls = 'bg-dark';
          switch(st){
            case 'Nuevo': cls = 'bg-primary'; break;
            case 'En proceso': cls = 'bg-info text-dark'; break;
            case 'Pospuesto': cls = 'bg-warning text-dark'; break;
            case 'Convertido': cls = 'bg-success'; break;
            case 'Cerrado': default: cls = 'bg-dark'; break;
          }
          badge.className = 'badge rounded-pill ' + cls + ' ms-2';
          badge.textContent = st || '—';
        }
      }catch(e){}
      contactModal.show();
    }
    document.getElementById('contactForm')?.addEventListener('submit', function(ev){
      ev.preventDefault();
      const doChange = document.getElementById('c_do_change').checked;
      const st = document.getElementById('c_status').value;
      const note = document.getElementById('c_note');
      const type = document.getElementById('c_type').value;
      const detail = document.getElementById('c_type_detail');
      // Validación discreta
      let ok=true;
      note.classList.remove('is-invalid'); detail.classList.remove('is-invalid');
      if (type.indexOf('Otro')!==-1 && !detail.value.trim()) { detail.classList.add('is-invalid'); ok=false; }
      if (!ok) return;
      const fd = new FormData(this);
      fetch('contacts.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'} })
        .then(r=>r.json()).then(json=>{
          if (json.ok){
            const row = document.querySelector(`tr[data-id='${document.getElementById('c_id').value}']`);
            if (row && json.status){ row.querySelector('td:nth-child(7)')?.classList.remove('is-invalid'); }
            contactModal.hide();
            // Refrescar parcialmente próximo contacto
            if (row){
              const td = row.querySelector('.cell-next-contact');
              if (td){
                if (json.deadline_at){
                  td.setAttribute('data-next-contact', json.deadline_at.replace(' ','T'));
                  td.title = 'Próximo contacto: ' + json.deadline_at;
                } else {
                  td.removeAttribute('data-next-contact');
                  td.title = 'Próximo contacto: —';
                  td.innerHTML = '<span class="badge badge--muted">—</span>';
                }
              }
            }
            // Feedback suave
            const al = document.createElement('div'); al.className='alert alert-success'; al.textContent = json.message||'Guardado';
            document.querySelector('main.container')?.prepend(al); setTimeout(()=>al.remove(), 2500);
            // repaint next-contact
            try { if (window.paintNextContactCells) window.paintNextContactCells(); } catch(e){}
          }
        }).catch(()=>{
          const al = document.createElement('div'); al.className='alert alert-danger'; al.textContent='Error al guardar';
          document.querySelector('main.container')?.prepend(al); setTimeout(()=>al.remove(), 2500);
        });
    });
    // Pintado relativo de Próximo contacto
    (function(){
      const cfg = { warnDays: <?= (int)$rem['r1v'] ?>, alertDays: <?= (int)$rem['r2v'] ?>, dangerHours: <?= (int)$rem['r3v'] ?> };
      function fmtDiff(iso){
        if (!iso) return {text:'—', cls:''};
        const now = new Date(); const target = new Date(iso);
        if (isNaN(target)) return {text:'—', cls:''};
        const ms = target - now; if (ms <= 0) return {text:'Vencido', cls:'badge--overdue'};
        const hours = Math.floor(ms/36e5); const days = Math.floor(hours/24);
        if (hours < 24) return {text:`Faltan ${hours} h`, cls:'badge--danger'};
        if (days < cfg.alertDays) return {text:`Faltan ${days} días`, cls:'badge--danger'};
        if (days < cfg.warnDays) return {text:`Faltan ${days} días`, cls:'badge--alert'};
        return {text:`Faltan ${days} días`, cls:'badge--warn'};
      }
      window.paintNextContactCells = function(){
        document.querySelectorAll('.cell-next-contact').forEach(td=>{
          const iso = td.getAttribute('data-next-contact');
          const {text, cls} = fmtDiff(iso);
          if (!iso){ td.textContent='—'; return; }
          td.innerHTML = `<span class="badge ${cls}">${text}</span>`;
        });
      };
      window.paintNextContactCells();
      setInterval(window.paintNextContactCells, 60000);
    })();

    // Persistencia de filtros en localStorage
    (function(){
      const form = document.getElementById('filtersForm');
      if (!form) return;
      const key = 'contactsFilters';
      // Restaurar si no hay query en URL
      try{
        const hasQuery = window.location.search.length > 1;
        if (!hasQuery){
          const raw = localStorage.getItem(key);
          if (raw){
            const data = JSON.parse(raw);
            ['q','isapre','status','deadline_date','from','to'].forEach(n=>{
              if (data[n] !== undefined && form.elements[n]) form.elements[n].value = data[n];
            });
          }
        }
      }catch(e){}
      form.addEventListener('submit', function(){
        try{
          const data = {};
          ['q','isapre','status','deadline_date','from','to'].forEach(n=>{ data[n] = form.elements[n]?.value || ''; });
          localStorage.setItem(key, JSON.stringify(data));
        }catch(e){}
      });
      document.getElementById('clearFiltersBtn')?.addEventListener('click', function(){
        try{ localStorage.removeItem(key); }catch(e){}
      });
    })();

    <?php if ($notice): ?>
      Swal.fire({icon:'success', title:'Hecho', text:'<?= e($notice) ?>'});
    <?php endif; ?>

    // Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) })

    async function confirmDelete(id, btn){
      const res = await Swal.fire({
        icon:'warning',
        title:'¿Desactivar contacto?',
        text:'Esta acción desactivará el registro y su historial asociado. Puedes ocultarlos del listado.',
        showCancelButton:true,
        confirmButtonText:'Sí, desactivar',
        cancelButtonText:'Cancelar',
        confirmButtonColor:'#dc3545'
      });
      if (!res.isConfirmed) return;
      try{
        const form = new FormData();
        form.append('id', String(id));
        form.append('csrf', '<?= e(csrf_token()) ?>');
        const r = await fetch('delete_contact.php', { method:'POST', body: form, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'} });
        const json = await r.json();
        if (json.ok){
          Swal.fire({icon:'success', title:'Desactivado', text:'✅ Contacto desactivado con éxito'});
          const row = btn.closest('tr');
          if (row){ row.classList.add('table-warning'); row.style.opacity = '0.6'; }
        } else {
          Swal.fire({icon:'error', title:'Error', text: json.message || 'Error al eliminar'});
        }
      } catch(err){
        Swal.fire({icon:'error', title:'Error', text:'No fue posible eliminar el contacto.'});
      }
    }
  </script>
</body>
</html>


