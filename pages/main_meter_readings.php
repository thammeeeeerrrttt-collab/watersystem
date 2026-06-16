<?php
session_start();
include "../db.php";

// التحقق من تسجيل الدخول
if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

// التحقق من الصلاحية (لأغراض العرض فقط)
$isAdmin = (isset($_SESSION['Role']) && $_SESSION['Role'] === 'Admin');
$message = "";

// --- معالجة العمليات ---

// 1. حفظ قراءة جديدة
if (isset($_POST['save_reading'])) {
    $meter_id = $_POST['meter_id'];
    $prev_reading = $_POST['prev_reading'];
    $curr_reading = $_POST['curr_reading'];
    $reading_type = 'يومية'; 
    $emp_id = $_SESSION['EmployeeID'];
    $consumption = $curr_reading - $prev_reading;

    if ($consumption < 0) {
        $message = "❌ خطأ: القراءة الحالية أقل من السابقة!";
    } else {
        $sql = "INSERT INTO MainMeterReading (MeterID, PreviousReading, CurrentReading, CreatedBy, ReadingType, ReadingDate) 
                VALUES ('$meter_id', '$prev_reading', '$curr_reading', '$emp_id', '$reading_type', NOW())";
        if ($conn->query($sql)) $message = "✅ تم تسجيل القراءة بنجاح.";
    }
}

// 2. حذف قراءة (للمدير فقط)
if (isset($_GET['delete_id']) && $isAdmin) {
    $id = intval($_GET['delete_id']);
    if ($conn->query("DELETE FROM MainMeterReading WHERE ReadingID = $id")) {
        $message = "🗑️ تم حذف العملية بنجاح.";
    }
}

// 3. تعديل قراءة (للمدير فقط)
if (isset($_POST['update_reading']) && $isAdmin) {
    $id = intval($_POST['reading_id']);
    $curr = $_POST['edit_curr'];
    $prev = $_POST['edit_prev'];
    if ($curr >= $prev) {
        $conn->query("UPDATE MainMeterReading SET CurrentReading = '$curr', PreviousReading = '$prev' WHERE ReadingID = $id");
        $message = "✏️ تم تحديث البيانات بنجاح.";
    }
}

// --- جلب البيانات ---
$selected_location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';
$where_clause = $selected_location ? " AND m.Location = '$selected_location' " : "";

