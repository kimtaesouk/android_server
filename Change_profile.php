<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once("db_connect.php");

// 사용자의 입력 데이터를 안전하게 처리합니다.
$pid = $_POST['pid'];
$name = isset($_POST['name']) ? $_POST['name'] : null;
$birth = isset($_POST['birth']) ? $_POST['birth'] : null;
$id = isset($_POST['id']) ? $_POST['id'] : null;
$pw = isset($_POST['pw']) ? $_POST['pw'] : null;

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 응답 생성
$response = array();

if ($name !== null && $birth === null && $id === null && $pw === null) {
    // name만 입력된 경우
    $sql = "UPDATE user SET name = ? WHERE pid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $name, $pid);
} elseif ($name === null && $birth !== null && $id === null  && $pw === null) {
    // birth만 입력된 경우
    $sql = "UPDATE user SET birth = ? WHERE pid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $birth, $pid);
} elseif ($name === null && $birth === null && $id !== null  && $pw === null) {
    // id만 입력된 경우
    $sql = "UPDATE user SET id = ? WHERE pid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $id, $pid);
}elseif($name === null && $birth === null && $id === null  && $pw !== null) {

    $sql = "UPDATE user SET pw = ? WHERE pid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $pw, $pid);

}else {
    // 둘 이상 입력된 경우 또는 아무것도 입력되지 않은 경우
    $response["success"] = false;
    $response["message"] = "Invalid fields to update";
    echo json_encode($response);
    $conn->close();
    exit();
}

// 쿼리 실행
if ($stmt->execute()) {
    $response["success"] = true;
} else {
    $response["success"] = false;
    $response["message"] = "Error updating record: " . $stmt->error;
}
$stmt->close();

// JSON 형식으로 응답 전송
echo json_encode($response);

// 데이터베이스 연결 종료
$conn->close();
?>
