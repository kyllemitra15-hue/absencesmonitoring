<?php
include "config.php";

// ========================= CSV EXPORT =========================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!extension_loaded('zip')) exit("ZIP extension not enabled");
    $zip = new ZipArchive();
    $tmpZip = tempnam(sys_get_temp_dir(), "zip");
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) exit("Cannot create zip file");

    foreach (["day","night"] as $shift) {
        $csvFile = tempnam(sys_get_temp_dir(), "csv");
        $fp = fopen($csvFile, "w");
        fputcsv($fp, ["ID","Date","Shift","Total Employees","Total Absent","Total Present","Matex Emp","Matex Absent","Avance Emp","Avance Absent","HRPro Emp","HRPro Absent"]);
        $res = $conn->query("SELECT * FROM man_power WHERE shift='$shift' ORDER BY date DESC,id DESC");
        while($row = $res->fetch_assoc()) fputcsv($fp,$row);
        fclose($fp);
        $zip->addFile($csvFile, "{$shift}_manpower.csv");
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="manpower_records.zip"');
    header('Content-Length: '.filesize($tmpZip));
    readfile($tmpZip);
    unlink($tmpZip);
    exit;
}

// ========================= AJAX =========================
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $in=json_decode(file_get_contents('php://input'),true);
    header('Content-Type: application/json');
    if(!$in||!isset($in['action'])){echo json_encode(['success'=>false,'message'=>'Invalid']);exit;}
    $a=$in['action'];

    if($a==='delete'){
        $id=intval($in['id']??0);
        $stmt=$conn->prepare("DELETE FROM man_power WHERE id=?");
        $stmt->bind_param('i',$id);
        echo json_encode(['success'=>$stmt->execute(),'message'=>$stmt->error??'']);
        exit;
    }
    if($a==='update'){
        $id=intval($in['id']??0);
        $date=$in['date']??null; $shift=$in['shift']??null;
        $totEmp=intval($in['totalEmployees']??0);
        $matexEmp=intval($in['matexEmployees']??0);
        $matexAbs=intval($in['matexAbsent']??0);
        $avEmp=intval($in['avanceEmployees']??0);
        $avAbs=intval($in['avanceAbsent']??0);
        $hrEmp=intval($in['hrproEmployees']??0);
        $hrAbs=intval($in['hrproAbsent']??0);

        $totAbs=$matexAbs+$avAbs+$hrAbs;
        $totPres=$totEmp-$totAbs;

        $stmt=$conn->prepare("UPDATE man_power SET date=?,shift=?,totalEmployees=?,totalAbsent=?,totalPresent=?,matexEmployees=?,matexAbsent=?,avanceEmployees=?,avanceAbsent=?,hrproEmployees=?,hrproAbsent=? WHERE id=?");
        $stmt->bind_param('ssiiiiiiiiii',$date,$shift,$totEmp,$totAbs,$totPres,$matexEmp,$matexAbs,$avEmp,$avAbs,$hrEmp,$hrAbs,$id);
        echo json_encode(['success'=>$stmt->execute(),'message'=>$stmt->error??'']);
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Unknown action']);exit;
}

// ========================= TABLE RENDER =========================
function renderManpowerTable($conn,$shift){
    $res=$conn->query("SELECT * FROM man_power WHERE shift='$shift' ORDER BY date DESC,id DESC");
    echo "<h3>".ucfirst($shift)." Shift</h3>
          <table><thead><tr>
          <th>ID</th><th>Date</th><th>Total Employees</th><th>Total Absent</th><th>Total Present</th>
          <th>Matex Emp</th><th>Matex Absent</th><th>Avance Emp</th><th>Avance Absent</th>
          <th>HRPro Emp</th><th>HRPro Absent</th><th>Actions</th></tr></thead><tbody>";
    if($res->num_rows>0){
        while($r=$res->fetch_assoc()){
            echo "<tr id='row-{$r['id']}'>
                  <td>{$r['id']}</td><td>{$r['date']}</td>
                  <td>{$r['totalEmployees']}</td><td>{$r['totalAbsent']}</td><td>{$r['totalPresent']}</td>
                  <td>{$r['matexEmployees']}</td><td>{$r['matexAbsent']}</td>
                  <td>{$r['avanceEmployees']}</td><td>{$r['avanceAbsent']}</td>
                  <td>{$r['hrproEmployees']}</td><td>{$r['hrproAbsent']}</td>
                  <td class='actions'>
                    <button class='btn btn-edit editBtn' data-id='{$r['id']}'>Edit</button>
                    <button class='btn btn-delete' onclick='deleteRecord({$r['id']})'>Delete</button>
                  </td></tr>";
        }
    }else echo "<tr><td colspan='12'>No records</td></tr>";
    echo "</tbody></table><br>";
}

