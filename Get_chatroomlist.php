<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$pid = $_POST["pid"];

if (isset($pid)) {
    try {
        $sql = "SELECT pid, roomname, Participants, `create`, status 
                FROM ChattingRoom 
                WHERE JSON_CONTAINS(Participants, JSON_QUOTE(?)) 
                AND (Exiter IS NULL OR NOT JSON_CONTAINS(Exiter, JSON_QUOTE(?)))";
        $stmt = $conn->prepare($sql);

        // JSON 형식으로 변환된 pid를 바인딩
        $stmt->bind_param("ss", $pid, $pid);
        $stmt->execute();
        $stmt->store_result();

        $response = array();

        if ($stmt->num_rows > 0) {
            $rooms = array();
            $stmt->bind_result($chatroom_pid, $roomname_json, $Participants, $create, $state);

            while ($stmt->fetch()) {
                // Participants와 roomname을 JSON으로 디코딩
                $participants_array = json_decode($Participants, true);
                $roomname_array = json_decode($roomname_json, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    continue;
                }

                // roomname 배열에서 pid와 매칭되는 값을 가져옴
                $roomname = isset($roomname_array[$pid]) ? $roomname_array[$pid] : '';

                $last_msg = null;
                $block_reason = null;
                $last_create = null;
                $reader_count = null;

                // friends_block 상태 확인
                foreach ($participants_array as $participant_pid) {
                    $sql_block = "SELECT friends_block FROM user WHERE pid = ?";
                    $stmt_block = $conn->prepare($sql_block);
                    $stmt_block->bind_param("s", $pid);
                    $stmt_block->execute();
                    $stmt_block->bind_result($friends_block_json);
                    $stmt_block->fetch();
                    $stmt_block->close();

                    // friends_block을 JSON 디코딩
                    $friends_block_array = json_decode($friends_block_json, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($friends_block_array)) {
                        // participants_array의 pid가 friends_block 배열의 키로 있는지 확인
                        if (array_key_exists($participant_pid, $friends_block_array)) {
                            // 해당 participant_pid가 friends_block에 있을 경우 그 값을 가져옴
                            $block_reason = $friends_block_array[$participant_pid];
                            error_log("Participant $participant_pid is blocked. Reason: $block_reason");

                            // block_reason보다 이전인 메시지 중 가장 나중에 작성된 메시지 가져오기
                            $sql_msg = "SELECT msg, `create`, 
                                        (SELECT COUNT(*) FROM Chatting 
                                        WHERE room_pid = ? 
                                        AND JSON_CONTAINS(reader, JSON_QUOTE(?)) 
                                        AND JSON_CONTAINS(recipient_pid, JSON_QUOTE(?))) AS reader_count 
                                        FROM Chatting 
                                        WHERE room_pid = ? 
                                        ORDER BY `create` DESC LIMIT 1";
                            $stmt_msg = $conn->prepare($sql_msg);

                            // JSON 형식에 맞게 pid 값을 처리 (이스케이프된 pid)
                            $escaped_pid = json_encode($pid);  // $pid를 JSON 형식으로 인코딩

                            $stmt_msg->bind_param("ssss", $chatroom_pid, $pid, $pid, $chatroom_pid);
                            $stmt_msg->execute();
                            $stmt_msg->bind_result($last_msg, $last_create, $reader_count);
                            if (!$stmt_msg->fetch()) {
                                error_log("No latest message found for room_pid: $chatroom_pid");
                            }
                            $stmt_msg->close();
                            break; // 해당 participant_pid에 대해 블록된 메시지를 찾았으므로 루프 탈출
                        }
                    }
                }

                // 블록되지 않은 경우, 최신 메시지를 가져옴
                if ($block_reason === null) {
                    // 'pid'를 JSON 배열에서 찾는 방식 수정
                    $sql_msg = "SELECT msg, `create`, 
                                        (SELECT COUNT(*) FROM Chatting 
                                        WHERE room_pid = ? 
                                        AND JSON_CONTAINS(reader, JSON_QUOTE(?)) 
                                        AND JSON_CONTAINS(recipient_pid, JSON_QUOTE(?))) AS reader_count 
                                        FROM Chatting 
                                        WHERE room_pid = ? 
                                        ORDER BY `create` DESC LIMIT 1";
                            $stmt_msg = $conn->prepare($sql_msg);

                            // JSON 형식에 맞게 pid 값을 처리 (이스케이프된 pid)
                            $escaped_pid = json_encode($pid);  // $pid를 JSON 형식으로 인코딩

                            $stmt_msg->bind_param("ssss", $chatroom_pid, $pid, $pid, $chatroom_pid);
                            $stmt_msg->execute();
                            $stmt_msg->bind_result($last_msg, $last_create, $reader_count);
                            if (!$stmt_msg->fetch()) {
                                error_log("No latest message found for room_pid: $chatroom_pid");
                            }
                            $stmt_msg->close();
                }
                // if ($reader_count === null) {
                //     $reader_count = 0;
                // }

                // 채팅방 정보와 마지막 메시지 추가
                $chatroom_info = array(
                    "pid" => $chatroom_pid,
                    "roomname" => $roomname,
                    "Participants" => $Participants,
                    "create" => $last_create,
                    "state" => $state,
                    "count" => $reader_count,
                    "last_msg" => $last_msg
                );
                $rooms[] = $chatroom_info;
            }

            $response["success"] = true;
            $response["rooms"] = $rooms;
        } else {
            $response["success"] = false;
            $response["message"] = "No chat rooms found for the given pid.";
        }

        $stmt->close();

    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }
} else {
    $response = array("success" => false, "message" => "pid is missing.");
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();
?>
