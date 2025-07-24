<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include './../classes/IncidentMonitor.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['official', 'tanod'])) {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['incident_id'], $_POST['notes']) && !isset($_POST['resolve'])) {
        $incident_id = (int)$_POST['incident_id'];
        $notes = trim($_POST['notes']);
        $user_id = $_SESSION['user_id'];

        $check = $conn->prepare("SELECT verified_by FROM incident_reports WHERE incident_id = ?");
        $check->execute([$incident_id]);
        $alreadyVerified = $check->fetchColumn();

        if (!$alreadyVerified) {
            $stmt = $conn->prepare("UPDATE incident_reports SET verified_by = ?, verified_datetime = NOW(), status = 'in progress' WHERE incident_id = ?");
            $stmt->execute([$user_id, $incident_id]);

            $stmt = $conn->prepare("INSERT INTO incident_verification_notes (incident_id, verified_by, notes, note_type) VALUES (?, ?, ?, 'verification')");
            $stmt->execute([$incident_id, $user_id, $notes]);

            header("Location: monitor_incidents.php?success=verify&id=$incident_id");
            exit();
        } else {
            header("Location: monitor_incidents.php?success=already&id=$incident_id");
            exit();
        }
    }

    if (isset($_POST['resolve'], $_POST['incident_id'], $_POST['resolution_notes'])) {
        $incident_id = (int)$_POST['incident_id'];
        $notes = trim($_POST['resolution_notes']);
        $user_id = $_SESSION['user_id'];

        $stmt = $conn->prepare("UPDATE incident_reports SET status = 'resolved' WHERE incident_id = ? AND status = 'in progress'");
        $stmt->execute([$incident_id]);

        if ($stmt->rowCount()) {
            $log = $conn->prepare("INSERT INTO incident_verification_notes (incident_id, verified_by, notes, note_type) VALUES (?, ?, ?, 'resolution')");
            $log->execute([$incident_id, $user_id, $notes]);
            header("Location: monitor_incidents.php?success=resolve&id=$incident_id");
            exit();
        } else {
            header("Location: monitor_incidents.php?success=fail&id=$incident_id");
            exit();
        }
    }
}

