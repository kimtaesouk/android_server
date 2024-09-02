<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once("db_connect.php");

// POST 데이터 가져오기
$pid = $_POST['pid'];
$status = isset($_POST['status']) ? $_POST['status'] : null;

$response = array();

if (isset($pid)) {
    try {
        if ($status === 'hide' || $status === 'block') {
            // 상태에 따라 적절한 컬럼에서 데이터 가져오기
            $column = $status === 'hide' ? 'friends_hide' : 'friends_block';
            $sql = "SELECT $column FROM user WHERE pid = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $pid);
            
            // 쿼리 실행
            $stmt->execute();
            $stmt->bind_result($friends_data);
            $stmt->fetch();
            $stmt->close();

            if ($friends_data) {
                // JSON 배열 파싱
                $friends_array = json_decode($friends_data, true);

                // 디버그: JSON 디코딩 오류 확인
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    $friends_array = [];
                }

                $friends_info = array();

                if (!empty($friends_array) && is_array($friends_array)) {
                    // friends의 name, pf_im 가져오기
                    $placeholders = implode(',', array_fill(0, count($friends_array), '?'));
                    $types = str_repeat('s', count($friends_array));
                    $sql_friends = "SELECT name, pf_im, pid, birth FROM user WHERE pid IN ($placeholders)";
                    $stmt_friends = $conn->prepare($sql_friends);

                    // 가변 인자 배열을 사용하여 bind_param 호출
                    $stmt_friends->bind_param($types, ...$friends_array);

                    $stmt_friends->execute();
                    $stmt_friends->bind_result($f_name, $f_pf_im, $f_pid, $f_birth);
                    
                    while ($stmt_friends->fetch()) {
                        $friends_info[] = array(
                            "pid" => $f_pid,
                            "name" => $f_name,
                            "birth" => $f_birth,
                            "pf_im" => $f_pf_im
                        );
                    }

                    $stmt_friends->close();
                }

                // 응답 생성
                $response["success"] = true;
                $response["user"] = array(
                    "friends" => $friends_info
                );
            } else {
                $response["success"] = false;
                $response["message"] = "No friends found for the given status.";
            }
        } else {
            // 상태가 'hide' 또는 'block'이 아닌 경우
            $response["success"] = false;
            $response["message"] = "Invalid status.";
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
