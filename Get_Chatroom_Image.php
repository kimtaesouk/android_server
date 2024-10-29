<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$room_pid = $_POST["room_pid"] ?? null; // 단일 pid 값


$response = array();

if (!empty($room_pid)) {
    try {
        // image_path가 null이 아닌 최근 4개의 image_path와 sender_pid를 가져옵니다.
        $sql = "SELECT image_path, sender_pid FROM Chatting WHERE room_pid = ? AND image_path IS NOT NULL ORDER BY `create` DESC LIMIT 3";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $room_pid);

        $stmt->execute();
        $stmt->bind_result($image_path, $sender_pid);

        $images = array();
        while ($stmt->fetch()) {
            $images[] = array(
                "image_path" => $image_path,
                "sender_pid" => $sender_pid
            );
        }
        $response['images'] = $images;
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(array("success" => false, "message" => "Error: " . $e->getMessage()), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
echo json_encode(array("success" => true, "data" => $response), JSON_UNESCAPED_UNICODE);

$conn->close();

?>
