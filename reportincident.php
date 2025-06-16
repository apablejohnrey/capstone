<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
    <meta charset="UTF-8">
    <title>Report an Incident </title>
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
            <div class="section-title">Incident Details( <em>Mga Detalye ng Insidente</em> )</div>

            <div class="mb-3">
                <label for="category_id" class="form-label">Category (<em>Kategorya</em>) – <strong>Required</strong></strong></label>
                <select name="category_id" id="category_id" class="form-select" required>
                    <option value="">-- Select Category (<em>Piliin ang Kategorya</em>) --</option>
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
                <label for="urgency" class="form-label"> Urgency ( <em>Pagmamadali</em> ) – <strong>Required</strong></label>
                <select name="urgency" id="urgency" class="form-select" required>
                    <option value="">-- Select Category (<em>Piliin ang Kategorya</em>) --</option>
                    <option value="High">High (<em>Mataas</em>)</option>
                    <option value="Medium">Medium (<em>Katamtaman</em>)</option>
                    <option value="Low">Low (<em>Mababa</em>)</option>
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
                <label for="landmark" class="form-label">Landmark (<em>Palatandaan</em> )</label>
                <input type="text" name="landmark" id="landmark" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="incident_datetime" class="form-label">Date and Time of Incident (<em>Petsa at Oras ng Insidente</em>) – <strong>Required</strong></label>
                <input type="datetime-local" name="incident_datetime" id="incident_datetime" class="form-control" required>
            </div>
            <div class="mb-3">
              <div class="section-title">Incident Details (<em>Mga Detalye ng Insidente</em>)</></div>
                <textarea name="details" id="details" class="form-control" rows="4" required></textarea>
            </div>
        </div>

        <!-- Incident Location -->
        <div class="card p-4 mb-3">
         <div class="section-title">Incident Location (<em>Lokasyon ng Insidente</em>)</div>
            <div class="mb-3">
                <label class="form-label"> Choose location on map (<em>Pumili ng lokasyon sa mapa</em>)– <strong>Required</strong></label>
                <div id="map"></div>
            </div>
            <input type="hidden" name="latitude" id="latitude" required>
            <input type="hidden" name="longitude" id="longitude" required>
        </div>

        <!-- Victims -->
        <div class="card p-4 mb-3">
            <div class="section-title">Victim(s) (<em>Mga Biktima</em>)</div>
            <div id="victims">
                <div class="row mb-2 victim-group">
                    <div class="col"><input type="text" name="victim_name[]" class="form-control" placeholder="Name (Buong Pangalan)"></div>
                    <div class="col"><input type="number" name="victim_age[]" class="form-control" placeholder="Age (Edad)"></div>
                    <div class="col"><input type="text" name="victim_contact[]" class="form-control" placeholder="Contact Number (Numero ng Telepono)"></div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addVictim()">+Add Victim(<em>Magdagdag ng Biktima</em>)</button>
        </div>

        <!-- Perpetrators -->
        <div class="card p-4 mb-3">
            <div class="section-title">Perpetrator(s) <em>(Mga Salarin)</em></div>
            <div id="perpetrators">
                <div class="row mb-2 perpetrator-group">
                    <div class="col"><input type="text" name="perpetrator_name[]" class="form-control" placeholder=" Name (Buong Pangalan)"></div>
                    <div class="col"><input type="number" name="perpetrator_age[]" class="form-control" placeholder="Age (Edad)"></div>
                    <div class="col"><input type="text" name="perpetrator_contact[]" class="form-control" placeholder="Contact Number (Numero ng Telepono)"></div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addPerpetrator()">+ Add Perpetrator(<em>Magdagdag ng Salarin</em>)</button>
        </div>

        <!-- Witnesses -->
        <div class="card p-4 mb-3">
            <div class="section-title">Witness(es) <em>(Mga Saksi)</em></div>
            <div id="witnesses">
                <div class="row mb-2 witness-group">
                    <div class="col"><input type="text" name="witness_name[]" class="form-control" placeholder="Name (Buong Pangalan)"></div>
                    <div class="col"><input type="text" name="witness_contact[]" class="form-control" placeholder="Contact Number (Numero ng Telepono)"></div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addWitness()">+ Add Witness(<em>Magdagdag ng Saksi</em>)</button>
        </div>

        <!-- Upload Evidence -->
        <div class="card p-4 mb-4">
            <div class="section-title">Upload Evidence (<em>Mag-upload ng Ebidensya</em>)</div>
            <input type="file" name="evidence[]" multiple accept="image/*,video/mp4,video/webm,video/ogg" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Submit (<em>Isumite ang Ulat</em>)</button>
    </form>
</div>

<!-- Scripts -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-geosearch@3.0.0/dist/bundle.min.js"></script>

<script>
    function addVictim() {
        const html = `
            <div class="row mb-2 victim-group">
                <div class="col"><input type="text" name="victim_name[]" class="form-control" placeholder="Buong Pangalan"></div>
                <div class="col"><input type="number" name="victim_age[]" class="form-control" placeholder="Edad"></div>
                <div class="col"><input type="text" name="victim_contact[]" class="form-control" placeholder="Numero ng Telepono"></div>
            </div>
        `;
        document.getElementById('victims').insertAdjacentHTML('beforeend', html);
    }

    function addPerpetrator() {
        const html = `
            <div class="row mb-2 perpetrator-group">
                <div class="col"><input type="text" name="perpetrator_name[]" class="form-control" placeholder="Buong Pangalan"></div>
                <div class="col"><input type="number" name="perpetrator_age[]" class="form-control" placeholder="Edad"></div>
                <div class="col"><input type="text" name="perpetrator_contact[]" class="form-control" placeholder="Numero ng Telepono"></div>
            </div>
        `;
        document.getElementById('perpetrators').insertAdjacentHTML('beforeend', html);
    }

    function addWitness() {
        const html = `
            <div class="row mb-2 witness-group">
                <div class="col"><input type="text" name="witness_name[]" class="form-control" placeholder="Buong Pangalan"></div>
                <div class="col"><input type="text" name="witness_contact[]" class="form-control" placeholder="Numero ng Telepono"></div>
            </div>
        `;
        document.getElementById('witnesses').insertAdjacentHTML('beforeend', html);
    }


    const defaultLat = 13.35667;
    const defaultLng = 123.72167;
    const map = L.map('map', {
        center: [defaultLat, defaultLng],
        zoom: 17,
        maxBounds: [[13.3500, 123.7150], [13.3630, 123.7280]],
        maxBoundsViscosity: 1.0
    });


    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: ''
    }).addTo(map);

    let marker;
    function setLatLng(lat, lng) {
        document.getElementById('latitude').value = lat.toFixed(6);
        document.getElementById('longitude').value = lng.toFixed(6);
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map).bindPopup("Napiling Lokasyon").openPopup();
        }
    }

    map.on('click', function (e) {
        setLatLng(e.latlng.lat, e.latlng.lng);
    });

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (position) {
            map.setView([position.coords.latitude, position.coords.longitude], 16);
        }, function () {
            console.warn("Tinanggihan ang pahintulot sa lokasyon.");
        });
    }

    function validateMap() {
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        if (!lat || !lng) {
            alert("Mangyaring pumili ng lokasyon sa mapa.");
            return false;
        }
        return true;
    }
</script>
</body>
</html>