// ========================= CHART DATA =========================
$trend=["day"=>[],"night"=>[],"overall"=>[]];
$res=$conn->query("SELECT date,LOWER(shift) as s,SUM(totalEmployees) tEmp,SUM(totalAbsent) tAbs
                   FROM man_power GROUP BY date,shift ORDER BY date ASC");
$overall=[];
while($r=$res->fetch_assoc()){
    $pct=$r['tEmp']?round(($r['tAbs']/$r['tEmp'])*100,2):0;
    $trend[$r['s']][]=['date'=>$r['date'],'percent'=>$pct];
    $overall[$r['date']]['emp']=($overall[$r['date']]['emp']??0)+$r['tEmp'];
    $overall[$r['date']]['abs']=($overall[$r['date']]['abs']??0)+$r['tAbs'];
}
foreach($overall as $d=>$v){
    $trend['overall'][]=['date'=>$d,'percent'=>$v['emp']?round(($v['abs']/$v['emp'])*100,2):0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manpower Records</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<style>
body{font-family:Arial,sans-serif;background:#f7fafc;padding:20px;}
h2{color:#4CAF50;} h3{text-align:center;color:#333;}
.container{max-width:1200px;margin:auto;}
table{border-collapse:collapse;width:100%;background:#fff;margin-bottom:20px;}
th,td{border:1px solid #ddd;padding:8px;text-align:center;}
th{background:#4CAF50;color:#fff;}
.btn{background:#4CAF50;color:#fff;padding:6px 12px;border-radius:5px;margin:0 5px;text-decoration:none;}
.btn-edit{background:#0ea5a4;} .btn-delete{background:#e74c3c;}
.actions{display:flex;justify-content:center;gap:6px;}
.card{background:#fff;padding:15px;margin:20px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,.1);}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;}
.modal.show{display:flex;}
.modal-content{background:#fff;padding:18px;border-radius:8px;width:500px;}
.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.modal-grid input{width:100%;padding:6px;}
.modal-actions{margin-top:12px;display:flex;justify-content:flex-end;gap:8px;}
</style>
</head>
<body>
<div class="container">
  <h2>📊 Manpower Records</h2>
  <a href="manpower.html" class="btn">⬅ Back</a>
  <a href="?export=csv" class="btn">⬇ Export (ZIP)</a>
  <button id="exportExcelBtn" class="btn">⬇ Export Excel (XLSX)</button>
  
  <!-- ======== Trend Chart ======== -->
  <div class="card">
    <h3>📈 Absence Trend (Day / Night / Overall)</h3>
    <canvas id="absentTrendChart" height="120"></canvas>
  </div>
  
  <?php renderManpowerTable($conn,"day"); ?>
  <?php renderManpowerTable($conn,"night"); ?>
</div>

<!-- ======== Edit Modal ======== -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <h3>Edit Record</h3>
    <input type="hidden" id="editId">
    <div class="modal-grid">
      <label>Date:<input type="date" id="editDate"></label>
      <label>Shift:
        <select id="editShift"><option value="day">Day</option><option value="night">Night</option></select>
      </label>
      <label>Total Employees:<input type="number" id="editTotalEmployees"></label>
      <label>Total Absent:<input type="number" id="editTotalAbsent" readonly></label>
      <label>Matex Emp:<input type="number" id="editMatexEmployees"></label>
      <label>Matex Absent:<input type="number" id="editMatexAbsent"></label>
      <label>Avance Emp:<input type="number" id="editAvanceEmployees"></label>
      <label>Avance Absent:<input type="number" id="editAvanceAbsent"></label>
      <label>HRPro Emp:<input type="number" id="editHrproEmployees"></label>
      <label>HRPro Absent:<input type="number" id="editHrproAbsent"></label>
    </div>
    <div class="modal-actions">
      <button onclick="closeModal()" class="btn">Cancel</button>
      <button onclick="saveEdit()" class="btn btn-edit">Save</button>
    </div>
  </div>
</div>

<script>
const trendData=<?php echo json_encode($trend); ?>;
document.addEventListener('DOMContentLoaded',()=>{
  const labels=[...new Set([...(trendData.day||[]).map(x=>x.date),
                             ...(trendData.night||[]).map(x=>x.date),
                             ...(trendData.overall||[]).map(x=>x.date)])];
  const get=(arr,d)=> (arr.find(x=>x.date===d)?.percent)||0;
  const day=labels.map(d=>get(trendData.day,d));
  const night=labels.map(d=>get(trendData.night,d));
  const all=labels.map(d=>get(trendData.overall,d));
  new Chart(document.getElementById('absentTrendChart'),{
    type:'line',
    data:{labels:labels,datasets:[
      {label:'Day Shift',data:day,borderColor:'#3498db',fill:false,tension:0.3},
      {label:'Night Shift',data:night,borderColor:'#9b59b6',fill:false,tension:0.3},
      {label:'Overall %',data:all,borderColor:'#e74c3c',fill:false,tension:0.3}
    ]},
    options:{
      responsive:true,
      plugins:{
        legend:{position:'top'},
        tooltip:{callbacks:{label:ctx=>ctx.raw+'%'}},
        datalabels:{display:true,align:'top',formatter:v=>v+'%'}
      },
      scales:{
        y:{title:{display:true,text:'Absence %'},beginAtZero:true,suggestedMax:5},
        x:{title:{display:true,text:'Date'}}
      }
    },
    plugins:[ChartDataLabels]
  });
});

// ======== Edit/Delete JS ========
document.querySelectorAll('.editBtn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const id=btn.dataset.id,row=document.getElementById('row-'+id).querySelectorAll('td');
    document.getElementById('editId').value=id;
    document.getElementById('editDate').value=row[1].innerText.trim();
    document.getElementById('editTotalEmployees').value=row[2].innerText.trim();
    document.getElementById('editTotalAbsent').value=row[3].innerText.trim();
    document.getElementById('editMatexEmployees').value=row[5].innerText.trim();
    document.getElementById('editMatexAbsent').value=row[6].innerText.trim();
    document.getElementById('editAvanceEmployees').value=row[7].innerText.trim();
    document.getElementById('editAvanceAbsent').value=row[8].innerText.trim();
    document.getElementById('editHrproEmployees').value=row[9].innerText.trim();
    document.getElementById('editHrproAbsent').value=row[10].innerText.trim();
    document.getElementById('editModal').classList.add('show');
  });
});
function closeModal(){document.getElementById('editModal').classList.remove('show');}
async function saveEdit(){
  const p={action:'update',
    id:+document.getElementById('editId').value,
    date:editDate.value,shift:editShift.value,
    totalEmployees:+editTotalEmployees.value||0,
    matexEmployees:+editMatexEmployees.value||0,matexAbsent:+editMatexAbsent.value||0,
    avanceEmployees:+editAvanceEmployees.value||0,avanceAbsent:+editAvanceAbsent.value||0,
    hrproEmployees:+editHrproEmployees.value||0,hrproAbsent:+editHrproAbsent.value||0};
  const r=await fetch('manpower.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(p)});
  const j=await r.json(); if(j.success)location.reload();else alert('Update failed:'+j.message);
}
async function deleteRecord(id){
  if(!confirm('Delete record #'+id+'?'))return;
  const r=await fetch('manpower.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id})});
  const j=await r.json(); if(j.success)document.getElementById('row-'+id).remove();else alert('Delete failed:'+j.message);
}
</script>
<script src="https://unpkg.com/exceljs/dist/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script>
async function exportExcelWithChart() {
  try {
    // Header must match the table columns (do NOT include "Shift" and exclude Actions)
    const header = ["ID","Date","Total Employees","Total Absent","Total Present","Matex Emp","Matex Absent","Avance Emp","Avance Absent","HRPro Emp","HRPro Absent"];
    const rows = [header];

    // Collect rows from both tables on the page — take first N columns only (exclude Actions)
    const expectedCols = header.length;
    document.querySelectorAll('table tbody tr').forEach(tr => {
      const cells = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim());
      if (cells.length >= expectedCols) {
        rows.push(cells.slice(0, expectedCols));
      }
    });

    // Create workbook and data sheet
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'Absence Monitoring';
    const ws = workbook.addWorksheet('Manpower Data');

    // Write rows
    ws.addRows(rows);

    // Basic column formatting & header style
    header.forEach((h, i) => {
      ws.getColumn(i+1).width = Math.max(12, Math.min(30, h.length + 8));
    });
    ws.getRow(1).font = { bold: true };

    // Add chart image from canvas (if exists)
    const canvas = document.getElementById('absentTrendChart');
    if (canvas) {
      const dataUrl = canvas.toDataURL('image/png');
      const base64 = dataUrl.split(',')[1];
      const imageId = workbook.addImage({ base64: base64, extension: 'png' });
      const imgSheet = workbook.addWorksheet('Trend Chart');
      imgSheet.addImage(imageId, { tl: { col: 0, row: 0 }, ext: { width: 900, height: 300 } });
    }

    // Generate and save
    const buffer = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([buffer], { type: 'application/octet-stream' }), 'manpower_report.xlsx');
  } catch (err) {
    console.error('Export Excel failed', err);
    alert('Export failed: ' + (err.message || err));
  }
}

document.getElementById('exportExcelBtn').addEventListener('click', exportExcelWithChart);
</script>
<?php $conn->close(); ?>
</body>
</html>
