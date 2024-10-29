<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$my_pid = $_POST["my_pid"];
$friend_pids_string = $_POST["friend_pids"];
$message = $_POST["message"];
$isBlocked = $_POST["isBlocked"];

// 이미지 파일 처리
$image_file_path = null;
$image_file_name = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
    $image_file = $_FILES['image'];
    $upload_dir = '/var/www/html/NewProject/uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $file_name = $image_file['name']; 
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $image_file_name = uniqid() . '.' . $file_extension;
    $image_file_path = $upload_dir . $image_file_name;
    
    if (!move_uploaded_file($image_file['tmp_name'], $image_file_path)) {
        error_log('Failed to move uploaded file: ' . $image_file['tmp_name']);
        echo json_encode(array('success' => false, 'message' => 'Image upload failed'));
        exit;
    }
}

$friend_pids_array = [];
if (!empty($friend_pids_string)) {
    $friend_pids_array = explode(",", $friend_pids_string);
}

$participants_array = array_merge([$my_pid], $friend_pids_array);
$participants_json = json_encode($participants_array);

$response = array();

try {
    // 채팅방 확인
    $sql = "SELECT pid FROM ChattingRoom WHERE JSON_CONTAINS(Participants, ?) AND JSON_LENGTH(Participants) = ?";
    $stmt = $conn->prepare($sql);
    $participants_count = count($participants_array);
    $stmt->bind_param("si", $participants_json, $participants_count);
    $stmt->execute();
    $stmt->bind_result($chattingroom_pid);
    $stmt->fetch();
    $stmt->close();

    if (!empty($chattingroom_pid)) {
        if ($participants_count == 2) {
            $sqlRemoveExiter = "UPDATE ChattingRoom 
                                SET Exiter = JSON_REMOVE(Exiter, JSON_UNQUOTE(JSON_SEARCH(Exiter, 'one', ?)))
                                WHERE pid = ?";
            $stmtRemoveExiter = $conn->prepare($sqlRemoveExiter);
            $stmtRemoveExiter->bind_param('ss', $friend_pids_string, $chattingroom_pid);
            $stmtRemoveExiter->execute();
            $stmtRemoveExiter->close();
        }
        // 채팅 메시지 저장
        $sql = "INSERT INTO Chatting (room_pid, sender_pid, recipient_pid, msg, image_path, `create`, reader) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($sql);

        $participants_json_encoded = json_encode($participants_array);
        $friend_pids_json = json_encode($friend_pids_array);

        $stmt->bind_param("ssssss", $chattingroom_pid, $my_pid, $participants_json_encoded, $message, $image_file_path, $friend_pids_json);

        if ($stmt->execute()) {
            // 마지막으로 삽입된 채팅 메시지의 pid 가져오기
            $chatting_pid = $conn->insert_id;  // 이 부분이 추가되었습니다.

            $response["success"] = true;
            $response["message"] = "Message and image sent successfully.";
            $response["chatting_pid"] = $chatting_pid;  // 채팅 pid를 응답에 추가

            $image_base_url = 'http://49.247.32.169/NewProject/uploads/';
            $response["image_path"] = !empty($image_file_name) ? $image_base_url . $image_file_name : null;
        } else {
            throw new Exception("Message saving failed.");
        }
        $stmt->close();

    } else {
        // 새로운 채팅방 생성 및 메시지 저장 로직
        // ...
    }
} catch (Exception $e) {
    $response = array("success" => false, "message" => "Error: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();
?>
