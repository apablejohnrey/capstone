<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: authentication/loginform.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
    <meta charset="UTF-8">
    <title>Report an Incident</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.0.0/dist/geosearch.css" />
    <style>
        .section-title {
            margin-top: 20px;
            font-weight: bold;
            font-size: 1.2rem;
        }
        #map {
            height: 400px;
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <h2 class="mb-4 text-center">Report an Incident <em>(I-ulat ang Isang Insidente)</em></h2>
    <form action="report_incident_handler.php" method="POST" enctype="multipart/form-data" onsubmit="return validateMap();">

        <!-- Incident Details -->
        <div class="card p-4 mb-3">
            <div class="section-title">Incident Details (<em>Mga Detalye ng Insidente</em>)</div>

            <div class="mb-3">
                <label for="category_id" class="form-label">Category</label>
                <select name="category_id" id="category_id" class="form-select" required>
                    <option value="">-- Select Category --</option>
                    <?php
                    require_once 'includes/db.php';
                    $db = new Database();
                    $conn = $db->connect();
                    try {
                        $stmt = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$row['category_id']}'>{$row['category_name']} ({$row['default_urgency']})</option>";
                        }
                    } catch (PDOException $e) {
                        echo "<option disabled>May error sa pag-load ng mga kategorya</option>";
                    }
                    $conn = null;
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="urgency" class="form-label">Urgency</label>
                <select name="urgency" id="urgency" class="form-select" required>
                    <option value="">-- Select Urgency --</option>
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="purok" class="form-label">Purok</label>
                <select name="purok" id="purok" class="form-select" required>
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <option value="Purok <?= $i ?>">Purok <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="landmark" class="form-label">Landmark</label>
                <input type="text" name="landmark" id="landmark" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="incident_datetime" class="form-label">Date and Time of Incident</label>
                <input type="datetime-local" name="incident_datetime" id="incident_datetime" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="details" class="form-label">Incident Details</label>
                <textarea name="details" id="details" class="form-control" rows="4" required></textarea>
            </div>
        </div>

        <!-- Location -->
        <div class="card p-4 mb-3">
            <div class="section-title">Incident Location</div>
            <label class="form-label">Choose location on map</label>
            <div id="map"></div>
            <input type="hidden" name="latitude" id="latitude" required>
            <input type="hidden" name="longitude" id="longitude" required>
        </div>

        <!-- Victims -->
        <div class="card p-4 mb-3">
            <div class="section-title">Victim(s)</div>
            <div id="victims">
                <div class="row mb-2 victim-group">
                    <div class="col"><input type="text" name="victim_name[]" class="form-control" placeholder="Name"></div>
                    <div class="col"><input type="number" name="victim_age[]" class="form-control" placeholder="Age"></div>
                    <div class="col"><input type="text" name="victim_contact[]" class="form-control" placeholder="Contact"></div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addVictim()">+ Add Victim</button>
        </div>

        <!-- Perpetrators -->
        <div class="card p-4 mb-3">
            <div class="section-title">Perpetrator(s)</div>
            <div id="perpetrators">
                <div class="row mb-2 perpetrator-group">
                    <div class="col"><input type="text" name="perpetrator_name[]" class="form-control" placeholder="Name"></div>
                    <div class="col"><input type="number" name="perpetrator_age[]" class="form-control" placeholder="Age"></div>
                    <div class="col"><input type="text" name="perpetrator_contact[]" class="form-control" placeholder="Contact"></div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addPerpetrator()">+ Add Perpetrator</button>
        </div>

        <!-- Witnesses -->
        <div class="card p-4 mb-3">
            <div class="section-title">Witness(es)</div>
            <div id="witnesses">
                <div class="row mb-2 witness-group">
                    <div class="col"><input type="text" name="witness_name[]" class="form-control" placeholder="Name"></div>
                    <div class="col"><input type="text" name="witness_contact[]" class="form-control" placeholder="Contact"></div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addWitness()">+ Add Witness</button>
        </div>

        <!-- Upload Evidence -->
        <div class="card p-4 mb-4">
            <div class="section-title">Upload Evidence</div>
            <input type="file" name="evidence[]" multiple accept="image/*,video/mp4,video/webm,video/ogg" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Submit</button>
    </form>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const panalPolygon = [
    [13.359511641988888, 123.7258757069265],
    [13.35865726300932, 123.72162640442582],
    [13.35839532113907, 123.7202963323312],
    [13.357235934181006, 123.71698493902386],
    [13.353839090961301, 123.71847973395307],
    [13.353843379962683, 123.71997827897565],
    [13.354692794818547, 123.72146159488028],
    [13.355530795218783, 123.72233668539201],
    [13.355707363910255, 123.7221922473936],
    [13.358519757703732, 123.72545987370904],
    [13.359568838836907, 123.72595639741871],
    [13.359511641988888, 123.7258757069265]
];

