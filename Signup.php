<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once("db_connect.php");

// 사용자의 입력 데이터를 안전하게 처리합니다.
$email = $_POST['email'];
$pw = $_POST['pw'];
$name = $_POST['name'];
$birth = $_POST['birth'];

// 데이터베이스 연결 확인
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 중복된 이메일 확인 쿼리
$sql_check = "SELECT * FROM user WHERE email = ?";
$stmt_check = $conn->prepare($sql_check);
if (!$stmt_check) {
    die("Prepare failed: " . $conn->error);
}
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$stmt_check->store_result();
$count = $stmt_check->num_rows;
$stmt_check->close();

// 응답 생성
$response = array();

if ($count > 0) {
    // 중복된 이메일이 있는 경우
    $response["success"] = false;
    $response["message"] = "Email already exists";
} else {
    // 중복된 이메일이 없는 경우, 데이터 삽입
    $sql_insert = "INSERT INTO user (email, pw, name, birth) VALUES (?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    if (!$stmt_insert) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt_insert->bind_param("ssss", $email, $pw, $name, $birth);
    $result = $stmt_insert->execute();
    $stmt_insert->close();

    if ($result) {
        // 데이터 삽입 성공
        $response["success"] = true;
    } else {
        // 데이터 삽입 실패
        $response["success"] = false;
        $response["message"] = "Insert failed: " . $conn->error;
    }
}

echo json_encode($response);

$conn->close();
?>
