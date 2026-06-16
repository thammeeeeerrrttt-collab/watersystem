<?php
session_start();
include "../db.php";

// استلام رقم الدورة - افتراضياً 1 إذا لم يرسل
$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 1;

// 1. جلب قائمة العدادات الرئيسية
$meters_query = "SELECT MeterID, MeterNumber, Location FROM meter WHERE MeterType = 'Main'";
$meters_result = $conn->query($meters_query);

// 2. حساب إجمالي مبيعات المشتركين لهذه الدورة
$sales_query = "SELECT SUM(Consumption) as GrandTotalSold FROM bill WHERE PeriodID = '$period_id'";
$sales_result = $conn->query($sales_query);
$sales_row = $sales_result->fetch_assoc();
$grand_total_sold = floatval($sales_row['GrandTotalSold'] ?? 0);

// 3. معالجة العدادات المختارة
$selected_meters = isset($_POST['selected_meters']) ? $_POST['selected_meters'] : [];
$detailed_pumping = [];
$total_pumped = 0;

if (!empty($selected_meters)) {
    $ids = implode(',', array_map('intval', $selected_meters));
    $pumping_query = "SELECT m.MeterNumber, m.Location, r.Consumption 
                      FROM mainmeterreading r 
                      JOIN meter m ON r.MeterID = m.MeterID 
                      WHERE r.PeriodID = '$period_id' AND r.MeterID IN ($ids)";
    $pumping_result = $conn->query($pumping_query);
    
    while($row = $pumping_result->fetch_assoc()) {
        $detailed_pumping[] = $row;
        $total_pumped += floatval($row['Consumption']);
    }
}

$loss = $total_pumped - $grand_total_sold;
$loss_pct = ($total_pumped > 0) ? ($loss / $total_pumped) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحليل الفاقد المخصص</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --accent: #38bdf8;
            --danger: #ef4444;
            --success: #10b981;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            background: var(--bg-dark); 
            color: var(--text-main); 
            margin: 0; padding: 20px; 
        }

        .container { max-width: 900px; margin: 0 auto; }

        /* تنسيق الهيدر وزر الرجوع */
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .btn-back {
            background: rgba(255,255,255,0.1);
            color: var(--text-main);
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.2);
            color: var(--accent);
        }

        .selector-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        .meter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin: 20px 0;
        }

        .meter-item {
            background: rgba(15, 23, 42, 0.5);
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: 0.2s;
            border: 2px solid transparent;
        }

        .meter-item:hover { border-color: var(--accent); }
        .meter-item input { width: 20px; height: 20px; margin-left: 12px; cursor: pointer; }

        .btn-calculate {
            background: var(--accent);
            color: var(--bg-dark);
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-size: 1.1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-calculate:hover { opacity: 0.9; }

        .results-section {
            display: <?= empty($detailed_pumping) ? 'none' : 'block' ?>;
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .pump-row {
            background: rgba(255,255,255,0.05);
            margin-bottom: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-box {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            margin-top: 25px;
            border-right: 5px solid var(--accent);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .stat-line {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .total-highlight { font-size: 1.5rem; color: var(--accent); font-weight: bold; }
        .loss-val { font-size: 1.5rem; color: var(--danger); font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <!-- الهيدر مع زر الرجوع البرمجي -->
    <div class="header-nav">
        <h2 style="margin:0;"><i class="fa fa-calculator"></i> تحليل الفاقد المخصص</h2>
        <button onclick="window.history.back()" class="btn-back">
            <i class="fa fa-arrow-right"></i>
            رجوع للخلف
        </button>
    </div>

    <div class="selector-card">
        <form method="POST">
            <p style="color: var(--text-dim); margin-top:0;">
                <i class="fa fa-info-circle"></i> اختر العدادات الرئيسية (البومبات) لحساب إجمالي الضخ ومقارنته بالمبيعات:
            </p>
            <div class="meter-grid">
                <?php while($m = $meters_result->fetch_assoc()): ?>
                <label class="meter-item">
                    <input type="checkbox" name="selected_meters[]" value="<?= $m['MeterID'] ?>" 
                           <?= in_array($m['MeterID'], $selected_meters) ? 'checked' : '' ?>>
                    <div>
                        <strong><?= $m['MeterNumber'] ?></strong><br>
                        <small style="color: var(--text-dim)"><?= $m['Location'] ?></small>
                    </div>
                </label>
                <?php endwhile; ?>
            </div>
            <button type="submit" class="btn-calculate">
                <i class="fa fa-sync-alt"></i> 
                حساب النتائج الآن
            </button>
        </form>
    </div>

    <div class="results-section">
        <h3 style="margin-bottom:15px;"><i class="fa fa-list-ul"></i> تفصيل الاستهلاك المختار:</h3>
        
        <?php foreach($detailed_pumping as $p): ?>
        <div class="pump-row">
            <span><i class="fa fa-gas-pump"></i> استهلاك <?= $p['MeterNumber'] ?> (<?= $p['Location'] ?>)</span>
            <strong style="color: var(--accent)"><?= number_format($p['Consumption'], 2) ?> وحدة</strong>
        </div>
        <?php endforeach; ?>

        <div class="summary-box">
            <div class="stat-line">
                <span><i class="fa fa-plus-circle"></i> إجمالي كمية الضخ (المختارة):</span>
                <span class="total-highlight"><?= number_format($total_pumped, 2) ?> وحدة</span>
            </div>
            <div class="stat-line">
                <span><i class="fa fa-users"></i> إجمالي المبيع (لكل المشتركين):</span>
                <span style="color: var(--success); font-weight:bold; font-size: 1.2rem;">
                    <?= number_format($grand_total_sold, 2) ?> وحدة
                </span>
            </div>
            <div class="stat-line" style="margin-top: 20px; border-top: 2px dashed rgba(255,255,255,0.1); padding-top:20px;">
                <span><i class="fa fa-exclamation-triangle"></i> كمية الفاقد:</span>
                <span class="loss-val"><?= number_format($loss, 2) ?> وحدة</span>
            </div>
            <div class="stat-line">
                <span><i class="fa fa-percentage"></i> نسبة الفاقد من الضخ:</span>
                <span style="font-size: 1.3rem; color: <?= $loss_pct > 15 ? 'var(--danger)' : 'var(--success)' ?>">
                    <?= number_format($loss_pct, 1) ?> %
                </span>
            </div>
        </div>
    </div>
</div>

</body>
</html>