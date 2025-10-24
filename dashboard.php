<?php
// dashboard.php
session_start(); // <-- [FIX] เพิ่ม session_start()

// Simple Sales Dashboard (Chart.js + Bootstrap) using mysqli (no PDO)
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$DB_HOST = 'localhost';
$DB_USER = 's67160385';
$DB_PASS = 't9D8GWv6';
$DB_NAME = 's67160385';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) { return []; }
  $rows = [];
  while ($row = $res->fetch_assoc()) { $rows[] = $row; }
  $res->free();
  return $rows;
}

// เตรียมข้อมูลสำหรับกราฟต่าง ๆ
$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
$kpis = fetch_all($mysqli, "
  SELECT
    (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
    (SELECT SUM(quantity)   FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
    (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

// Helper for number format
function nf($n) { return number_format((float)$n, 2); }
function n($n) { return number_format((int)$n); } // Helper for integer
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail DW Dashboard</title>
  
  <!-- [STYLE] ใช้ Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- [STYLE] ฟอนต์ 'Kanit' (เหมือนเดิมตามที่ขอ) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;700&display=swap" rel="stylesheet">

  <!-- [STYLE] เพิ่ม Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  
  <style>
    /* [STYLE][EDIT] ปรับปรุงธีมเป็น Pastel Pink/Blue */
    body { 
      /* [EDIT] เปลี่ยนพื้นหลังเป็นสีฟ้าอ่อน جدا (Blue-50) */
      background: rgb(239 246 255); 
      color: #334155;     /* Slate 700 (ตัวหนังสือหลัก) */
      font-family: 'Kanit', sans-serif; /* [EDIT] ใช้ฟอนต์ Kanit */
    }
    .card { 
      background: #ffffff; /* White */
      border: 1px solid #e2e8f0; /* Slate 200 Border */
      border-radius: 0.75rem; /* .card-lg */
      height: 100%; 
      /* [EDIT] เพิ่มเงา (shadow-md) เพื่อไม่ให้เรียบเกินไป */
      box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    }
    .card-title { 
      color: #475569; /* Slate 600 */
      font-weight: 500;
    }
    .kpi { 
      font-size: 1.5rem; 
      font-weight: 500; 
      color: #1e293b; /* Slate 800 */
    }
    .kpi-icon {
      font-size: 1.75rem;
      /* [EDIT] เปลี่ยนสีไอคอนเป็นสีฟ้าที่เข้ากับธีม */
      color: #60a5fa; /* Blue 400 */
      opacity: 0.8;
    }
    .navbar-light .navbar-brand {
      color: #1e293b; /* Slate 800 */
      font-weight: 500;
    }
    .navbar {
      border-bottom: 1px solid #e2e8f0; /* Slate 200 Border */
      /* [EDIT] เพิ่มเงาให้ Navbar ด้วย */
      box-shadow: 0 2px 4px rgb(0 0 0 / 0.05);
    }
    canvas { 
      max-height: 350px; 
      width: 100%;
    }
  </style>
</head>
<body class="p-3 p-md-4">
    
  <!-- [LAYOUT] ปรับปรุง Navbar เป็น Light Theme -->
  <nav class="navbar navbar-expand-lg navbar-light mb-4" style="background-color: #ffffff;">
    <div class="container-fluid">
      <span class="navbar-brand">MyApp Dashboard</span>
      <div class="d-flex align-items-center gap-3">
        <span class="navbar-text small">
          Hi, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?>
        </span>
        <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="mb-0 h4" style="font-weight: 700;">ภาพรวมยอดขาย (Retail DW)</h2>
      <span class="text-muted small">แหล่งข้อมูล: MySQL (mysqli)</span>
    </div>

    <!-- [LAYOUT] ใช้ Bootstrap Grid (row/col) (เป็นระเบียบอยู่แล้ว) -->
    <!-- KPI -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4 col-md-6 col-12">
        <div class="card">
          <div class="card-body d-flex flex-column justify-content-between">
            <h6 class="card-title text-muted">ยอดขาย 30 วัน</h6>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
              <i class="bi bi-cash-coin kpi-icon"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 col-12">
        <div class="card">
          <div class="card-body d-flex flex-column justify-content-between">
            <h6 class="card-title text-muted">จำนวนชิ้นขาย 30 วัน</h6>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <div class="kpi"><?= n($kpi['qty_30d']) ?> ชิ้น</div>
              <i class="bi bi-box-seam kpi-icon"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 col-12">
        <div class="card">
          <div class="card-body d-flex flex-column justify-content-between">
            <h6 class="card-title text-muted">จำนวนผู้ซื้อ 30 วัน</h6>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <div class="kpi"><?= n($kpi['buyers_30d']) ?> คน</div>
              <i class="bi bi-person-check kpi-icon"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts grid -->
    <!-- [LAYOUT] ใช้ Bootstrap Grid (row/col) -->
    <div class="row g-4">

      <div class="col-lg-8 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">ยอดขายรายเดือน (2 ปี)</h6>
            <canvas id="chartMonthly"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">สัดส่วนยอดขายตามหมวด</h6>
            <canvas id="chartCategory"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-6 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">Top 10 สินค้าขายดี (ตามจำนวน)</h6>
            <canvas id="chartTopProducts"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-6 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">ยอดขายตามภูมิภาค</h6>
            <canvas id="chartRegion"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-6 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">วิธีการชำระเงิน</h6>
            <canvas id="chartPayment"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-6 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">ยอดขายรายชั่วโมง</h6>
            <canvas id="chartHourly"></canvas>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h6>
            <canvas id="chartNewReturning"></canvas>
          </div>
        </div>
      </div>

    </div>
  </div>

<script>
// เตรียมข้อมูลจาก PHP -> JS
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

// Utility: pick labels & values
const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });


// --- [STYLE][EDIT] Chart.js Pastel Pink/Blue Theme ---
// ตั้งค่าเริ่มต้นสำหรับ Chart.js ทั้งหมดให้เป็น Light Mode
Chart.defaults.color = '#334155'; // สีฟอนต์สากล (Slate 700)
Chart.defaults.borderColor = 'rgba(0, 0, 0, 0.05)'; // สีเส้นกริด (จางๆ)

// [EDIT] ชุดสีใหม่ Pastel Pink & Blue (สีอ่อนๆ ตามที่ขอ)
const chartColors = [
  '#93c5fd', // blue-300
  '#f9a8d4', // pink-300
  '#7dd3fc', // sky-300
  '#fda4af', // rose-300
  '#a5b4fc', // indigo-300
  '#f0abfc', // fuchsia-300
  '#67e8f9', // cyan-300
  '#fbcfe8', // pink-200
];

// Helper เพื่อใช้สี
const applyColors = (datasets) => {
  return datasets.map((d, i) => ({
    ...d,
    // ใช้สีจาก palette (วนลูป) + เติม alpha (ความโปร่งใส)
    // [EDIT] ปรับความโปร่งใสเป็น 60% ('99') ให้อ่อนลง
    backgroundColor: chartColors[i % chartColors.length] + '99', // '99' = ~60% alpha
    borderColor: chartColors[i % chartColors.length],
    borderWidth: 2
  }));
};
// --- สิ้นสุดการตั้งค่า Chart.js ---


// Monthly
(() => {
  const {labels, values} = toXY(monthly, 'ym', 'net_sales');
  new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: { 
      labels, 
      datasets: applyColors([
        { label: 'ยอดขาย (฿)', data: values, tension: .25, fill: true }
      ])
    },
    options: { responsive: true, maintainAspectRatio: false }
  });
})();

