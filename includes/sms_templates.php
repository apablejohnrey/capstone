<?php
function sms_template($type, $data) {
    switch ($type) {
        case 'hearing_notice':
            return "NOTICE: {$data['name']}, you are requested to attend a barangay hearing on {$data['date']}.";
        case 'summon':
            return "SUMMON: {$data['name']}, please appear at the barangay hall on {$data['date']}.";
        case 'alert':
            return "EMERGENCY: {$data['incident']} reported on {$data['date']}. Stay alert and follow instructions.";
        default:
            return '';
    }
}
