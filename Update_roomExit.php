<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$pid = $_POST["pid"] ?? null; // 단일 pid 값
$chattingroom_pid = $_POST["chattingroom_pid"] ?? null; // 단일 pid 값 
$friend_pids = $_POST["friend_pids"];
$image_path = null;
$reader = 0;

$friend_pids_array = [];
if (!empty($friend_pids)) {
    $friend_pids_array = explode(",", $friend_pids);
}


$response = array();

if (!empty($chattingroom_pid) && !empty($pid)) {
    try {
        $sql = "SELECT name FROM user WHERE pid =?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $pid);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        $stmt->close();

        $msg = $name . "님이 채팅방을 나갔습니다.";

        $sql = "INSERT INTO Chatting (room_pid, sender_pid, recipient_pid, msg, image_path, `create`, reader, status) VALUES (?, ?, ?, ?, ?, NOW(), ?, -1)";
        $stmt = $conn->prepare($sql);
        $participants_json_encoded = json_encode($friend_pids_array);
        $stmt->bind_param('ssssss', $chattingroom_pid, $pid, $participants_json_encoded, $msg, $image_path, $participants_json_encoded);
        $stmt->execute();
        $stmt->close();


        // ChattingRoom 테이블의 Exiter 필드에 pid 추가
        $sqlUpdateExiter = "UPDATE ChattingRoom SET Exiter = JSON_ARRAY_APPEND(IFNULL(Exiter, '[]'), '$', ?) WHERE pid = ?";
        $stmtUpdateExiter = $conn->prepare($sqlUpdateExiter);
        $stmtUpdateExiter->bind_param('ss', $pid, $chattingroom_pid);
        $stmtUpdateExiter->execute();
        $stmtUpdateExiter->close();

        // Chatting 테이블에서 recipient_pid에서 pid 제거
        $sqlRemoveRecipientPid = "UPDATE Chatting 
                                  SET recipient_pid = JSON_REMOVE(recipient_pid, JSON_UNQUOTE(JSON_SEARCH(recipient_pid, 'one', ?)))
                                  WHERE room_pid = ? AND JSON_SEARCH(recipient_pid, 'one', ?) IS NOT NULL";
        $stmtRemoveRecipientPid = $conn->prepare($sqlRemoveRecipientPid);
        $stmtRemoveRecipientPid->bind_param('sss', $pid, $chattingroom_pid, $pid);
        $stmtRemoveRecipientPid->execute();
        $stmtRemoveRecipientPid->close();

        

        $response['success'] = true;
    } catch (Exception $e) {
        echo json_encode(array("success" => false, "message" => "Error: " . $e->getMessage()), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

echo json_encode(array("success" => true, "data" => $response), JSON_UNESCAPED_UNICODE);

$conn->close();

?>
