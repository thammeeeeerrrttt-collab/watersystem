<?php
session_start();
include "../db.php";

// 1. معالجة العمليات (حذف وتعديل)
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'delete') {
        $meter_id = $_POST['meter_id'];
        $date = $_POST['reading_date'];
        $sql = "DELETE FROM MainMeterReading WHERE MeterID = '$meter_id' AND DATE(ReadingDate) = '$date'";
        $conn->query($sql);
        header("Location: daily_analysis.php?date=$date&msg=deleted");
        exit();
    }
    
    if ($_POST['action'] == 'update') {
        $meter_id = $_POST['meter_id'];
        $date = $_POST['reading_date'];
        $curr = $_POST['new_current'];
        $prev = $_POST['new_previous'];
        $cons = $curr - $prev;
        
        $sql = "UPDATE MainMeterReading SET 
                CurrentReading = '$curr', 
                PreviousReading = '$prev', 
                Consumption = '$cons' 
                WHERE MeterID = '$meter_id' AND DATE(ReadingDate) = '$date'";
        $conn->query($sql);
        header("Location: daily_analysis.php?date=$date&msg=updated");
        exit();
    }
}

// جلب تاريخ اليوم أو تاريخ محدد من البحث
$target_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// استعلام مطور لجلب كافة تفاصيل القراءات والمشي لكل عداد
$sql = "SELECT 
            m.MeterID, 
            m.MeterNumber, 
            m.Location, 
            m.ParentMeterID,
            p.MeterNumber as ParentNumber,
            r.PreviousReading, 
            r.CurrentReading, 
            r.Consumption as MyConsumption,
            r.ReadingDate,
            (SELECT Consumption FROM MainMeterReading WHERE MeterID = m.ParentMeterID AND DATE(ReadingDate) = '$target_date' LIMIT 1) as ParentConsumption
        FROM Meter m
        JOIN MainMeterReading r ON m.MeterID = r.MeterID
        LEFT JOIN Meter p ON m.ParentMeterID = p.MeterID
        WHERE m.MeterType = 'Main' 
        AND DATE(r.ReadingDate) = '$target_date'
        ORDER BY m.ParentMeterID ASC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحليل الفواقد والتحكم بالقراءات</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { 
            --bg: #0f172a; --card: #1e293b; --accent: #38bdf8; 
            --danger: #ef4444; --success: #22c55e; --text-dim: #94a3b8;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); color: white; margin: 0; padding: 20px; }
        
        /* Header Section */
        .top-bar { display: flex; justify-content: space-between; align-items: center; background: var(--card); padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .btn-back { color: var(--text-dim); text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .btn-back:hover { color: white; }
        
        .date-control { display: flex; align-items: center; gap: 10px; background: var(--bg); padding: 5px 15px; border-radius: 8px; border: 1px solid #334155; }
        .date-picker { background: transparent; border: none; color: var(--accent); font-weight: bold; outline: none; cursor: pointer; }

        /* Table Design */
        .table-container { background: var(--card); border-radius: 15px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        table { width: 100%; border-collapse: collapse; text-align: center; }
        th { background: #334155; padding: 15px; color: var(--accent); font-weight: 500; font-size: 0.9rem; }
        td { padding: 15px; border-bottom: 1px solid #334155; vertical-align: middle; }
        tr:hover { background: rgba(255,255,255,0.02); }

        .meter-info { text-align: right; }
        .meter-info strong { display: block; color: white; }
        .meter-info small { color: var(--text-dim); font-size: 0.8rem; }

        .consumption-badge { background: rgba(56, 189, 248, 0.15); color: var(--accent); padding: 5px 12px; border-radius: 6px; font-weight: 800; font-size: 1.1rem; border: 1px solid rgba(56, 189, 248, 0.3); }
        
        .loss-val { font-weight: bold; font-family: monospace; }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .status-high { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); }
        .status-normal { background: rgba(34, 197, 94, 0.1); color: var(--success); border: 1px solid var(--success); }

        /* Actions */
        .btn-action { border: none; background: none; cursor: pointer; padding: 8px; transition: 0.2s; border-radius: 5px; }
        .btn-edit { color: var(--accent); }
        .btn-edit:hover { background: rgba(56, 189, 248, 0.1); }
        .btn-delete { color: var(--danger); }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.1); }

        /* Modal Simple Style */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: var(--card); padding: 25px; border-radius: 12px; width: 400px; border: 1px solid #334155; }
        .modal-content h3 { margin-top: 0; color: var(--accent); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text-dim); font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 10px; background: var(--bg); border: 1px solid #334155; border-radius: 6px; color: white; box-sizing: border-box; }
        .modal-btns { display: flex; gap: 10px; margin-top: 20px; }
        .btn-submit { flex: 1; padding: 10px; background: var(--accent); border: none; border-radius: 6px; color: var(--bg); font-weight: bold; cursor: pointer; }
        .btn-cancel { flex: 1; padding: 10px; background: #334155; border: none; border-radius: 6px; color: white; cursor: pointer; }

        @media (max-width: 768px) {
            .table-container { overflow-x: auto; }
            .top-bar { flex-direction: column; gap: 15px; text-align: center; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div>
        <a href="../index.php" class="btn-back"><i class="fa fa-chevron-right"></i> العودة للرئيسية</a>
        <h2 style="margin: 10px 0 0 0;"><i class="fa fa-chart-line" style="color: var(--accent);"></i> تحليل الفواقد اليومي</h2>
    </div>
    
    <form>
        <div class="date-control">
            <i class="fa fa-calendar-alt" style="color: var(--text-dim);"></i>
            <input type="date" name="date" value="<?= $target_date ?>" onchange="this.form.submit()" class="date-picker">
        </div>
    </form>
</div>

<?php if(isset($_GET['msg'])): ?>
    <div style="background: var(--success); color: white; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
        تمت العملية بنجاح!
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th class="meter-info">العداد والموقع</th>
                <th>قراءة سابقة</th>
                <th>قراءة حالية</th>
                <th>المشي (الاستهلاك)</th>
                <th>المصدر (الأب)</th>
                <th>الفاقد</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                    $loss = 0; $loss_pct = 0;
                    if($row['ParentMeterID'] != NULL && $row['ParentConsumption'] > 0) {
                        $loss = $row['ParentConsumption'] - $row['MyConsumption'];
                        $loss_pct = ($loss / $row['ParentConsumption']) * 100;
                    }
                ?>
                <tr>
                    <td class="meter-info">
                        <strong><?= $row['MeterNumber'] ?></strong>
                        <small><?= $row['Location'] ?></small>
                    </td>
                    <td style="color: var(--text-dim);"><?= number_format($row['PreviousReading'], 2) ?></td>
                    <td style="font-weight: bold;"><?= number_format($row['CurrentReading'], 2) ?></td>
                    <td><span class="consumption-badge"><?= number_format($row['MyConsumption'], 2) ?></span></td>
                    <td>
                        <?= $row['ParentNumber'] ? 
                            '<span style="font-size:0.8rem; color:var(--text-dim)">'.$row['ParentNumber'].'</span><br>'.number_format($row['ParentConsumption'], 2) : 
                            '<span style="color:var(--success); font-size:0.8rem;">رئيسي (المصدر)</span>' ?>
                    </td>
                    <td class="loss-val" style="color: <?= $loss > 0 ? 'var(--danger)' : 'var(--success)' ?>">
                        <?= number_format($loss, 2) ?> <small>(<?= number_format($loss_pct, 1) ?>%)</small>
                    </td>
                    <td>
                        <?php if($loss_pct > 15): ?>
                            <span class="status-pill status-high">فاقد عالٍ</span>
                        <?php elseif($row['ParentMeterID'] != NULL): ?>
                            <span class="status-pill status-normal">طبيعي</span>
                        <?php else: ?>
                            <span style="color:var(--text-dim); font-size: 0.7rem;">محطة ضخ</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn-action btn-edit" title="تعديل" onclick='openEditModal(<?= json_encode($row) ?>)'>
                            <i class="fa fa-edit"></i>
                        </button>
                        <button class="btn-action btn-delete" title="حذف" onclick="confirmDelete(<?= $row['MeterID'] ?>, '<?= $target_date ?>')">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="padding: 80px; color: var(--text-dim);">
                        <i class="fa fa-database fa-3x" style="margin-bottom: 15px; display: block;"></i>
                        لا توجد قراءات مسجلة لهذا التاريخ
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal التعديل -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>تعديل القراءة</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="meter_id" id="edit_meter_id">
            <input type="hidden" name="reading_date" value="<?= $target_date ?>">
            
            <div class="form-group">
                <label>رقم العداد</label>
                <input type="text" id="edit_meter_num" disabled>
            </div>
            <div class="form-group">
                <label>القراءة السابقة</label>
                <input type="number" step="0.01" name="new_previous" id="edit_prev" required>
            </div>
            <div class="form-group">
                <label>القراءة الحالية</label>
                <input type="number" step="0.01" name="new_current" id="edit_curr" required>
            </div>
            
            <div class="modal-btns">
                <button type="submit" class="btn-submit">حفظ التغييرات</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<!-- نموذج الحذف المخفي -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="meter_id" id="del_meter_id">
    <input type="hidden" name="reading_date" id="del_reading_date">
</form>

<script>
    function openEditModal(data) {
        document.getElementById('edit_meter_id').value = data.MeterID;
        document.getElementById('edit_meter_num').value = data.MeterNumber;
        document.getElementById('edit_prev').value = data.PreviousReading;
        document.getElementById('edit_curr').value = data.CurrentReading;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function confirmDelete(id, date) {
        if (confirm("هل أنت متأكد من حذف هذه القراءة؟ سيؤثر هذا على تحليل الفواقد.")) {
            document.getElementById('del_meter_id').value = id;
            document.getElementById('del_reading_date').value = date;
            document.getElementById('deleteForm').submit();
        }
    }

    // إغلاق المودال عند النقر خارجه
    window.onclick = function(event) {
        if (event.target == document.getElementById('editModal')) {
            closeModal();
        }
    }
</script>

</body>
</html>