// Category
(() => {
  const {labels, values} = toXY(category, 'category', 'net_sales');
  new Chart(document.getElementById('chartCategory'), {
    type: 'doughnut',
    data: { 
      labels, 
      datasets: [{ 
        data: values,
        backgroundColor: chartColors, // Pie/Doughnut ใช้สีโดยตรง
        borderColor: '#ffffff', // [EDIT] เพิ่มขอบสีขาว
        borderWidth: 3           // [EDIT] เพิ่มความหนาขอบ
      }] 
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } } 
    }
  });
})();

// Top products
(() => {
  const labels = topProducts.map(o => o.product_name);
  const qty = topProducts.map(o => parseInt(o.qty_sold));
  new Chart(document.getElementById('chartTopProducts'), {
    type: 'bar',
    data: { 
      labels, 
      datasets: applyColors([
        // [EDIT] ใช้สีชมพูเป็นสีแรกสำหรับกราฟนี้
        { label: 'ชิ้นที่ขาย', data: qty, backgroundColor: chartColors[1] + '99', borderColor: chartColors[1] }
      ])
    },
    options: {
      indexAxis: 'y', // กราฟแท่งแนวนอน
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { display: false } } // ซ่อน Legend
    }
  });
})();

// Region
(() => {
  const {labels, values} = toXY(region, 'region', 'net_sales');
  new Chart(document.getElementById('chartRegion'), {
    type: 'bar',
    data: { 
      labels, 
      datasets: applyColors([
        // [EDIT] ใช้สีฟ้าอ่อน (sky) สำหรับกราฟนี้
        { label: 'ยอดขาย (฿)', data: values, backgroundColor: chartColors[2] + '99', borderColor: chartColors[2] }
      ])
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { display: false } } 
    }
  });
})();

// Payment
(() => {
  const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
  new Chart(document.getElementById('chartPayment'), {
    type: 'pie', // เปลี่ยนเป็น Pie เพื่อความหลากหลาย
    data: { 
      labels, 
      datasets: [{ 
        data: values,
        backgroundColor: chartColors,
        borderColor: '#ffffff', // [EDIT] เพิ่มขอบสีขาว
        borderWidth: 3           // [EDIT] เพิ่มความหนาขอบ
      }] 
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } } 
    }
  });
})();

// Hourly
(() => {
  const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
  new Chart(document.getElementById('chartHourly'), {
    type: 'bar',
    data: { 
      labels, 
      datasets: applyColors([
        // [EDIT] ใช้สีม่วงอ่อน (indigo) สำหรับกราฟนี้
        { label: 'ยอดขาย (฿)', data: values, backgroundColor: chartColors[4] + '99', borderColor: chartColors[4] }
      ])
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { display: false } } 
    }
  });
})();

// New vs Returning
(() => {
  const labels = newReturning.map(o => o.date_key);
  const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
  const retC = newReturning.map(o => parseFloat(o.returning_sales));
  new Chart(document.getElementById('chartNewReturning'), {
    type: 'line',
    data: { 
      labels,
      datasets: [ // [EDIT] กำหนดสีฟ้า/ชมพู ชัดเจน
        { label: 'ลูกค้าใหม่ (฿)', data: newC, tension: .25, fill: false, backgroundColor: chartColors[0] + '99', borderColor: chartColors[0] },
        { label: 'ลูกค้าเดิม (฿)', data: retC, tension: .25, fill: false, backgroundColor: chartColors[1] + '99', borderColor: chartColors[1] }
      ]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      scales: {
        x: { ticks: { maxTicksLimit: 12 } } // จำกัดจำนวนป้ายกำกับแกน X
      }
    }
  });
})();
</script>

</body>
</html>
