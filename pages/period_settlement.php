<?php
session_start();
include "../db.php";

$message = "";
$error_msg = "";

// 1. معالجة عملية الحفظ الجماعي عند الضغط على زر الاعتماد الموحد
if (isset($_POST['bulk_confirm'])) {
    if (!empty($_POST['selected_analyses'])) {
        $selected_indices = $_POST['selected_analyses']; 
        $count = 0;

        foreach ($selected_indices as $index) {
            $location = $conn->real_escape_string($_POST['data'][$index]['location']);
            $period_id = $conn->real_escape_string($_POST['data'][$index]['period_id']);
            $pumped = floatval($_POST['data'][$index]['total_pumped']);
            $sold = floatval($_POST['data'][$index]['total_sold']);
            
            $loss = $pumped - $sold;
            $loss_pct = ($pumped > 0) ? ($loss / $pumped) * 100 : 0;

            $sql = "INSERT INTO TechnicalLossLog (MainMeterID, PeriodID, TotalPumped, TotalInvoiced, LossAmount, LossPercentage) 
                    VALUES ((SELECT MeterID FROM Meter WHERE Location = '$location' AND MeterType = 'Main' LIMIT 1), 
                    '$period_id', '$pumped', '$sold', '$loss', '$loss_pct')";

            if ($conn->query($sql)) {
                $count++;
            }
        }
        $message = "✅ تم اعتماد وحفظ ($count) سجلات بنجاح في السجل التاريخي.";
    } else {
        $error_msg = "⚠️ يرجى اختيار منطقة واحدة على الأقل للاعتماد.";
    }
}

/**
 * 2. الاستعلام الرئيسي لجلب البيانات غير المرحلة بعد
 */
$sql_check = "SELECT 
                m.Location, 
                m.MeterNumber,
                r.PeriodID,
                SUM(r.Consumption) as Pumped,
                (SELECT SUM(Consumption) FROM bill WHERE Location = m.Location AND PeriodID = r.PeriodID) as Sold
              FROM Meter m
              JOIN MainMeterReading r ON m.MeterID = r.MeterID
              LEFT JOIN TechnicalLossLog log ON m.MeterID = log.MainMeterID AND r.PeriodID = log.PeriodID
              WHERE m.MeterType = 'Main' AND log.LogID IS NULL
              GROUP BY m.Location, r.PeriodID";

$result = $conn->query($sql_check);

