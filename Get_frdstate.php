<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once("db_connect.php");

// POST 데이터 가져오기
$mypid = $_POST['pid'];
$friend_pid = $_POST['friend_pid'];

$response = array();

if (isset($mypid) && isset($friend_pid)) {
    try {
        $sql = "SELECT friends, friends_hide, friends_block, (SELECT name FROM user WHERE pid = ?) FROM user WHERE pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $friend_pid, $mypid);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            // 데이터 바인딩
            $stmt->bind_result($friends_json, $friends_hide_json, $friends_block_json, $name);
            $stmt->fetch();

            // JSON 디코딩
            $friends = $friends_json !== null ? json_decode($friends_json, true) : [];
            $friends_hide = $friends_hide_json !== null ? json_decode($friends_hide_json, true) : [];
            $friends_block = $friends_block_json !== null ? json_decode($friends_block_json, true) : [];

            // 상태 확인
            if (array_key_exists($friend_pid, $friends_block)) {
                $response["success"] = true;
                $response["friend"] = "isBlock";
            } elseif (in_array($friend_pid, $friends_hide)) {
                $response["success"] = true;
                $response["friend"] = "isHide";
            } elseif (in_array($friend_pid, $friends)) {
                $response["success"] = true;
                $response["friend"] = "isFriend";
            } else {
                $response["success"] = true;
                $response["friend"] = "notFriend";
            }

            // 공통 정보 추가
            $response["name"] = $name;
        } else {
            // 자신의 pid가 유효하지 않은 경우
            $response["success"] = false;
            $response["message"] = "Invalid pid.";
            $response["friend"] = "false";
            $response["name"] = null;
        }
            
    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }

} else {
    $response = array("success" => false, "message" => "pid is missing.");
}

// JSON 형식으로 응답 전송
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// 데이터베이스 연결 종료
$conn->close();
?>