// Prepare success message after redirect
$successMessage = '';
if (isset($_GET['success'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    switch ($_GET['success']) {
        case 'verify':
            $successMessage = "Incident #$id has been successfully verified.";
            break;
        case 'already':
            $successMessage = "Incident #$id has already been verified.";
            break;
        case 'resolve':
            $successMessage = "Incident #$id has been marked as resolved.";
            break;
        case 'fail':
            $successMessage = "Incident #$id is not in progress or already resolved.";
            break;
    }
}


$incidentReport = new IncidentReport($conn);
$filters = [
    'category' => $_GET['category'] ?? '',
    'status' => $_GET['status'] ?? '',
    'urgency' => $_GET['urgency'] ?? ''
];
$incidents = $incidentReport->getAllIncidents($filters);

$itemsPerPage = 10;
$totalIncidents = count($incidents);
$totalPages = ceil($totalIncidents / $itemsPerPage);
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$startIndex = ($currentPage - 1) * $itemsPerPage;
$paginatedIncidents = array_slice($incidents, $startIndex, $itemsPerPage);

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Monitor Incidents</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" rel="stylesheet">
  <style>
    body {
      overflow-x: hidden;
    }
    .table td, .table th {
      vertical-align: middle;
    }
    .leaflet-container {
      height: 200px;
      width: 100%;
    }
    .table-responsive {
      overflow-x: auto;
    }
    tbody tr:hover {
      background-color: #f9f9f9;
    }
    .main-content-wrapper {
      margin-left: 250px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }
    @media (max-width: 768px) {
      .main-content-wrapper {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>
<?php include 'navofficial.php'; ?>
<div class="main-content-wrapper p-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
      <h2 class="mb-2">Monitor Incidents</h2>
      <div>
        <a href="../reportincident.php" class="btn btn-primary me-2">Report Incident</a>
        <a href="generate_incidentmap.php" class="btn btn-secondary">View Map</a>
      </div>
    </div>

    <?php if ($successMessage): ?>
      <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>

    <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search incidents...">

    <form method="get" class="row mb-3 g-2">
      <div class="col-md-3">
        <select name="category" class="form-select">
          <option value="">All Categories</option>
          <?php foreach ($incidentReport->getCategories() as $cat): ?>
            <option value="<?= $cat['category_id'] ?>" <?= $filters['category'] == $cat['category_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['category_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <option value="pending" <?= $filters['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="in progress" <?= $filters['status'] == 'in progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="resolved" <?= $filters['status'] == 'resolved' ? 'selected' : '' ?>>Resolved</option>
        </select>
      </div>
      <div class="col-md-3">
        <select name="urgency" class="form-select">
          <option value="">All Urgency</option>
          <option value="High" <?= $filters['urgency'] == 'High' ? 'selected' : '' ?>>High</option>
          <option value="Medium" <?= $filters['urgency'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
          <option value="Low" <?= $filters['urgency'] == 'Low' ? 'selected' : '' ?>>Low</option>
        </select>
      </div>
      <div class="col-md-3 d-flex">
        <button type="submit" class="btn btn-primary me-2">Filter</button>
        <a href="monitor_incidents.php" class="btn btn-secondary">Reset</a>
      </div>
    </form>

    <?php if (count($paginatedIncidents) > 0): ?>
      <div class="table-responsive">
        <table class="table table-bordered" id="incidentTable">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Type</th>
              <th>Description</th>
              <th>Reporter</th>
              <th>Evidence</th>
              <th>Map</th>
              <th>Date</th>
              <th>Status</th>
              <th>Verified By</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($paginatedIncidents as $index => $incident): ?>
              <tr>
                <td><?= $startIndex + $index + 1 ?></td>
                <td>
                  <?= htmlspecialchars($incident['category_name']) ?><br>
                  <span class="badge <?= $incident['urgency_level'] === 'High' ? 'bg-danger' : ($incident['urgency_level'] === 'Medium' ? 'bg-warning' : 'bg-success') ?>">
                    <?= htmlspecialchars($incident['urgency_level']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($incident['details']) ?></td>
                <td><?= htmlspecialchars($incident['reporter_name']) ?></td>
                <td><?= $incident['evidence_count'] ?> file(s)</td>
                <td>
                  <div id="map_<?= $incident['incident_id'] ?>" class="leaflet-container"
                       data-lat="<?= $incident['latitude'] ?>"
                       data-lng="<?= $incident['longitude'] ?>"
                       data-title="<?= htmlspecialchars($incident['category_name']) ?>"></div>
                  <p><strong>Landmark:</strong> <?= $incident['landmark'] ?: 'N/A' ?></p>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($incident['reported_datetime'])) ?></td>
                <td>
                  <span class="badge <?= $incident['status'] === 'resolved' ? 'bg-success' : ($incident['status'] === 'in progress' ? 'bg-warning' : 'bg-secondary') ?>">
                    <?= ucfirst($incident['status']) ?>
                  </span>
                </td>
                <td><?= $incident['verified_by'] ? htmlspecialchars($incident['verified_by']) . ' (' . $incident['verifier_type'] . ')' : '—' ?></td>
                <td>
                  <a href="view_incident.php?id=<?= $incident['incident_id'] ?>" class="btn btn-sm btn-info mb-1">View</a>
                  <?php if (!$incident['verified_by']): ?>
                    <button class="btn btn-sm btn-success mb-1" data-bs-toggle="modal" data-bs-target="#verifyModal<?= $incident['incident_id'] ?>">Verify</button>
                  <?php endif; ?>
                  <?php if ($incident['status'] === 'in progress'): ?>
                    <button class="btn btn-sm btn-warning mb-1" data-bs-toggle="modal" data-bs-target="#resolveModal<?= $incident['incident_id'] ?>">Resolve</button>
                  <?php endif; ?>
                </td>
              </tr>

              <!-- Verify Modal -->
              <div class="modal fade" id="verifyModal<?= $incident['incident_id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="post">
                    <input type="hidden" name="incident_id" value="<?= $incident['incident_id'] ?>">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Verify Incident #<?= $incident['incident_id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <label>Verification Notes:</label>
                        <textarea class="form-control" name="notes" required></textarea>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Submit</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Resolve Modal -->
              <div class="modal fade" id="resolveModal<?= $incident['incident_id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="post">
                    <input type="hidden" name="incident_id" value="<?= $incident['incident_id'] ?>">
                    <input type="hidden" name="resolve" value="1">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Resolve Incident #<?= $incident['incident_id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <label>Resolution Notes:</label>
                        <textarea class="form-control" name="resolution_notes" required></textarea>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" class="btn btn-warning">Resolve</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>

            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <nav>
        <ul class="pagination justify-content-center mt-3">
          <?php if ($currentPage > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $currentPage - 1 ?>">Previous</a>
            </li>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <?php if ($currentPage < $totalPages): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $currentPage + 1 ?>">Next</a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php else: ?>
      <div class="alert alert-info">No incidents reported yet.</div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[id^="map_"]').forEach(div => {
    const lat = parseFloat(div.dataset.lat);
    const lng = parseFloat(div.dataset.lng);
    const title = div.dataset.title || "Incident";
    const map = L.map(div.id).setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    L.marker([lat, lng]).addTo(map).bindPopup(`<strong>${title}</strong>`);
  });

  document.getElementById('searchInput').addEventListener('keyup', function () {
    const value = this.value.toLowerCase();
    document.querySelectorAll('#incidentTable tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
    });
  });

  // Sidebar toggle
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }
});
</script>
</body>
</html>
