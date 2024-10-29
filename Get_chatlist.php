<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$my_pid = $_POST["my_pid"];
$room_pid = $_POST["room_pid"];

// 추가된 부분: 페이지 번호와 페이지 크기 받기
$page = isset($_POST["page"]) ? intval($_POST["page"]) : 1; // 기본값은 1
$page_size = isset($_POST["page_size"]) ? intval($_POST["page_size"]) : 25; // 기본값은 25

// 페이지 번호와 페이지 크기 유효성 검사
if ($page < 1) $page = 1;
if ($page_size < 1) $page_size = 25;

// OFFSET 계산
$offset = ($page - 1) * $page_size;

// room_pid가 설정되어 있고 비어 있지 않은지 확인
if (isset($room_pid) && !empty($room_pid)) {
    try {
        // my_pid를 JSON 형식으로 인코딩
        $my_pid_json = json_encode($my_pid);

        // 채팅방 세부 정보를 가져오는 SQL 쿼리 준비 (image_path 필드를 추가)
        $sql = "SELECT pid, room_pid, sender_pid, msg, image_path, `create`, reader, status 
        FROM (
            SELECT * 
            FROM Chatting 
            WHERE room_pid = ? 
            AND JSON_CONTAINS(recipient_pid, ?)
            ORDER BY `create` DESC
            LIMIT ? OFFSET ?
        ) AS subquery
        ORDER BY `create` ASC";


        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $room_pid, $my_pid_json, $page_size, $offset);  // room_pid, my_pid_json, page_size, offset 바인딩
        $stmt->execute();
        $stmt->store_result();  // 결과 저장

        $response = array();

        if ($stmt->num_rows > 0) {
            // SQL 쿼리에서 선택한 필드와 bind_result()의 변수가 일치하도록 수정
            $stmt->bind_result($pid, $room_pid_result, $sender_pid, $msg, $image_path, $create, $reader, $status);

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

            // 참여자들의 PID를 모아서 한 번에 이름을 가져옴
            // mysqli_real_escape_string()에 연결 객체를 전달하기 위해 익명 함수 사용
            $escaped_pids = array_map(function($pid) use ($conn) {
                return mysqli_real_escape_string($conn, $pid);
            }, $participants_array);

            $participant_pids = implode("','", $escaped_pids);

            $sql_user = "SELECT pid, name FROM user WHERE pid IN ('$participant_pids')";
            $result_user = $conn->query($sql_user);
            while ($row_user = $result_user->fetch_assoc()) {
                $names[$row_user['pid']] = $row_user['name'];
            }

            // 채팅 메시지 및 기타 세부 정보를 가져옴
            while ($stmt->fetch()) {
                // sender_name을 names 배열에서 가져옴
                $sender_name = isset($names[$sender_pid]) ? $names[$sender_pid] : "Unknown";

                // reader 필드가 JSON 배열인 경우 reader 수 계산
                $reader_array = json_decode($reader, true);
                $count = is_array($reader_array) ? count($reader_array) : 0;

                // 이미지 경로가 있으면 이미지 URL을 생성
                $image_url = !empty($image_path) ? "http://49.247.32.169/NewProject/uploads/" . basename($image_path) : null;

                // 채팅방 정보를 배열로 준비
                $chatroom_info = array(
                    "pid" => $pid,
                    "room_pid" => $room_pid_result,
                    "sender_pid" => $sender_pid,
                    "sender_name" => $sender_name,  // 메시지 보낸 사람의 이름 추가
                    "msg" => $msg,
                    "image_url" => $image_url,  // 이미지 URL 추가
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
            $response["message"] = "No chat messages found for the given room_pid and page.";
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
