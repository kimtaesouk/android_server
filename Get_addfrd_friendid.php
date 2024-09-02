<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
// 데이터베이스 연결 코드를 포함합니다.
require_once("db_connect.php");

// POST 데이터 가져오기
$id = $_POST["id"];
$my_pid = $_POST["pid"];

// 응답 생성
$response = array();

// pid와 id가 있는지 확인
if (isset($id) && isset($my_pid)) {
    try {
        // 먼저 allow_add_id 값을 확인하는 쿼리 작성
        $sql = "SELECT allow_add_id FROM user WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($allow_add_id);
        $stmt->fetch();

        // allow_add_id가 1인지 확인
        if ($stmt->num_rows == 1 && $allow_add_id == 1) {
            $stmt->close();

            // 친구의 이름, 프로필 사진, pid를 가져오기 위한 SQL 쿼리 작성
            $sql = "SELECT name, pf_im, pid FROM user WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($name, $pf_im, $f_pid);
            $stmt->fetch();

            if ($stmt->num_rows == 1) {
                $stmt->close();

                // 자신의 friends, friends_hide, friends_block 열에서 친구의 pid를 확인하기 위한 SQL 쿼리 작성
                $sql = "SELECT friends, friends_hide, friends_block FROM user WHERE pid = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $my_pid);
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($friends_json, $friends_hide_json, $friends_block_json);
                $stmt->fetch();

                if ($stmt->num_rows == 1) {
                    // JSON 디코딩
                    $friends = $friends_json !== null ? json_decode($friends_json, true) : [];
                    $friends_hide = $friends_hide_json !== null ? json_decode($friends_hide_json, true) : [];
                    $friends_block = $friends_block_json !== null ? json_decode($friends_block_json, true) : [];

                    // 상태 확인
                    if (array_key_exists($f_pid, $friends_block)) {
                        $response["success"] = true;
                        $response["friend"] = "isBlock";
                    } elseif (in_array($f_pid, $friends_hide)) {
                        $response["success"] = true;
                        $response["friend"] = "isHide";
                    } elseif (in_array($f_pid, $friends)) {
                        $response["success"] = true;
                        $response["friend"] = "isfrind";
                    } else {
                        $response["success"] = true;
                        $response["friend"] = "notfriend";
                    }

                    // 공통 정보 추가
                    $response["name"] = $name;
                    $response["pf_im"] = $pf_im;
                    $response["f_pid"] = $f_pid;
                } else {
                    // 자신의 pid가 유효하지 않은 경우
                    $response["success"] = false;
                    $response["message"] = "Invalid pid.";
                    $response["friend"] = "false";
                    $response["name"] = null;
                    $response["pf_im"] = null;
                    $response["f_pid"] = null;
                }

                $stmt->close();
            } else {
                // 사용자의 프로필 정보가 없는 경우
                $response["success"] = false;
                $response["message"] = "Invalid id.";
                $response["friend"] = "false";
                $response["name"] = null;
                $response["pf_im"] = null;
                $response["f_pid"] = null;
            }
        } else {
            $response["success"] = true;
            $response["message"] = "Not allowed to retrieve friend data.";
            $response["friend"] = "not_allow";
            $response["name"] = null;
            $response["pf_im"] = null;
            $response["f_pid"] = null;
        }
    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }
} else {
    $response = array("success" => false, "message" => "id or pid is missing.");
}

// JSON 형식으로 응답 전송
echo json_encode($response);

// 데이터베이스 연결 종료
$conn->close();
?>
