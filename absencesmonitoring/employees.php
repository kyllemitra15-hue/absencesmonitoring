<?php
include "config.php";

// Export to CSV (separate day and night files inside a ZIP)
// Export to CSV (separate day and night files inside a ZIP)
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    if (!extension_loaded('zip')) {
        exit("ZIP extension not enabled");
    }

    $zip = new ZipArchive();
    $tmpZip = tempnam(sys_get_temp_dir(), "zip");
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        exit("Cannot create zip file");
    }

    $shifts = ["day", "night"];
    foreach ($shifts as $shift) {
        $csvFile = tempnam(sys_get_temp_dir(), "csv"); 
        $fp = fopen($csvFile, "w");     
        fputcsv($fp, ["ID", "Date", "Shift", "Total Employees", "Total Absent", "Matex", "Avance", "HRPro", "Leave Absent"]);

        $sql = "SELECT * FROM absences WHERE shift='$shift' ORDER BY date DESC, id DESC";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $zip->addFile($csvFile, $shift . "_shift.csv");
    }

    $zip->close();

    // send zip
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="absence_records.zip"');
    header('Content-Length: ' . filesize($tmpZip));
    readfile($tmpZip);

    unlink($tmpZip);
    exit;
}


// AJAX handlers for update/delete (expect JSON body)
// GET by id for edit modal
  if (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
  }
  $stmt = $conn->prepare("SELECT id, date, shift, totalEmployees, totalAbsent, matex, avance, hrpro, leaveAbsent FROM absences WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();
  header('Content-Type: application/json');
  if ($row) echo json_encode($row); else echo json_encode(['success' => false, 'message' => 'Not found']);
  exit;
}
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  header('Content-Type: application/json');
  if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
  exit;
  }

    $action = $input['action'];
    if ($action === 'delete') {
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid id']); exit; }
    $stmt = $conn->prepare("DELETE FROM absences WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
      echo json_encode(['success' => true]);
    } 
    else {
      echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
    exit;
  }

    if ($action === 'update') {
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid id']); exit; }
    $date = $input['date'] ?? null;
    $shift = $input['shift'] ?? null;
    $totalEmployees = intval($input['totalEmployees'] ?? 0);
    $matex = intval($input['matex'] ?? 0);
    $avance = intval($input['avance'] ?? 0);
    $hrpro = intval($input['hrpro'] ?? 0);
    $leaveAbsent = intval($input['leaveAbsent'] ?? 0);

    // Recompute totalAbsent from category fields to ensure consistency
    $totalAbsent = $matex + $avance + $hrpro + $leaveAbsent;

    $stmt = $conn->prepare("UPDATE absences SET date = ?, shift = ?, totalEmployees = ?, totalAbsent = ?, matex = ?, avance = ?, hrpro = ?, leaveAbsent = ? WHERE id = ?");
    $stmt->bind_param('ssiiiiiii', $date, $shift, $totalEmployees, $totalAbsent, $matex, $avance, $hrpro, $leaveAbsent, $id);
    if ($stmt->execute()) {
      echo json_encode(['success' => true]);
    } 
    else {
      echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
    exit;
  }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

    function renderTable($conn, $shift) {
    $sql = "SELECT * FROM absences WHERE shift = '$shift' ORDER BY date DESC, id DESC";
    $result = $conn->query($sql);

    echo "<h3>".ucfirst($shift)." Shift</h3>";
    echo "<table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Total Employees</th>
                <th>Total Absent</th>
                <th>Matex</th>
                <th>Avance</th>
                <th>HRPro</th>
                <th>Leave/Rest Day</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>";
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $leaveVal = isset($row['leaveAbsent']) ? $row['leaveAbsent'] : ($row['leave'] ?? '');
            echo "<tr id='row-".$row['id']."'>
                    <td>".$row['id']."</td>
                    <td>".$row['date']."</td>
                    <td>".$row['totalEmployees']."</td>
                    <td>".$row['totalAbsent']."</td>
                    <td>".$row['matex']."</td>
                    <td>".$row['avance']."</td>
                    <td>".$row['hrpro']."</td>
                    <td>".$leaveVal."</td>
                    <td class='actions'>
                      <button class='btn btn-edit editBtn' data-id='".$row['id']."'>Edit</button>
                      <button class='btn btn-delete' onclick='deleteRecord(".$row['id'].")'>Delete</button>
                    </td>
                  </tr>";
        }
    } else {
  echo "<tr><td colspan='9'>No records found</td></tr>";
    }
    echo "</tbody></table><br>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
      <meta charset="UTF-8">
      <title>Absence Records</title>
      <style>
      :root{ --accent:#2e8bf7; --accent-2:#4CAF50; --danger:#e74c3c; --muted:#6b7280; }
      body { 
              font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, 'Helvetica Neue', Arial; 
              background: linear-gradient(180deg,#f7fafc,#eef2f7); 
              padding:24px; 
              color:#0f172a; 
      }
      .container { 
              max-width:1200px; 
              margin:0 auto; 
      }
      .header-bar { 
              display:flex; 
              align-items:center; 
              justify-content:space-between; 
              gap:12px; 
              margin-bottom:18px; 
      }
      h2 { 
              font-size:1.5rem; 
              margin:0; 
              color:#4CAF50; 
      }
      .toolbar { 
              display:flex; 
              gap:10px; 
              align-items:center; 
      }
      .card { 
              background: #ffffff; 
              border-radius:12px; 
              box-shadow: 0 6px 18px rgba(12,18,32,0.06); 
              padding:18px; 
              margin-bottom:20px; 
      }

      .table-wrap { 
              overflow-x:auto; 
              margin-top:12px; 
      }
      table { 
              border-collapse: collapse; 
              width:100%; 
              background:transparent; 
              margin-bottom:8px;
              min-width:900px; 
      }
      th, td { 
              padding:12px 10px; 
              text-align:center; 
              vertical-align:middle; 
              border-bottom:1px solid rgba(15,23,42,0.06); 
      }
      th { 
              background: transparent; 
              color:#0f172a; 
              font-weight:600; 
              text-transform:uppercase; 
              font-size:12px; 
              letter-spacing:0.06em; 
      }
      tr:nth-child(even) td { 
              background: rgba(15,23,42,0.02); 
      }
      .btn { 
              display:inline-flex; 
              gap:8px; 
              align-items:center; 
              padding:8px 12px; 
              border-radius:8px; 
              border:0; 
              cursor:pointer; 
              font-size:0.95rem; 
      }
      .btn-primary { 
              background:var(--accent); 
              color:white; 
      }
      .btn-ghost { 
              background:transparent; 
              color:var(--accent); 
              border:1px solid rgba(46,139,247,0.12); 
      }
      .btn-export { 
              background:linear-gradient(90deg,var(--accent),#6dd3ff); 
              color:white; 
      }
      .btn-edit { 
              background:#0ea5a4; 
              color:white; 
              padding:6px 10px; 
              border-radius:6px; 
      }
      .btn-delete { 
              background:var(--danger); 
              color:white; 
              padding:6px 10px; 
              border-radius:6px; 
      }
      .actions { 
              display:flex; 
              gap:8px; 
              justify-content:center; 
      }

      /* Modal */
      .modal { 
              display:none; 
              position:fixed; 
              inset:0; 
              background:rgba(2,6,23,0.55); 
              align-items:center; 
              justify-content:center; 
              z-index:1200; 
      }
      .modal.show { 
              display:flex; 
      }
      .modal-content { 
              width:460px; 
              max-width:94%; 
              background:#fff; 
              border-radius:12px; 
              padding:18px; 
              box-shadow:0 12px 40px rgba(2,6,23,0.2); 
      }
      .modal-content.large { 
              width:760px; 
              max-width:96%; 
              padding:22px; 
      }
      .modal-content h3 { 
              margin:0 0 8px 0; 
              color:#0f172a; 
      }
      .modal-grid { 
              display:grid; 
              grid-template-columns:1fr 1fr; 
              gap:8px; 
              margin-top:10px; 
      }
      .modal-grid label { 
              font-size:0.85rem; 
              color:var(--muted); 
      }
      .modal-grid input, .modal-grid select { 
              width:100%; 
              padding:8px 10px; 
              border:1px solid #e6e9ef; 
              border-radius:8px; 
      }
      .modal-actions { 
              display:flex; 
              gap:8px; 
              justify-content:flex-end; 
              margin-top:12px; 
      }

    @media (max-width:640px){ .modal-content{ width:92%; } .modal-grid{ grid-template-columns:1fr; } }
  </style>
</head>
<body>
      <div class="container">
        <div class="header-bar">
          <h2>📋 Absence Records</h2>
          <div class="toolbar">
            <button id="backBtn" class="btn btn-ghost">Home</button>
            <a href="employees.php?export=csv" class="btn btn-export">⬇ Export (ZIP)</a>
          </div>
        </div>

    <div class="card">
      <?php renderTable($conn, "day"); ?>
    </div>

    <div style="height:14px"></div>

    <div class="card">
      <?php renderTable($conn, "night"); ?>
    </div>
  </div>
  <script>
    // Back button: prefer history.back(), fallback to main page
    document.getElementById('backBtn').addEventListener('click', function(){
      if (document.referrer) {
        history.back();
      } else {
        window.location.href = 'absence-monitoring-system.html';
      }
    });
  </script>  
  <!-- Edit Modal -->
  <div id="editModal" class="modal" role="dialog" aria-modal="true">
    <div class="modal-content">
      <h3>Edit Record</h3>
      <input type="hidden" id="editId">
      <div class="modal-grid">
        <label>Date: <input id="editDate" type="date"></label>
        <label>Shift:
          <select id="editShift"><option value="day">day</option><option value="night">night</option></select>
        </label>
        <label>Total Employees: <input id="editTotalEmployees" type="number"></label>
        <label>Total Absent: <input id="editTotalAbsent" type="number" readonly></label>
        <label>Matex: <input id="editMatex" type="number"></label>
        <label>Avance: <input id="editAvance" type="number"></label>
        <label>HRPro: <input id="editHrpro" type="number"></label>
        <label>Leave/Rest Day: <input id="editLeave" type="number"></label>
      </div>
      <div class="modal-actions">
        <button onclick="closeModal()" class="btn btn-ghost">Cancel</button>
        <button onclick="saveEdit()" class="btn btn-primary">Save</button>
      </div>
    </div>
  </div>

  <script>
    // Wire edit buttons after DOM ready
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.editBtn').forEach(btn => {
        btn.addEventListener('click', function(){
          const id = this.dataset.id;
          openEdit(id);
        });
      });
    });

    function openEdit(id) {
      // fetch row data from DOM
      const tr = document.getElementById('row-' + id);
      if (!tr) return alert('Row not found');
      const cells = tr.querySelectorAll('td');
      // cells layout: id(0), date(1), totalEmployees(2), totalAbsent(3), matex(4), avance(5), hrpro(6), leave(7)
            document.getElementById('editId').value = id;
            document.getElementById('editDate').value = cells[1].innerText.trim();
            document.getElementById('editTotalEmployees').value = cells[2].innerText.trim();
            document.getElementById('editTotalAbsent').value = cells[3].innerText.trim();
            document.getElementById('editMatex').value = cells[4].innerText.trim();
            document.getElementById('editAvance').value = cells[5].innerText.trim();
            document.getElementById('editHrpro').value = cells[6].innerText.trim();
            document.getElementById('editLeave').value = cells[7].innerText.trim();
          // set shift if present
      const shiftCell = cells[1];
      // shift isn't in a cell directly in the table; try to query via dataset or re-fetch from server
      // For reliability, fetch the record from server by id
      fetch('employees.php?id=' + id)
        .then(r => r.json())
        .then(data => {
          if (data && data.id) {
            document.getElementById('editShift').value = data.shift;
            document.getElementById('editDate').value = data.date;
            document.getElementById('editTotalEmployees').value = data.totalEmployees;
            document.getElementById('editMatex').value = data.matex;
            document.getElementById('editAvance').value = data.avance;
            document.getElementById('editHrpro').value = data.hrpro;
            document.getElementById('editLeave').value = data.leaveAbsent || data.leave || 0;
            // compute total absent from categories
            const calc = (parseInt(data.matex)||0) + (parseInt(data.avance)||0) + (parseInt(data.hrpro)||0) + (parseInt(data.leaveAbsent||data.leave)||0);
            document.getElementById('editTotalAbsent').value = calc;
          }
          }).catch(()=>{}).finally(()=>{
                  // show modal
                  const modal = document.getElementById('editModal');
                  modal.classList.add('show');
                  // make modal larger when editing
                  document.querySelector('.modal-content').classList.add('large');
          });
            }

            function closeModal(){ 
              const modal = document.getElementById('editModal');
              if (!modal) return;
              modal.classList.remove('show');
              const mc = modal.querySelector('.modal-content');
              if (mc) mc.classList.remove('large');
            }

      async function saveEdit(){
      const payload = {
                      action: 'update',
                      id: parseInt(document.getElementById('editId').value,10),
                      date: document.getElementById('editDate').value,
                      shift: document.getElementById('editShift').value,
                      totalEmployees: parseInt(document.getElementById('editTotalEmployees').value,10) || 0,
  // totalAbsent is recomputed server-side, but send the computed value as well for immediate feedback
                      totalAbsent: parseInt(document.getElementById('editTotalAbsent').value,10) || 0,
                      matex: parseInt(document.getElementById('editMatex').value,10) || 0,
                      avance: parseInt(document.getElementById('editAvance').value,10) || 0,
                      hrpro: parseInt(document.getElementById('editHrpro').value,10) || 0,
                      leaveAbsent: parseInt(document.getElementById('editLeave').value,10) || 0
      };
      const res = await fetch('employees.php', { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(payload) });
      const json = await res.json();
      if (json.success) {
        // simple approach: reload page to reflect changes
        window.location.reload();
      } else {
        alert('Update failed: ' + (json.message || 'unknown'));
      }
      }

      async function deleteRecord(id){
      if (!confirm('Delete record #' + id + '?')) return;
      const res = await fetch('employees.php', { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ action:'delete', id }) });
      const json = await res.json();
      if (json.success) {
        // remove row from DOM
        const tr = document.getElementById('row-' + id);
        if (tr) tr.remove();
      } else {
        alert('Delete failed: ' + (json.message || 'unknown'));
      }
      }
    // Recalculate total absent in modal when category inputs change
      function recalcTotalAbsent() {
      const matex = parseInt(document.getElementById('editMatex').value,10) || 0;
      const avance = parseInt(document.getElementById('editAvance').value,10) || 0;
      const hrpro = parseInt(document.getElementById('editHrpro').value,10) || 0;
      const leave = parseInt(document.getElementById('editLeave').value,10) || 0;
      document.getElementById('editTotalAbsent').value = matex + avance + hrpro + leave;
      }

    ['editMatex','editAvance','editHrpro','editLeave'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', recalcTotalAbsent);
    });
    // Modal backdrop and ESC close
      (function(){
        const modal = document.getElementById('editModal');
        if (!modal) return;
        modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
      });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
      })();
  </script>
</body>
</html>
      