if (!$result) {
    $db_error = $conn->error;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اعتماد التحاليل المحددة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #0f172a; color: white; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #1e293b; padding: 20px; border-radius: 12px; border-bottom: 4px solid #0ea5e9; }
        
        .actions-group { display: flex; gap: 10px; align-items: center; }
        
        .btn-bulk { background: #10b981; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .btn-bulk:hover { background: #059669; transform: translateY(-2px); }
        
        /* زر الرجوع المعدل */
        .btn-back { background: #334155; color: white; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: bold; display: flex; align-items: center; gap: 8px; border: 1px solid #475569; transition: 0.3s; }
        .btn-back:hover { background: #475569; color: #f8fafc; }

        .card { background: #1e293b; border-radius: 12px; padding: 15px; margin-bottom: 15px; display: flex; align-items: flex-start; gap: 20px; border: 1px solid #334155; transition: 0.2s; }
        .card:hover { border-color: #38bdf8; }
        .card:has(input:checked) { border-color: #10b981; background: #16253d; }
        
        .check-container { padding-top: 10px; }
        .check-container input[type="checkbox"] { width: 22px; height: 22px; cursor: pointer; accent-color: #10b981; }
        
        .card-content { flex-grow: 1; }
        .data-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px; }
        .data-item { background: #0f172a; padding: 8px; border-radius: 6px; text-align: center; border: 1px solid #1e293b; }
        .data-item span { display: block; font-size: 0.75rem; color: #94a3b8; }
        .data-item strong { font-size: 1rem; color: #38bdf8; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
        .alert-danger { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid #ef4444; }

        .controls-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .select-all-link { background: none; border: none; color: #38bdf8; cursor: pointer; font-size: 0.9rem; padding: 0; text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <form method="POST" id="mainForm">
        <div class="top-bar">
            <div>
                <h2 style="margin:0;"><i class="fa fa-tasks"></i> اختيار واعتماد التحاليل</h2>
                <p style="margin:5px 0 0 0; color:#94a3b8; font-size: 0.9rem;">حدد المناطق التي تريد ترحيلها للسجل الدائم</p>
            </div>
            
            <div class="actions-group">
                <!-- تم تعديل المسار هنا ليعود للمجلد الرئيسي -->
                <a href="../index.php" class="btn-back">
                    <i class="fa fa-arrow-right"></i> إلغاء ورجوع
                </a>
                <button type="submit" name="bulk_confirm" class="btn-bulk">
                    <i class="fa fa-save"></i> اعتماد المحدّد
                </button>
            </div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $message ?></div>
        <?php endif; ?>

        <?php if($error_msg): ?>
            <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= $error_msg ?></div>
        <?php endif; ?>

        <?php if($result && $result->num_rows > 0): ?>
            <div class="controls-row">
                <button type="button" class="select-all-link" onclick="toggleSelectAll()">تحديد الكل / إلغاء التحديد</button>
                <span style="color: #64748b; font-size: 0.8rem;">عدد السجلات المعلقة: <?= $result->num_rows ?></span>
            </div>
            
            <?php 
            $i = 0;
            while($row = $result->fetch_assoc()): 
                $pumped = floatval($row['Pumped']);
                $sold = floatval($row['Sold'] ?? 0);
                $loss = $pumped - $sold;
            ?>
            <div class="card">
                <div class="check-container">
                    <input type="checkbox" name="selected_analyses[]" value="<?= $i ?>" class="row-checkbox">
                </div>
                <div class="card-content">
                    <h3 style="margin:0; display: flex; justify-content: space-between; align-items: center;">
                        <span>منطقة: <?= htmlspecialchars($row['Location']) ?></span>
                        <span style="font-size: 0.8rem; color:#64748b;">دورة: <?= $row['PeriodID'] ?></span>
                    </h3>
                    
                    <input type="hidden" name="data[<?= $i ?>][location]" value="<?= $row['Location'] ?>">
                    <input type="hidden" name="data[<?= $i ?>][period_id]" value="<?= $row['PeriodID'] ?>">
                    <input type="hidden" name="data[<?= $i ?>][total_pumped]" value="<?= $pumped ?>">
                    <input type="hidden" name="data[<?= $i ?>][total_sold]" value="<?= $sold ?>">

                    <div class="data-grid">
                        <div class="data-item">
                            <span>إجمالي الضخ</span>
                            <strong><?= number_format($pumped, 1) ?></strong>
                        </div>
                        <div class="data-item">
                            <span>إجمالي المباع</span>
                            <strong><?= number_format($sold, 1) ?></strong>
                        </div>
                        <div class="data-item">
                            <span>كمية الفقد</span>
                            <strong style="color:#ef4444;"><?= number_format($loss, 1) ?></strong>
                        </div>
                        <div class="data-item">
                            <span>نسبة الفقد</span>
                            <strong style="color:#f59e0b;"><?= ($pumped > 0) ? round(($loss/$pumped)*100, 1) : 0 ?>%</strong>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
                $i++;
            endwhile; 
        else: ?>
            <div style="text-align: center; padding: 60px; background: #1e293b; border-radius: 12px; border: 2px dashed #334155; color: #94a3b8;">
                <i class="fa fa-clipboard-check fa-4x" style="margin-bottom: 20px; opacity: 0.3;"></i>
                <h3>لا يوجد بيانات بانتظار الاعتماد</h3>
                <p>تم ترحيل جميع التحاليل الحالية بنجاح.</p>
                <br>
                <a href="../index.php" class="btn-back" style="display: inline-flex; width: fit-content; margin: 0 auto;">العودة للرئيسية</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}
</script>

</body>
</html>