$meters_result = $conn->query("SELECT m.*, 
    (SELECT CurrentReading FROM MainMeterReading WHERE MeterID = m.MeterID ORDER BY ReadingID DESC LIMIT 1) as LastStoredReading 
    FROM Meter m WHERE m.MeterType = 'Main' AND m.IsDeleted = 0 $where_clause");

$history_result = null;
if ($isAdmin) {
    $history_sql = "SELECT r.*, m.MeterNumber, m.Location, e.Name as EmployeeName 
                    FROM MainMeterReading r 
                    JOIN Meter m ON r.MeterID = m.MeterID 
                    JOIN Employee e ON r.CreatedBy = e.EmployeeID
                    WHERE m.IsDeleted = 0 $where_clause
                    ORDER BY r.ReadingDate DESC LIMIT 50";
    $history_result = $conn->query($history_sql);
}

$locations_result = $conn->query("SELECT DISTINCT Location FROM Meter WHERE MeterType = 'Main'");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة القراءات</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --primary: #38bdf8; --accent: #22c55e; --danger: #ef4444; --text: #f1f5f9; --border: #334155; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 15px; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header Styling */
        .app-header { background: var(--card); padding: 15px 20px; border-radius: 12px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 10px; }
        .header-right { display: flex; align-items: center; gap: 15px; }
        
        .btn-back { background: #334155; color: white; border: 1px solid var(--border); padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        .btn-back:hover { background: #475569; border-color: var(--primary); }

        .section-title { border-right: 4px solid var(--primary); padding-right: 15px; margin: 25px 0 15px; color: var(--primary); font-weight: bold; display: flex; align-items: center; gap: 10px; }
        
        /* Input Cards */
        .grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
        .card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); padding: 15px; }
        
        /* Table Styles */
        .admin-box { margin-top: 40px; background: #111827; padding: 20px; border-radius: 12px; border: 1px dashed var(--border); }
        .table-responsive { width: 100%; overflow-x: auto; background: var(--card); border-radius: 8px; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: #334155; color: var(--primary); padding: 12px; text-align: right; font-size: 0.85rem; }
        td { padding: 12px; border-bottom: 1px solid var(--border); font-size: 0.85rem; }

        /* Controls */
        input, select { background: #0f172a; border: 1px solid var(--border); color: white; padding: 10px; border-radius: 6px; width: 100%; margin-top: 5px; outline: none; }
        input:focus { border-color: var(--primary); }
        .btn { cursor: pointer; border: none; padding: 10px 15px; border-radius: 8px; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; }
        .btn-save { background: var(--primary); color: #0f172a; width: 100%; justify-content: center; margin-top: 10px; }
        .btn-danger { color: var(--danger); background: rgba(239, 68, 68, 0.1); }
        .btn-edit { color: var(--accent); background: rgba(34, 197, 94, 0.1); }

        .badge { background: #334155; color: var(--primary); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; }
        .alert { background: rgba(56, 189, 248, 0.1); border: 1px solid var(--primary); padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; color: var(--primary); }

        #modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 100; justify-content: center; align-items: center; }
        .modal-body { background: var(--card); padding: 20px; border-radius: 12px; width: 90%; max-width: 400px; border: 1px solid var(--primary); }
    </style>
</head>
<body>

<div class="container">
    <!-- الهيدر العام يحتوي على زر الرجوع للكل -->
    <header class="app-header">
        <div class="header-right">
            <a href="../index.php" class="btn-back">
                <i class="fa fa-arrow-right"></i> رجوع
            </a>
            <div>
                <h3 style="margin:0;">نظام القراءات</h3>
                <small style="color:#94a3b8;"><?= $_SESSION['Name'] ?></small>
            </div>
        </div>
        
        <form method="GET" style="max-width: 200px; width: 100%;">
            <select name="location" onchange="this.form.submit()">
                <option value="">كافة المواقع</option>
                <?php while($l = $locations_result->fetch_assoc()): ?>
                    <option value="<?= $l['Location'] ?>" <?= $selected_location == $l['Location'] ? 'selected' : '' ?>><?= $l['Location'] ?></option>
                <?php endwhile; ?>
            </select>
        </form>
    </header>

    <?php if($message): ?> <div class="alert"><?= $message ?></div> <?php endif; ?>

    <!-- قسم الإدخال -->
    <div class="section-title"><i class="fa fa-plus-circle"></i> تسجيل قراءة جديدة</div>
    <div class="grid-cards">
        <?php while($m = $meters_result->fetch_assoc()): ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span style="color:var(--primary); font-weight:bold;"><?= $m['MeterNumber'] ?></span>
                <span class="badge"><?= $m['Location'] ?></span>
            </div>
            <form method="POST">
                <input type="hidden" name="meter_id" value="<?= $m['MeterID'] ?>">
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label style="font-size:0.75rem;">السابقة</label>
                        <input type="number" name="prev_reading" value="<?= $m['LastStoredReading'] ?? 0 ?>" readonly style="background:#0a0f1a;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.75rem; color:var(--primary);">الحالية</label>
                        <input type="number" step="0.01" name="curr_reading" required autofocus>
                    </div>
                </div>
                <button type="submit" name="save_reading" class="btn btn-save">حفظ القراءة</button>
            </form>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- لوحة التحكم للمدير -->
    <?php if ($isAdmin && $history_result): ?>
    <div class="admin-box">
        <div class="section-title" style="color:var(--accent); border-color:var(--accent);">
            <i class="fa fa-user-shield"></i> سجل المراجعة والإدارة
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>التاريخ والوقت</th>
                        <th>رقم العداد</th>
                        <th>السابقة</th>
                        <th>الحالية</th>
                        <th>الاستهلاك</th>
                        <th>بواسطة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $history_result->fetch_assoc()): 
                        $diff = $row['CurrentReading'] - $row['PreviousReading'];
                    ?>
                    <tr>
                        <td><?= date('Y/m/d H:i', strtotime($row['ReadingDate'])) ?></td>
                        <td><?= $row['MeterNumber'] ?> <br><small style="color:#64748b"><?= $row['Location'] ?></small></td>
                        <td><?= $row['PreviousReading'] ?></td>
                        <td style="color:var(--primary); font-weight:bold;"><?= $row['CurrentReading'] ?></td>
                        <td style="color:var(--accent); font-weight:bold;">+<?= number_format($diff, 2) ?></td>
                        <td><?= $row['EmployeeName'] ?></td>
                        <td>
                            <div style="display:flex; gap:5px;">
                                <button class="btn btn-edit" onclick="openEdit(<?= $row['ReadingID'] ?>, <?= $row['PreviousReading'] ?>, <?= $row['CurrentReading'] ?>)">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <a href="?delete_id=<?= $row['ReadingID'] ?>" class="btn btn-danger" onclick="return confirm('هل تريد حذف هذا السجل نهائياً؟')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- نافذة التعديل -->
    <div id="modal">
        <div class="modal-body">
            <h4 style="margin:0 0 15px; color:var(--primary);">تعديل البيانات المسجلة</h4>
            <form method="POST">
                <input type="hidden" name="reading_id" id="edit_id">
                <label>القراءة السابقة:</label>
                <input type="number" step="0.01" name="edit_prev" id="edit_prev">
                <div style="margin-top:10px;"></div>
                <label>القراءة الحالية:</label>
                <input type="number" step="0.01" name="edit_curr" id="edit_curr">
                
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="update_reading" class="btn btn-save" style="margin:0;">تحديث</button>
                    <button type="button" onclick="closeEdit()" class="btn btn-danger" style="margin:0; background:#334155; color:white;">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    function openEdit(id, prev, curr) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_prev').value = prev;
        document.getElementById('edit_curr').value = curr;
        document.getElementById('modal').style.display = 'flex';
    }
    function closeEdit() {
        document.getElementById('modal').style.display = 'none';
    }
    // إغلاق المودال عند الضغط خارجه
    window.onclick = function(event) {
        if (event.target == document.getElementById('modal')) closeEdit();
    }
</script>

</body>
</html>