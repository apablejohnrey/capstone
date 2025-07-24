<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/encryption.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

$encryptor = new Encryptor(ENCRYPTION_KEY, ENCRYPTION_IV);

function safeDecryptOrBlank($data, $encryptor) {
    if (!$data) return '';
    $decrypted = $encryptor->decrypt($data);
    return ($decrypted === false || $decrypted === '') ? '' : $decrypted;
}

$db = new Database();
$conn = $db->connect();

$residents = $conn->query("SELECT r.*, u.status FROM Residents r JOIN Users u ON r.user_id = u.user_id")->fetchAll(PDO::FETCH_ASSOC);
$officials = $conn->query("SELECT b.*, u.status FROM Barangay_Officials b JOIN Users u ON b.user_id = u.user_id")->fetchAll(PDO::FETCH_ASSOC);
$tanods = $conn->query("SELECT t.*, u.status FROM Tanods t JOIN Users u ON t.user_id = u.user_id")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Accounts</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- External styles -->
  <link rel="stylesheet" href="../css/navofficial.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Custom styles -->
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #ecf0f1;
      margin: 0;
    }

    .main-content-wrapper {
      margin-left: 250px;
      padding: 30px;
      transition: margin-left 0.3s ease;
    }

    @media (max-width: 768px) {
      .main-content-wrapper {
        margin-left: 0;
      }
    }

    .card-style {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      padding: 20px;
    }

    .tab-content {
      margin-top: 20px;
    }

    .nav-tabs .nav-link {
      font-weight: 600;
      color: #2c3e50;
    }

    .nav-tabs .nav-link.active {
      background-color: #2c3e50;
      color: white;
      border-radius: 5px 5px 0 0;
    }

    .table th, .table td {
      vertical-align: middle !important;
    }

    .btn-action {
      margin-right: 5px;
    }

    .table-responsive {
      overflow-x: auto;
    }

    h2 {
      font-weight: bold;
      color: #2c3e50;
    }

    .sidebar-toggle {
      display: none;
    }

    @media (max-width: 768px) {
      .sidebar-toggle {
        display: block;
        margin: 15px;
      }
    }

    .sidebar-collapsed .sidebar {
      display: none;
    }
  </style>
</head>
<body>
<?php include 'navofficial.php'; ?>

<div class="main-content-wrapper" id="mainWrapper">
  <h2>Manage Accounts</h2>
  <div class="card-style mt-3">

    <ul class="nav nav-tabs" id="accountTabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#officials">Officials</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tanods">Tanods</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#residents">Residents</button></li>
    </ul>

    <div class="tab-content" id="accountTabsContent">
      <div class="tab-pane fade show active" id="officials">
        <div class="d-flex justify-content-between align-items-center mt-3">
          <h5>Barangay Officials</h5>
          <a href="createofficialform.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Create Official</a>
        </div>
        <div class="table-responsive mt-3">
          <table class="table table-hover table-bordered">
            <thead class="table-dark">
              <tr><th>Name</th><th>Position</th><th>Contact</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($officials as $o): ?>
                <tr>
                  <td><?= htmlspecialchars($o['fname'] . ' ' . $o['lname']) ?></td>
                  <td><?= htmlspecialchars($o['position']) ?></td>
                  <td><?= htmlspecialchars(safeDecryptOrBlank($o['contact_number'], $encryptor)) ?></td>
                  <td><?= htmlspecialchars($o['status']) ?></td>
                  <td>
                    <a href="edit_account.php?user_id=<?= $o['user_id'] ?>&role=official" class="btn btn-sm btn-primary btn-action">Edit</a>
                    <button class="btn btn-sm btn-secondary btn-action toggle-status-btn"
                            data-user-id="<?= $o['user_id'] ?>"
                            data-user-name="<?= htmlspecialchars($o['fname'] . ' ' . $o['lname']) ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#statusModal">
                      <?= $o['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tanods -->
      <div class="tab-pane fade" id="tanods">
        <div class="d-flex justify-content-between align-items-center mt-3">
          <h5>Barangay Tanods</h5>
          <a href="create_tanodform.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Create Tanod</a>
        </div>
        <div class="table-responsive mt-3">
          <table class="table table-hover table-bordered">
            <thead class="table-dark">
              <tr><th>Name</th><th>Contact</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($tanods as $t): ?>
                <tr>
                  <td><?= htmlspecialchars($t['fname'] . ' ' . $t['lname']) ?></td>
                  <td><?= htmlspecialchars(safeDecryptOrBlank($t['contact_number'], $encryptor)) ?></td>
                  <td><?= htmlspecialchars($t['status']) ?></td>
                  <td>
                    <a href="edit_account.php?user_id=<?= $t['user_id'] ?>&role=tanod" class="btn btn-sm btn-primary btn-action">Edit</a>
                    <button class="btn btn-sm btn-secondary btn-action toggle-status-btn"
                            data-user-id="<?= $t['user_id'] ?>"
                            data-user-name="<?= htmlspecialchars($t['fname'] . ' ' . $t['lname']) ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#statusModal">
                      <?= $t['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Residents -->
      <div class="tab-pane fade" id="residents">
        <div class="mt-3"><h5>Residents</h5></div>
        <div class="table-responsive mt-3">
          <table class="table table-hover table-bordered">
            <thead class="table-dark">
              <tr><th>Name</th><th>Contact</th><th>Purok</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($residents as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?></td>
                  <td><?= htmlspecialchars(safeDecryptOrBlank($r['contact_number'], $encryptor)) ?></td>
                  <td><?= htmlspecialchars($r['purok']) ?></td>
                  <td><?= htmlspecialchars($r['status']) ?></td>
                  <td>
                    <a href="edit_account.php?user_id=<?= $r['user_id'] ?>&role=resident" class="btn btn-sm btn-primary btn-action">Edit</a>
                    <button class="btn btn-sm btn-secondary btn-action toggle-status-btn"
                            data-user-id="<?= $r['user_id'] ?>"
                            data-user-name="<?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#statusModal">
                      <?= $r['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div> 
  </div> 
</div> 

<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="statusForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Status Change</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Enter your password to change the status of <strong id="targetName"></strong>.</p>
        <input type="hidden" name="user_id" id="targetUserId">
        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
        <div id="statusError" class="text-danger mt-2 d-none">Invalid password or error occurred.</div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-danger">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('toggleSidebar').addEventListener('click', function () {
    document.body.classList.toggle('sidebar-collapsed');
  });

  document.querySelectorAll('.toggle-status-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('targetUserId').value = btn.dataset.userId;
      document.getElementById('targetName').textContent = btn.dataset.userName;
      document.getElementById('statusError').classList.add('d-none');
      document.querySelector('#statusForm input[name="password"]').value = '';
    });
  });

  document.getElementById('statusForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const res = await fetch('toggle_status.php', {
      method: 'POST',
      body: formData
    });
    const result = await res.text();
    if (result.trim() === 'success') {
      location.reload();
    } else {
      document.getElementById('statusError').classList.remove('d-none');
    }
  });
</script>

</body>
</html>
