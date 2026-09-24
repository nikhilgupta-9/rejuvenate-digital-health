<?php
$pid = intval($_GET['id'] ?? $_GET['patient_id'] ?? 0);
header('Location: patient-profile.php' . ($pid ? '?id=' . $pid : ''));
exit;