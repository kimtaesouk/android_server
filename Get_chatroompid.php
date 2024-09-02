<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$pid = (int)$_POST["pid"];  // $pid를 명시적으로 숫자로 변환
$friend_pids = json_decode($_POST["friend_pid"], true); // friend_pid가 배열일 수 있으므로 JSON 디코딩

if (isset($pid) && isset($friend_pids)) {
    try {
        $sql = "SELECT friends, friends_block, friends_hide FROM user WHERE pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $stmt->bind_result($friends, $friends_block, $friends_hide);
        $stmt->fetch();
        $stmt->close();

        // 친구 리스트를 JSON에서 배열로 디코딩
        $friends_array = json_decode($friends, true);
        $friends_block_array = json_decode($friends_block, true);
        $friends_hide_array = json_decode($friends_hide, true);

        // JSON 디코딩 오류 체크
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            $friends_array = [];
            $friends_block_array = [];
            $friends_hide_array = [];
        }

        // 최종 응답 배열 초기화
        $response = array();

        // 친구 이름을 저장할 배열
        $names = array();

        $all_pids = array_merge([$pid], $friend_pids);

        // 각 friend_pid에 대해 루프 돌기
        foreach ($friend_pids as $friend_pid) {
            // 친구 이름 가져오기
            $sql = "SELECT name FROM user WHERE pid = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $friend_pid);
            $stmt->execute();
            $stmt->bind_result($friend_name);
            $stmt->fetch();
            $stmt->close();

            // 이름을 배열에 추가
            $names[] = $friend_name;
        }

        $sql = "SELECT pid FROM ChattingRoom 
        WHERE JSON_CONTAINS(Participants, ?, '$') 
        AND JSON_LENGTH(Participants) = JSON_LENGTH(?)";

        $stmt = $conn->prepare($sql);

        // PIDs를 JSON으로 인코딩하기 전에 문자열로 변환
        $friend_pids_as_strings = array_map('strval', $all_pids);  // 숫자를 문자열로 변환
        $friend_pid_json_value = json_encode($friend_pids_as_strings); // 변환된 배열을 JSON으로 인코딩

        $stmt->bind_param("ss", $friend_pid_json_value, $friend_pid_json_value);
        $stmt->execute();
        $stmt->bind_result($chatting_room_pid);
        $stmt->fetch();
        $stmt->close();
        

            // 첫 번째 친구의 채팅방 PID만 응답에 추가 (하나의 값만 저장)
            if (!isset($response["chatting_room_pid"])) {
                $response["chatting_room_pid"] = $chatting_room_pid ? $chatting_room_pid : null;
            }

            // 첫 번째 친구의 상태만 응답에 추가 (하나의 값만 저장)
            if (!isset($response["success"])) {
                if (is_array($friends_block_array) && array_key_exists($friend_pid, $friends_block_array)) {
                    $response["success"] = "isBlock";
                } elseif (is_array($friends_array) && in_array($friend_pid, $friends_array)) {
                    $response["success"] = "true";
                } elseif (is_array($friends_hide_array) && in_array($friend_pid, $friends_hide_array)) {
                    $response["success"] = "true";
                } else {
                    $response["success"] = "false";
                    $response["message"] = "friend_pid is not in the friends list.";
                }
            }
        

        // 이름들을 콤마로 구분하여 하나의 문자열로 합치기
        $response["name"] = implode(", ", $names);

    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
        error_log("Exception: " . $e->getMessage());
    }
} else {
    $response = array("success" => false, "message" => "pid or friend_pid is missing.");
}

// 응답을 JSON 형식으로 출력
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// 데이터베이스 연결 종료
$conn->close();

?>
