<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$room_pid = $_POST["room_pid"];

// room_pid가 설정되어 있고 비어 있지 않은지 확인
if (isset($room_pid) && !empty($room_pid)) {
    try {
        // 채팅방 세부 정보를 가져오는 SQL 쿼리 준비
        $sql = "SELECT pid, room_pid, sender_pid, msg, `create`, reader, status 
                FROM Chatting 
                WHERE room_pid = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $room_pid);  // 쿼리에서 room_pid 바인딩
        $stmt->execute();
        $stmt->store_result();  // 결과 저장

        $response = array();

        if ($stmt->num_rows > 0) {
            // SQL 쿼리에서 선택한 필드와 bind_result()의 변수가 일치하도록 수정
            $stmt->bind_result($pid, $room_pid, $sender_pid, $msg, $create, $reader, $status);

            // 데이터를 모두 처리하기 전에는 stmt를 닫으면 안 됨
            // $stmt->close(); // 이 줄은 데이터를 가져오기 전에 호출되면 안 됩니다!

            // ChattingRoom 테이블에서 Participants(참여자 목록)을 가져옴
            $sql_participants = "SELECT Participants FROM ChattingRoom WHERE pid = ?";
            $stmt_participants = $conn->prepare($sql_participants);
            $stmt_participants->bind_param("s", $room_pid);  // 쿼리에서 room_pid 바인딩
            $stmt_participants->execute();
            $stmt_participants->bind_result($Participants);
            $stmt_participants->fetch();
            $stmt_participants->close();  // Participants 데이터를 가져왔으므로 stmt 닫기

            // Participants(참여자) JSON 배열로 디코드
            $participants_array = json_decode($Participants, true);

            $names = array();  // 참여자 이름을 저장할 배열
            $rooms = array();  // 채팅방 정보를 저장할 배열

            // 각 참여자의 이름을 user 테이블에서 가져옴
            foreach ($participants_array as $participant_pid) {
                $sql_user = "SELECT name FROM user WHERE pid = ?";
                $stmt_user = $conn->prepare($sql_user);
                $stmt_user->bind_param("s", $participant_pid);  // 참여자 PID 바인딩
                $stmt_user->execute();
                $stmt_user->store_result();
                $stmt_user->bind_result($name);
                $stmt_user->fetch();
                $stmt_user->close();

                // 참여자의 이름을 PID를 키로 names 배열에 저장
                $names[$participant_pid] = $name;
            }

            // 채팅 메시지 및 기타 세부 정보를 가져옴
            while ($stmt->fetch()) {
                // 메시지 보낸 사람의 이름을 user 테이블에서 가져옴
                $sql_sender = "SELECT name FROM user WHERE pid = ?";
                $stmt_sender = $conn->prepare($sql_sender);
                $stmt_sender->bind_param("s", $sender_pid);  // 발신자 PID 바인딩
                $stmt_sender->execute();
                $stmt_sender->store_result();
                $stmt_sender->bind_result($sender_name);
                $stmt_sender->fetch();
                $stmt_sender->close();

                // reader 필드가 JSON 배열인 경우 reader 수 계산
                $reader_array = json_decode($reader, true);
                $count = is_array($reader_array) ? count($reader_array) : 0;

                // 채팅방 정보를 배열로 준비
                $chatroom_info = array(
                    "pid" => $pid,
                    "room_pid" => $room_pid,
                    "sender_pid" => $sender_pid,
                    "sender_name" => $sender_name,  // 메시지 보낸 사람의 이름 추가
                    "msg" => $msg,
                    "create" => $create,
                    "count" => $count,  // reader 수
                    "status" => $status
                );
                $rooms[] = $chatroom_info;  // 정보를 rooms 배열에 추가
            }

            $stmt->close();  // 모든 데이터를 처리한 후 stmt 닫기

            // 응답 데이터 준비
            $response["success"] = true;
            $response["rooms"] = $rooms;  // 채팅방 정보 추가
            $response["names"] = $names;  // 참여자 이름 정보 추가
        } else {
            $response["success"] = false;
            $response["message"] = "No chat rooms found for the given room_pid.";
        }

    } catch (Exception $e) {
        // 예외 발생 시 오류 메시지 응답
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }
} else {
    // room_pid가 없는 경우 오류 메시지 응답
    $response = array("success" => false, "message" => "room_pid is missing.");
}

// 응답을 JSON 형식으로 출력 (UTF-8 인코딩 유지)
echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();  // 데이터베이스 연결 종료
?>
