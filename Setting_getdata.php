<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
// 데이터베이스 연결 코드를 포함합니다.
require_once("db_connect.php");

// POST 데이터 가져오기
$pid = $_POST["pid"];

// pid가 있는지 확인
if (isset($pid)) {
    try {
        // SQL 쿼리 작성 (테이블 이름과 필드 이름을 실제 데이터베이스에 맞게 수정해야 합니다)
        $sql = "SELECT email,name, pf_im, birth, id FROM user WHERE pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $pid);

        // 쿼리 실행
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($email, $name, $pf_im, $birth, $id);
        $stmt->fetch();

        // 응답 생성
        $response = array();

        if ($stmt->num_rows == 1) {
            // 조회 성공
            $response["success"] = true;
            $response["message"] = "Data retrieval successful.";
            $response["email"] = $email; // 사용자의 프로필 이미지 반환
            $response["name"] = $name; // 사용자의 이름 반환
            $response["birth"] = $birth; // 사용자의 프로필 이미지 반환
            $response["pf_im"] = $pf_im; // 사용자의 프로필 이미지 반환
            $response["id"] = $id; // 사용자의 프로필 이미지 반환
        } else {
            // 조회 실패
            $response["success"] = false;
            $response["message"] = "Invalid pid.";
        }

        $stmt->close();
    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }
} else {
    $response = array("success" => false, "message" => "pid is missing.");
}

// JSON 형식으로 응답 전송
echo json_encode($response);

// 데이터베이스 연결 종료
$conn->close();
?>