const map = L.map('map').setView([13.35667, 123.72167], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '' }).addTo(map);

const boundary = L.polygon(panalPolygon, { color: 'green', fillOpacity: 0.05 }).addTo(map);
let marker;

function isInsidePolygon(lat, lng, polygon) {
    let inside = false;
    let j = polygon.length - 1;
    for (let i = 0; i < polygon.length; i++) {
        let xi = polygon[i][1], yi = polygon[i][0];
        let xj = polygon[j][1], yj = polygon[j][0];
        let intersect = ((yi > lat) !== (yj > lat)) &&
            (lng < (xj - xi) * (lat - yi) / ((yj - yi) + 0.0000001) + xi);
        if (intersect) inside = !inside;
        j = i;
    }
    return inside;
}

function setLatLng(lat, lng) {
    const inside = isInsidePolygon(lat, lng, panalPolygon);
    document.getElementById('latitude').value = lat.toFixed(6);
    document.getElementById('longitude').value = lng.toFixed(6);

    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng]).addTo(map);
    }

    marker.bindPopup(inside ? "Napiling Lokasyon (VALID)" : "Labas sa Barangay Panal").openPopup();
    boundary.setStyle({ color: inside ? 'green' : 'red' });
}

map.on('click', function (e) {
    setLatLng(e.latlng.lat, e.latlng.lng);
});

// if (navigator.geolocation) {
//     navigator.geolocation.getCurrentPosition(function (position) {
//         map.setView([position.coords.latitude, position.coords.longitude], 16);
//     });
// }

function validateMap() {
    const lat = parseFloat(document.getElementById('latitude').value);
    const lng = parseFloat(document.getElementById('longitude').value);

    if (!lat || !lng) {
        alert("Mangyaring pumili ng lokasyon sa mapa.");
        return false;
    }

    if (!isInsidePolygon(lat, lng, panalPolygon)) {
        alert("Ang napiling lokasyon ay nasa labas ng Barangay Panal.");
        return false;
    }

    return true;
}

function addVictim() {
    document.getElementById('victims').insertAdjacentHTML('beforeend', `
        <div class="row mb-2 victim-group">
            <div class="col"><input type="text" name="victim_name[]" class="form-control" placeholder="Name"></div>
            <div class="col"><input type="number" name="victim_age[]" class="form-control" placeholder="Age"></div>
            <div class="col"><input type="text" name="victim_contact[]" class="form-control" placeholder="Contact"></div>
        </div>
    `);
}

function addPerpetrator() {
    document.getElementById('perpetrators').insertAdjacentHTML('beforeend', `
        <div class="row mb-2 perpetrator-group">
            <div class="col"><input type="text" name="perpetrator_name[]" class="form-control" placeholder="Name"></div>
            <div class="col"><input type="number" name="perpetrator_age[]" class="form-control" placeholder="Age"></div>
            <div class="col"><input type="text" name="perpetrator_contact[]" class="form-control" placeholder="Contact"></div>
        </div>
    `);
}

function addWitness() {
    document.getElementById('witnesses').insertAdjacentHTML('beforeend', `
        <div class="row mb-2 witness-group">
            <div class="col"><input type="text" name="witness_name[]" class="form-control" placeholder="Name"></div>
            <div class="col"><input type="text" name="witness_contact[]" class="form-control" placeholder="Contact"></div>
        </div>
    `);
}
</script>
</body>
</html>
