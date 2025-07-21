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

$successMessage = '';

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['incident_id'], $_POST['notes']) && !isset($_POST['resolve'])) {
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

        $successMessage = "Incident #$incident_id has been successfully verified.";
    } else {
        $successMessage = "Incident #$incident_id has already been verified.";
    }
}

// Handle resolution with notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve'], $_POST['incident_id'], $_POST['resolution_notes'])) {
    $incident_id = (int)$_POST['incident_id'];
    $notes = trim($_POST['resolution_notes']);
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE incident_reports SET status = 'resolved' WHERE incident_id = ? AND status = 'in progress'");
    $stmt->execute([$incident_id]);

    if ($stmt->rowCount()) {
        $log = $conn->prepare("INSERT INTO incident_verification_notes (incident_id, verified_by, notes, note_type) VALUES (?, ?, ?, 'resolution')");
        $log->execute([$incident_id, $user_id, $notes]);
        $successMessage = "Incident #$incident_id has been marked as resolved with notes.";
    } else {
        $successMessage = "Incident #$incident_id is not in progress or already resolved.";
    }
}

$incidentReport = new IncidentReport($conn);
$incidents = $incidentReport->getAllIncidents();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Monitor Incidents</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
  <style>
    .table td, .table th { vertical-align: middle; }
    .leaflet-container { height: 200px; width: 100%; }
  </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-3 mb-3">
      <?php include 'navofficial.php'; ?>
    </div>

    <div class="col-lg-9 p-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Monitor Incidents</h2>
        <div>
          <a href="../reportincident.php" class="btn btn-primary me-2">Report Incident</a>
          <a href="generate_incidentmap.php" class="btn btn-secondary">View Incident Map</a>
        </div>
      </div>

      <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
      <?php endif; ?>

      <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search incidents...">

      <?php if (count($incidents) > 0): ?>
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
              <?php foreach ($incidents as $index => $incident): ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($incident['category_name']) ?><br>
                  <span class="badge <?= $incident['urgency_level'] === 'High' ? 'bg-danger' : ($incident['urgency_level'] === 'Medium' ? 'bg-warning' : 'bg-success') ?>">
                    <?= htmlspecialchars($incident['urgency_level']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($incident['details']) ?></td>
                <td><?= htmlspecialchars($incident['reporter_name']) ?></td>
                <td><?= $incident['evidence_count'] ?> file(s)</td>
                <td>
                  <div id="map_<?= $incident['incident_id'] ?>" class="leaflet-container" style="min-height:200px"
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
                <td>
                  <?= $incident['verified_by'] ? htmlspecialchars($incident['verified_by']) . ' (' . $incident['verifier_type'] . ')' : '—' ?>
                </td>
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
                        <textarea class="form-control" name="notes" required rows="4"></textarea>
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
                        <textarea class="form-control" name="resolution_notes" required rows="4"></textarea>
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
      <?php else: ?>
        <div class="alert alert-info">No incidents reported yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const incidentMaps = document.querySelectorAll('[id^="map_"]');
  incidentMaps.forEach(div => {
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
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(value) ? '' : 'none';
    });
  });
});
</script>
</body>
</html>
