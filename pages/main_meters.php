<?php
session_start();
include "../db.php";

// التحقق من الصلاحيات (Admin فقط لهذه الصفحة)
if (!isset($_SESSION['EmployeeID']) || $_SESSION['Role'] != 'Admin') {
    die("غير مصرح لك بالوصول.");
}

$message = "";

// 1. إجراء الحذف (Soft Delete)
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // التأكد من أننا نحذف فقط عدادات رئيسية أو فرعية وليس مشتركين بالخطأ هنا
    $sql = "UPDATE Meter SET IsDeleted = 1 WHERE MeterID = $del_id AND (MeterType = 'Main' OR MeterType = 'SubMain')";
    if ($conn->query($sql)) {
        header("Location: manage_meters.php?msg=deleted");
        exit();
    }
}

// 2. إضافة عداد جديد
if (isset($_POST['add_meter'])) {
    $m_num = $conn->real_escape_string($_POST['meter_number']);
    $loc = $conn->real_escape_string($_POST['location']);
    $m_type = $conn->real_escape_string($_POST['meter_type']); 
    $parent = ($_POST['parent_id'] == "") ? "NULL" : intval($_POST['parent_id']);
    
    $sql = "INSERT INTO Meter (MeterNumber, Location, MeterType, ParentMeterID, Status) 
            VALUES ('$m_num', '$loc', '$m_type', $parent, 'Active')";
    
    if ($conn->query($sql)) {
        $message = "✅ تم إضافة العداد بنجاح كـ " . ($m_type == 'Main' ? 'بومبة رئيسية' : 'عداد منطقة فرعي');
    } else {
        $message = "❌ خطأ في الإضافة: " . $conn->error;
    }
}

// 3. إجراء التعديل (Update)
if (isset($_POST['update_meter'])) {
    $edit_id = intval($_POST['edit_id']);
    $m_num = $conn->real_escape_string($_POST['meter_number']);
    $loc = $conn->real_escape_string($_POST['location']);
    $m_type = $conn->real_escape_string($_POST['meter_type']);
    $parent = ($_POST['parent_id'] == "") ? "NULL" : intval($_POST['parent_id']);

    $sql = "UPDATE Meter SET MeterNumber='$m_num', Location='$loc', MeterType='$m_type', ParentMeterID=$parent WHERE MeterID=$edit_id";
    if ($conn->query($sql)) {
        $message = "✅ تم تحديث بيانات العداد بنجاح";
    } else {
        $message = "❌ خطأ في التحديث: " . $conn->error;
    }
}

// تعديل الاستعلام: جلب العدادات (الرئيسية والمناطق فقط) واستبعاد عدادات المشتركين
$sql = "SELECT m1.*, m2.MeterNumber as ParentName 
        FROM Meter m1 
        LEFT JOIN Meter m2 ON m1.ParentMeterID = m2.MeterID 
        WHERE m1.IsDeleted = 0 
        AND (m1.MeterType = 'Main' OR m1.MeterType = 'SubMain')
        ORDER BY m1.MeterType ASC, m1.MeterID DESC";
$result = $conn->query($sql);

// تعديل القائمة المنسدلة: إظهار المصادر المتاحة فقط للربط (استبعاد المشتركين)
$parents_list = $conn->query("SELECT MeterID, MeterNumber, MeterType FROM Meter WHERE IsDeleted = 0 AND (MeterType = 'Main' OR MeterType = 'SubMain')");
$parents_data = [];
while($p = $parents_list->fetch_assoc()) {
    $parents_data[] = $p;
}

