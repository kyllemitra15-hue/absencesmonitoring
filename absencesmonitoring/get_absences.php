<?php
require "config.php";
header("Content-Type: application/json");

// Default to today if no date is provided
$date = ($_GET['date'] ?? 'today') === 'today' ? date('Y-m-d') : $_GET['date'];

// --- DAILY SUMMARY for this date ---
$stmt = $conn->prepare("
    SELECT 
        SUM(totalEmployees) AS totalEmployees,
        SUM(totalAbsent) AS totalAbsent,
        SUM(matex) AS matex,
        SUM(avance) AS avance,
        SUM(hrpro) AS hrpro,
        SUM(leaveAbsent) AS `leave`
    FROM absences
    WHERE date = ?
");
$stmt->bind_param("s", $date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- RECENT RECORDS (last 10 rows overall) ---
$sql = "SELECT id, date, shift, totalEmployees, totalAbsent, matex, avance, hrpro, leaveAbsent
        FROM absences
        ORDER BY date DESC, id DESC
        LIMIT 10";
$res = $conn->query($sql);
$recent = [];
while ($r = $res->fetch_assoc()) {
    $r['leave'] = $r['leaveAbsent'];
    unset($r['leaveAbsent']);
    $recent[] = $r;
}

echo json_encode([
    "success" => true,
    "summary" => $summary,
    "recent"  => $recent
]);
$conn->close();