<?php
// Ensure the script always returns valid JSON to the client.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload_error.log');
// Buffer any accidental output so we can discard it and return strict JSON
ob_start();

header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "absence_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    send_json(['success' => false, 'error' => 'Database connection failed']);
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data)) {
    send_json(['success' => false, 'error' => 'No data received']);
}

// Helper to always return JSON and discard any prior output
function send_json($payload) {
    // clear all output buffers to remove stray warnings or HTML
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    // ensure script stops
    exit;
}

// Create the absences table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS `absences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `date` DATE NOT NULL,
    `shift` VARCHAR(16) NOT NULL,
    `totalEmployees` INT DEFAULT 0,
    `totalAbsent` INT DEFAULT 0,
    `matex` INT DEFAULT 0,
    `avance` INT DEFAULT 0,
    `hrpro` INT DEFAULT 0,
    `leaveAbsent` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) 
    ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($createTableSQL) !== TRUE) {
    send_json(['success' => false, 'error' => 'Table creation failed: ' . $conn->error]);
}

// Prepare statement with correct column count
try {
    $stmt = $conn->prepare("INSERT INTO absences 
        (date, shift, totalEmployees, totalAbsent, matex, avance, hrpro, leaveAbsent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
} catch (mysqli_sql_exception $e) {
    $msg = $e->getMessage();
    $conn->close();
    send_json([
        'success' => false,
        'error' => 'Prepare failed: ' . $msg,
        'hint' => 'It looks like the `absences` table may be missing. Run the provided CREATE TABLE SQL to create it.',
        'create_table_sql' => $createTableSQL
    ]);
}

$successCount = 0;
$errors = [];
$i = 0;

foreach ($data as $row) {
    $i++;
    
    // Map Excel columns to database fields with proper data conversion
    $date = $row['Date'] ?? date('Y-m-d');
    
    // Handle Excel date serial numbers if needed
    if (is_numeric($date)) {
        // Excel date serial number conversion
        $unix_date = ($date - 25569) * 86400;
        $date = date('Y-m-d', $unix_date);
    }
    
    $shift = strtolower(trim($row['Shift'] ?? 'day'));
    
    $totalEmployees = intval($row['TotalEmployees'] ?? 0);
    $totalAbsent = intval($row['TotalAbsent'] ?? 0);
    $matex = intval($row['Matex'] ?? 0);
    $avance = intval($row['Avance'] ?? 0);
    $hrpro = intval($row['HRPro'] ?? 0);
    $leaveAbsent = intval($row['LeaveAbsent'] ?? 0);

    // Validate required fields
    if (empty($shift) || !in_array($shift, ['day', 'night'])) {
        $errors[] = "Row $i: Invalid shift value. Must be 'day' or 'night'";
        continue;
    }

    // Auto-calculate totalAbsent if not provided or inconsistent
    $calculatedTotal = $matex + $avance + $hrpro + $leaveAbsent;
    if ($totalAbsent == 0 || $totalAbsent != $calculatedTotal) {
        $totalAbsent = $calculatedTotal;
    }

    if (!@$stmt->bind_param("ssiiiiii", 
    $date, 
    $shift, 
    $totalEmployees, 
    $totalAbsent, 
    $matex, 
    $avance, 
    $hrpro, 
    $leaveAbsent
)) {
    $errors[] = "Row $i: bind_param failed: " . $stmt->error;
    continue;
}

    if (!@$stmt->execute()) {
        $errors[] = "Row $i: execute failed: " . $stmt->error;
        continue;
    }

    $successCount++;
}

try {
    $stmt->close();
    $conn->close();
} catch (Throwable $e) {
    // ignore
}

$result = ['success' => $successCount > 0, 'imported' => $successCount];
if (!empty($errors)) $result['errors'] = $errors;
file_put_contents(__DIR__ . '/debug_output.json', json_encode($result));
send_json($result);
?>