// رسائل التنبيه من الرابط
if(isset($_GET['msg']) && $_GET['msg'] == 'deleted') $message = "🗑️ تم حذف العداد من النظام بنجاح";
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العدادات والشبكة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { 
            --main-color: #0ea5e9; 
            --bg: #0f172a; 
            --card: #1e293b; 
            --sub-color: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: white; padding: 20px; margin: 0; }
        .container { max-width: 1200px; margin: auto; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: var(--card); padding: 15px 25px; border-radius: 15px; border: 1px solid #334155; }
        .card { background: var(--card); padding: 25px; border-radius: 15px; margin-bottom: 25px; border: 1px solid #334155; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 0.9rem; color: #94a3b8; margin-right: 5px; }
        input, select { background: #0f172a; border: 1px solid #334155; color: white; padding: 12px; border-radius: 10px; width: 100%; transition: 0.3s; }
        input:focus, select:focus { border-color: var(--main-color); outline: none; }
        .btn { padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px; transition: 0.3s; text-decoration: none; color: white; }
        .btn-add { background: var(--main-color); }
        .btn-update { background: var(--success); width: 100%; justify-content: center; margin-top: 10px; }
        .btn-cancel { background: #475569; width: 100%; justify-content: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: rgba(255,255,255,0.02); border-radius: 10px; overflow: hidden; }
        th, td { padding: 15px; text-align: right; border-bottom: 1px solid #334155; }
        th { background: #334155; color: var(--main-color); font-weight: 600; }
        .badge { padding: 5px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; }
        .badge-main { background: rgba(14, 165, 233, 0.2); color: #38bdf8; border: 1px solid #0ea5e9; }
        .badge-sub { background: rgba(139, 92, 246, 0.2); color: #a78bfa; border: 1px solid #8b5cf6; }
        .action-btn { background: none; border: none; cursor: pointer; font-size: 1.1rem; transition: 0.2s; }
        .edit-icon { color: var(--warning); }
        .delete-icon { color: var(--danger); }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; animation: fadeIn 0.5s; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); }
        .modal-content { background: var(--card); margin: 10% auto; padding: 30px; width: 400px; border-radius: 20px; border: 1px solid var(--main-color); position: relative; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="container">
    <div class="header-flex">
        <h2 style="margin:0;"><i class="fa fa-network-wired"></i> هيكلة شبكة العدادات</h2>
        <a href="../index.php" style="color:#94a3b8; text-decoration:none;"><i class="fa fa-home"></i> الرئيسية</a>
    </div>

    <?php if($message): ?>
        <div class="alert" style="background: <?= (strpos($message, '✅') !== false || strpos($message, '🗑️') !== false) ? '#065f46' : '#7f1d1d' ?>;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0; color: var(--main-color);"><i class="fa fa-plus-circle"></i> إضافة عداد جديد</h3>
        <form method="POST" class="form-grid">
            <div class="input-group">
                <label>رقم العداد</label>
                <input type="text" name="meter_number" required>
            </div>
            <div class="input-group">
                <label>نوع العداد</label>
                <select name="meter_type" required>
                    <option value="Main">بومبة رئيسية (مصدر)</option>
                    <option value="SubMain">عداد منطقة (فرعي)</option>
                </select>
            </div>
            <div class="input-group">
                <label>يتبع لـ</label>
                <select name="parent_id">
                    <option value="">-- عداد مستقل --</option>
                    <?php foreach($parents_data as $p): ?>
                        <option value="<?= $p['MeterID'] ?>">
                            <?= $p['MeterType'] == 'Main' ? '💎' : '📍' ?> <?= $p['MeterNumber'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group">
                <label>الموقع</label>
                <input type="text" name="location">
            </div>
            <button type="submit" name="add_meter" class="btn btn-add">
                <i class="fa fa-save"></i> حفظ
            </button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0;"><i class="fa fa-list"></i> قائمة العدادات الرئيسية والمناطق</h3>
        <table>
            <thead>
                <tr>
                    <th>العداد</th>
                    <th>النوع</th>
                    <th>الموقع</th>
                    <th>التبعية</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= $row['MeterNumber'] ?></strong></td>
                        <td>
                            <span class="badge <?= $row['MeterType'] == 'Main' ? 'badge-main' : 'badge-sub' ?>">
                                <?= $row['MeterType'] == 'Main' ? 'بومبة' : 'منطقة' ?>
                            </span>
                        </td>
                        <td><?= $row['Location'] ?: '-' ?></td>
                        <td><?= $row['ParentName'] ?: 'مصدر مباشر' ?></td>
                        <td>
                            <button class="action-btn edit-icon" onclick='openEditModal(<?= json_encode($row) ?>)'>
                                <i class="fa fa-edit"></i>
                            </button>
                            <a href="?delete_id=<?= $row['MeterID'] ?>" class="action-btn delete-icon" onclick="return confirm('حذف العداد؟')">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">لا يوجد بيانات</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h3 style="color:var(--warning); margin-top:0;"><i class="fa fa-edit"></i> تعديل العداد</h3>
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="input-group" style="margin-bottom:15px;">
                <label>رقم العداد</label>
                <input type="text" name="meter_number" id="edit_number" required>
            </div>
            <div class="input-group" style="margin-bottom:15px;">
                <label>النوع</label>
                <select name="meter_type" id="edit_type">
                    <option value="Main">بومبة رئيسية (مصدر)</option>
                    <option value="SubMain">عداد منطقة (فرعي)</option>
                </select>
            </div>
            <div class="input-group" style="margin-bottom:15px;">
                <label>يتبع لـ</label>
                <select name="parent_id" id="edit_parent">
                    <option value="">-- عداد مستقل --</option>
                    <?php foreach($parents_data as $p): ?>
                        <option value="<?= $p['MeterID'] ?>"><?= $p['MeterNumber'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group" style="margin-bottom:20px;">
                <label>الموقع</label>
                <input type="text" name="location" id="edit_location">
            </div>
            <div style="display:flex; gap:10px;">
                <button type="submit" name="update_meter" class="btn btn-update">حفظ</button>
                <button type="button" onclick="closeModal()" class="btn btn-cancel">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(data) {
        document.getElementById('edit_id').value = data.MeterID;
        document.getElementById('edit_number').value = data.MeterNumber;
        document.getElementById('edit_type').value = data.MeterType;
        document.getElementById('edit_parent').value = data.ParentMeterID || "";
        document.getElementById('edit_location').value = data.Location;
        document.getElementById('editModal').style.display = 'block';
    }
    function closeModal() { document.getElementById('editModal').style.display = 'none'; }
    window.onclick = function(event) { if (event.target == document.getElementById('editModal')) closeModal(); }
</script>
</body>
</html>