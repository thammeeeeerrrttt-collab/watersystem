<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

$message = "";

// حذفنا كود إنشاء عمود Region لأننا سنعتمد على Location بشكل كامل

$customers = $conn->query("SELECT CustomerID, Name FROM Customer");

// جلب المواقع المسجلة مسبقاً لاقتراحها للمستخدم في الحقل (لتسهيل الإدخال)
$existing_locations = $conn->query("SELECT DISTINCT Location FROM Meter WHERE Location IS NOT NULL AND Location != ''");

if(isset($_POST['submit'])){

    $customerid = $_POST['customerid'];
    $meternumber = $_POST['meternumber'];
    $location = $_POST['location']; // الاعتماد على Location بدلاً من Region
    $status = $_POST['status'];

    if($customerid != "" && $meternumber != "" && $location != ""){

        // حفظ البيانات في حقل Location فقط
        $stmt = $conn->prepare("
            INSERT INTO Meter (CustomerID, MeterNumber, Location, Status)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param("isss", $customerid, $meternumber, $location, $status);

        if($stmt->execute()){
            $message = "✅ تم إضافة العداد بنجاح";
        } else {
            $message = "❌ خطأ في الحفظ: " . $conn->error;
        }
    } else {
        $message = "⚠ تأكد من تعبئة البيانات الأساسية (العميل، رقم العداد، الموقع)";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة عداد</title>

<style>
body {
    margin:0;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg,#2193b0,#6dd5ed);
    overflow:hidden;
}

/* الكرت */
.container {
    width:400px;
    margin:80px auto;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(12px);
    padding:30px;
    border-radius:15px;
    text-align:center;
    animation: fadeUp 1.2s ease;
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
    position: relative;
    z-index: 10;
}

/* العنوان */
h2 {
    color:white;
    margin-bottom:10px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

/* الرسالة */
.msg {
    color:white;
    margin-bottom:15px;
    font-weight:bold;
    background: rgba(0,0,0,0.1);
    padding: 8px;
    border-radius: 8px;
    min-height: 20px;
}

/* الحقول */
input, select {
    width:100%;
    padding:12px;
    margin:8px 0;
    border:none;
    border-radius:8px;
    outline:none;
    box-sizing: border-box;
    background: rgba(255,255,255,0.9);
}

/* الزر */
button {
    width:100%;
    padding:12px;
    margin-top: 15px;
    background:#0ea5e9;
    color:white;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-weight:bold;
    font-size: 16px;
    transition:0.3s;
    box-shadow: 0 4px 15px rgba(14, 165, 233, 0.4);
}

button:hover {
    background:#0284c7;
    transform: translateY(-2px);
}

/* زر رجوع */
.back {
    display:inline-block;
    margin-bottom:10px;
    color:white;
    text-decoration:none;
    float: right;
    background: rgba(0,0,0,0.2);
    padding: 5px 10px;
    border-radius: 5px;
    transition: 0.3s;
}
.back:hover {
    background: rgba(0,0,0,0.4);
}

/* أنيميشن */
@keyframes fadeUp {
    from {opacity:0; transform: translateY(40px);}
    to {opacity:1; transform: translateY(0);}
}

/* فقاعات */
.bubble {
    position:absolute;
    bottom:-100px;
    background:rgba(255,255,255,0.3);
    border-radius:50%;
    animation:rise 10s infinite ease-in;
    z-index: 1;
}

@keyframes rise {
    0% {transform: translateY(0) scale(1);}
    100% {transform: translateY(-1000px) scale(0.5);}
}
</style>

</head>

<body>

<div class="container">

<a href="meters.php" class="back">⬅ رجوع</a>
<div style="clear:both;"></div>

<h2>💧 إضافة عداد جديد</h2>

<?php if($message != ""): ?>
    <div class="msg"><?php echo $message; ?></div>
<?php endif; ?>

<form method="POST">

    <select name="customerid" required>
        <option value="">-- اختر العميل --</option>
        <?php while($row = $customers->fetch_assoc()) { ?>
        <option value="<?php echo $row['CustomerID']; ?>">
            <?php echo htmlspecialchars($row['Name']); ?>
        </option>
        <?php } ?>
    </select>

    <input type="text" name="meternumber" placeholder="رقم العداد" required>
    
    <!-- حقل الموقع كإدخال نصي مع قائمة مقترحة من المواقع الموجودة مسبقاً -->
    <input type="text" name="location" list="locations-list" placeholder="الموقع / المنطقة (مهم للفلترة)" required autocomplete="off">
    <datalist id="locations-list">
        <?php if($existing_locations) { while($loc = $existing_locations->fetch_assoc()) { ?>
            <option value="<?php echo htmlspecialchars($loc['Location']); ?>"></option>
        <?php } } ?>
    </datalist>

    <select name="status">
        <option value="active">نشط</option>
        <option value="inactive">متوقف</option>
    </select>

    <button name="submit" type="submit">حفظ العداد</button>

</form>

</div>

<script>
/* فقاعات خلفية متحركة */
for(let i=0; i<15; i++){
    let b = document.createElement("div");
    b.className = "bubble";
    b.style.left = Math.random() * 100 + "vw";
    b.style.width = b.style.height = (Math.random() * 40 + 10) + "px";
    b.style.animationDuration = (Math.random() * 10 + 5) + "s";
    b.style.animationDelay = (Math.random() * 5) + "s";
    document.body.appendChild(b);
}
</script>

</body>
</html>