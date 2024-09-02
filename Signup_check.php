<?php
// 데이터베이스 연결 코드를 포함합니다.
require_once("db_connect.php");

// POST 데이터 가져오기
$email = $_POST["email"];

// SQL 쿼리 작성 (테이블 이름과 필드 이름을 실제 데이터베이스에 맞게 수정해야 합니다)
$sql = "SELECT * FROM user WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);

// 쿼리 실행
$stmt->execute();
$stmt->store_result();
$count = $stmt->num_rows;
$stmt->close();

// 응답 생성
$response = array();

if ($count == 0) {
    // 중복된 이메일이 없는 경우
    $response["success"] = true;
} else {
    // 중복된 이메일이 있는 경우
    $response["success"] = false;
}

// JSON 형식으로 응답 전송
echo json_encode($response);

// 데이터베이스 연결 종료
$conn->close();
?>
