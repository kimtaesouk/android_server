<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 데이터베이스 연결 코드를 포함합니다.
require_once("db_connect.php");

// POST 데이터 가져오기
$pid = $_POST["pid"];
$isChecked = $_POST["isChecked"];

// pid와 isChecked가 있는지 확인
if (isset($pid) && isset($isChecked)) {
    try {
        // isChecked 값에 따라 allow_add_id 값 설정
        $allow_add_id = $isChecked === 'true' ? 1 : 0;

        // SQL 쿼리 작성 (테이블 이름과 필드 이름을 실제 데이터베이스에 맞게 수정해야 합니다)
        $sql = "UPDATE user SET allow_add_id = ? WHERE pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $allow_add_id, $pid);

        // 쿼리 실행
        if ($stmt->execute()) {
            // 업데이트 성공
            $response = array(
                "success" => true,
                "message" => "Update successful.",
            );
        } else {
            // 업데이트 실패
            $response = array(
                "success" => false,
                "message" => "Update failed."
            );
        }

        $stmt->close();
    } catch (Exception $e) {
        $response = array(
            "success" => false,
            "message" => "Error: " . $e->getMessage()
        );
    }
} else {
    $response = array(
        "success" => false,
        "message" => "pid or isChecked is missing."
    );
}

// JSON 형식으로 응답 전송
echo json_encode($response);

// 데이터베이스 연결 종료
$conn->close();

?>
