<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

// POST 데이터 가져오기
$my_pid = $_POST["my_pid"];
$friend_pids_string = $_POST["friend_pids"];
$message = $_POST["message"];

// friend_pids를 배열로 변환
$friend_pids_array = explode(",", $friend_pids_string);
$friend_pids_json = json_encode($friend_pids_array);

// 전체 참가자 배열 생성 (내 PID 포함)
$participants_array = array_merge([$my_pid], $friend_pids_array);
$participants_json = json_encode($participants_array);

$response = array();

try {
    $sql = "SELECT pid FROM ChattingRoom WHERE JSON_CONTAINS(Participants, ?) AND JSON_LENGTH(Participants) = ?";
    $stmt = $conn->prepare($sql);
    $participants_count = count($participants_array);
    $stmt->bind_param("si", $participants_json, $participants_count);
    $stmt->execute();
    $stmt->bind_result($chattingroom_pid);
    $stmt->fetch();
    $stmt->close();

    if (!empty($chattingroom_pid)) {
        // 기존 채팅방이 있을 경우 채팅 메시지 저장
        $sql = "INSERT INTO Chatting (room_pid, sender_pid, msg, `create`, reader) VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $chattingroom_pid, $my_pid, $message, $friend_pids_json);

        if ($stmt->execute()) {
            $response["success"] = true;
            $response["message"] = "Message sent successfully.";
        } else {
            throw new Exception("Message saving failed.");
        }

        $stmt->close();
    } else {
        // 새로운 채팅방 생성
        $room_names = [];

        // 모든 참가자의 이름을 가져오기
        $names = [];
        foreach ($participants_array as $pid) {
            $sql = "SELECT name FROM user WHERE pid = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $pid);
            $stmt->execute();
            $stmt->bind_result($name);
            $stmt->fetch();
            $stmt->close();

            $names[$pid] = $name;
        }

        // 각 참가자의 PID를 키로 하고, 나머지 참가자들의 이름을 값으로 하는 roomname 생성
        foreach ($participants_array as $pid) {
            $other_names = array_diff($names, [$names[$pid]]);
            $room_names[$pid] = implode(', ', $other_names);
        }

        // roomname을 JSON으로 인코딩
        $roomname_json = json_encode($room_names, JSON_UNESCAPED_UNICODE);

        // 채팅방 생성
        $sql = "INSERT INTO ChattingRoom (roomname, Participants, `create`) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $roomname_json, $participants_json);
        if (!$stmt->execute()) {
            throw new Exception("ChattingRoom creation failed.");
        }
        $chattingroom_pid = $stmt->insert_id;
        $stmt->close();

        // 채팅 메시지 저장
        $sql = "INSERT INTO Chatting (room_pid, sender_pid, msg, `create`, reader) VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $chattingroom_pid, $my_pid, $message, $friend_pids_json);

        if ($stmt->execute()) {
            $response["success"] = true;
            $response["message"] = "Message sent successfully.";
        } else {
            throw new Exception("Message saving failed.");
        }

        $stmt->close();
    }
} catch (Exception $e) {
    $response = array("success" => false, "message" => "Error: " . $e->getMessage());
}

// JSON 형식으로 응답 전송
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// 데이터베이스 연결 종료
$conn->close();
?>
