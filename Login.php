<?php
// 데이터베이스 연결 코드를 포함합니다.
require_once("db_connect.php");

// POST 데이터 가져오기
$email = $_POST["email"];
$pw = $_POST["pw"];

// 이메일과 비밀번호 입력 값이 있는지 확인
if (empty($email) || empty($pw)) {
    $response = array("success" => false, "message" => "Email and password are required.");
    echo json_encode($response);
    exit();
}

try {
    // SQL 쿼리 작성 (테이블 이름과 필드 이름을 실제 데이터베이스에 맞게 수정해야 합니다)
    $sql = "SELECT pw, pid FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);

    // 쿼리 실행
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($stored_pw, $pid);
    $stmt->fetch();

    // 응답 생성
    $response = array();

    if ($stmt->num_rows == 1 && $pw === $stored_pw) {
        // 로그인 성공
        $response["success"] = true;
        $response["message"] = "Login successful.";
        $response["pid"] = $pid; // 사용자의 pid 반환
    } else {
        // 로그인 실패
        $response["success"] = false;
        $response["message"] = "Invalid email or password.";
    }

    $stmt->close();
} catch (Exception $e) {
    $response = array("success" => false, "message" => "Error: " . $e->getMessage());
}

// JSON 형식으로 응답 전송
echo json_encode($response);

// 데이터베이스 연결 종료
$conn->close();
?>
