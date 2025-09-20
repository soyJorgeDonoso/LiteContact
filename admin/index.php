<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$counts = ['content'=>0,'contacts'=>0,'today'=>0,'week'=>0,'month'=>0,'upcoming'=>0,'overdue'=>0];
$funnel = ['Nuevo'=>0,'En proceso'=>0,'Pospuesto'=>0,'Convertido'=>0,'Cerrado'=>0];
$dailyLabels = [];
$dailyValues = [];
$calendarMap = [];
try {
  $counts['content'] = (int)$pdo->query('SELECT COUNT(*) FROM site_content')->fetchColumn();
  $tbl = contacts_table($pdo);
  $counts['contacts'] = (int)$pdo->query('SELECT COUNT(*) FROM ' . $tbl['name'] . ' WHERE is_active=1')->fetchColumn();
  // hoy/semana/mes
  $counts['today'] = (int)$pdo->query("SELECT COUNT(*) FROM contact_status WHERE DATE(created_at)=CURDATE() AND is_active=1")->fetchColumn();
  $counts['week'] = (int)$pdo->query("SELECT COUNT(*) FROM contact_status WHERE YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1) AND is_active=1")->fetchColumn();
  $counts['month'] = (int)$pdo->query("SELECT COUNT(*) FROM contact_status WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) AND is_active=1")->fetchColumn();
  // próximos contactos y vencidos
  $counts['upcoming'] = (int)$pdo->query("SELECT COUNT(*) FROM contact_status WHERE is_active=1 AND deadline_at IS NOT NULL AND deadline_at > '1000-01-01 00:00:00' AND TIMESTAMPDIFF(SECOND,NOW(),deadline_at) BETWEEN 0 AND 432000")->fetchColumn();
  $counts['overdue'] = (int)$pdo->query("SELECT COUNT(*) FROM contact_status WHERE is_active=1 AND deadline_at IS NOT NULL AND deadline_at > '1000-01-01 00:00:00' AND TIMESTAMPDIFF(SECOND,NOW(),deadline_at) < 0")->fetchColumn();
  // embudo por estado
  $st = $pdo->query("SELECT status, COUNT(*) c FROM contact_status WHERE is_active=1 GROUP BY status");
  while($r=$st->fetch()){ $k=(string)$r['status']; if(isset($funnel[$k])) $funnel[$k]=(int)$r['c']; }
  // últimas 14 fechas (contactos por día)
  $days = [];
  for ($i=13;$i>=0;$i--) { $d = (new DateTime("-$i day"))->format('Y-m-d'); $days[$d] = 0; }
  $st2 = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM contact_status WHERE is_active=1 AND created_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(created_at)");
  while($r=$st2->fetch()){ $d=(string)$r['d']; $days[$d] = (int)$r['c']; }
  foreach($days as $d=>$c){ $dailyLabels[] = $d; $dailyValues[] = $c; }
  // calendario próximos 30 días (deadlines)
  $st3 = $pdo->query("SELECT DATE(deadline_at) d, COUNT(*) c FROM contact_status WHERE is_active=1 AND deadline_at IS NOT NULL AND deadline_at > '1000-01-01 00:00:00' AND deadline_at BETWEEN CURDATE() AND (CURDATE()+ INTERVAL 30 DAY) GROUP BY DATE(deadline_at)");
  while($r=$st3->fetch()){ $calendarMap[(string)$r['d']] = (int)$r['c']; }
} catch (Throwable $e) { log_error('admin dashboard counts: ' . $e->getMessage()); }
$today = date('Y-m-d');
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <?php $adminCss = (file_exists(__DIR__ . '/../assets/css/admin.min.css') ? '../assets/css/admin.min.css' : '../assets/css/admin.css'); $adminCssVer = @filemtime(__DIR__ . '/../' . basename($adminCss)) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminCssVer ?>" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
</head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>

  <main class="container">
    <nav class="breadcrumb" aria-label="breadcrumb">
      <a href="index.php">Dashboard</a>
      <span>/</span>
      <span>Inicio</span>
    </nav>
    <h1>Dashboard</h1>
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3">
        <a class="card p-3 text-center text-decoration-none d-block" href="contacts.php?from=<?= e($today) ?>&to=<?= e($today) ?>" title="Ver contactos de hoy" aria-label="Ver contactos de hoy">
          <div class="num"><?= (int)$counts['today'] ?></div><div class="label">Hoy</div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a class="card p-3 text-center text-decoration-none d-block" href="contacts.php?from=<?= e($monday) ?>&to=<?= e($sunday) ?>" title="Ver contactos de esta semana" aria-label="Ver contactos de esta semana">
          <div class="num"><?= (int)$counts['week'] ?></div><div class="label">Semana</div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a class="card p-3 text-center text-decoration-none d-block" href="contacts.php?from=<?= e($monthStart) ?>&to=<?= e($monthEnd) ?>" title="Ver contactos de este mes" aria-label="Ver contactos de este mes">
          <div class="num"><?= (int)$counts['month'] ?></div><div class="label">Mes</div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a class="card p-3 text-center text-decoration-none d-block" href="contacts.php" title="Ver todos los contactos" aria-label="Ver todos los contactos">
          <div class="num"><?= (int)$counts['contacts'] ?></div><div class="label">Total</div>
        </a>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-md-7">
        <div class="card p-3">
          <h5 class="mb-3">Embudo por estado</h5>
          <canvas id="funnelChart" height="140"></canvas>
        </div>
      </div>
      <div class="col-md-5">
        <div class="card p-3">
          <h5 class="mb-2">Fechas importantes</h5>
          <div class="d-flex gap-2 flex-wrap">
            <a class="badge bg-danger text-decoration-none" href="contacts.php?overdue=1" title="Ver vencidos" aria-label="Ver vencidos">Vencidos: <?= (int)$counts['overdue'] ?></a>
            <a class="badge bg-warning text-dark text-decoration-none" href="contacts.php?upcoming=1" title="Ver próximos 5 días" aria-label="Ver próximos 5 días">Próx. 5 días: <?= (int)$counts['upcoming'] ?></a>
          </div>
          <a class="btn btn-sm btn-outline-secondary mt-2" href="contacts.php?lvl3=1">Ver próximos contactos</a>
          <div class="mt-3">
            <input id="calendarInline" type="text" />
          </div>
        </div>
      </div>
    </div>
    <div class="row g-3 mt-1">
      <div class="col-12">
        <div class="card p-3">
          <h5 class="mb-3">Ingresos últimos 14 días</h5>
          <canvas id="dailyChart" height="110"></canvas>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="../assets/js/admin.js"></script>
  <script>
    (function(){
      try{
        const ctxF = document.getElementById('funnelChart');
        if (ctxF && window.Chart){
          new Chart(ctxF, {
            type: 'doughnut',
            data: {
              labels: ['Nuevo','En proceso','Pospuesto','Convertido','Cerrado'],
              datasets: [{
                data: [<?= (int)$funnel['Nuevo'] ?>, <?= (int)$funnel['En proceso'] ?>, <?= (int)$funnel['Pospuesto'] ?>, <?= (int)$funnel['Convertido'] ?>, <?= (int)$funnel['Cerrado'] ?>],
                backgroundColor: ['#0d6efd','#0dcaf0','#ffc107','#198754','#212529']
              }]
            },
            options: { plugins: { legend: { position:'bottom' } }, cutout:'55%'}
          });
        }
        const ctxD = document.getElementById('dailyChart');
        if (ctxD && window.Chart){
          new Chart(ctxD, {
            type: 'bar',
            data: {
              labels: <?= json_encode($dailyLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
              datasets: [{ label: 'Ingresos', data: <?= json_encode($dailyValues) ?>, backgroundColor: 'rgba(90,61,240,.6)' }]
            },
            options: { scales: { y: { beginAtZero:true } }, plugins:{ legend:{ display:false } } }
          });
        }
        // Calendario inline con marcas
        const calEl = document.getElementById('calendarInline');
        if (calEl && window.flatpickr){
          const map = <?= json_encode($calendarMap) ?>;
          const savedDate = (function(){ try { const v = localStorage.getItem('dashboardDeadlineFilter'); return (/^\d{4}-\d{2}-\d{2}$/).test(v||'') ? v : null; } catch(e){ return null; } })();
          flatpickr(calEl, {
            inline: true,
            defaultDate: savedDate || new Date(),
            onChange: function(selectedDates, dateStr){
              try{ localStorage.setItem('dashboardDeadlineFilter', dateStr); }catch(e){}
              if (dateStr){ window.location.href = 'contacts.php?deadline_date=' + encodeURIComponent(dateStr); }
            },
            onDayCreate: function(dObj, dStr, fp, dayElem){
              const d = dayElem.dateObj;
              const y = d.getFullYear(); const m = String(d.getMonth()+1).padStart(2,'0'); const dd = String(d.getDate()).padStart(2,'0');
              const key = y+'-'+m+'-'+dd;
              if (map[key]){
                const dot = document.createElement('span');
                dot.style.cssText='display:block;width:6px;height:6px;border-radius:999px;background:#dc3545;position:absolute;left:50%;transform:translateX(-50%);bottom:4px';
                dayElem.style.position='relative'; dayElem.appendChild(dot);
                dayElem.title = 'Próximos contactos: ' + map[key];
              }
            }
          });
        }
      }catch(e){}
    })();
  </script>
  </body>
  </html>


