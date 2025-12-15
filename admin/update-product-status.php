<?php
include 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['pro_id'];
    $action = $_POST['action'];

    if ($action === 'toggle_deal') {
        $sql = "UPDATE `products` SET `is_deal` = NOT `is_deal` WHERE `pro_id` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        // Get the updated status
        $stmt = $conn->prepare("SELECT `is_deal` FROM `products` WHERE `pro_id` = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        echo json_encode([
            "status" => "success",
            "newStatus" => (bool)$row['is_deal']
        ]);
    } elseif ($action === 'toggle_disable') {
        $sql = "UPDATE `products` SET `is_disabled` = NOT `is_disabled` WHERE `pro_id` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        // Get the updated status
        $stmt = $conn->prepare("SELECT `is_disabled` FROM `products` WHERE `pro_id` = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        echo json_encode([
            "status" => "success",
            "newStatus" => (bool)$row['is_disabled']
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid action"
        ]);
    }
    exit;
}
?>
