<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once("db_connect.php");

// 사용자의 입력 데이터를 안전하게 처리합니다.
$my_pid = $_POST['my_pid'];
$f_pid = $_POST['f_pid'];

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 현재 friends, friends_hide, friends_block 필드 값을 가져옵니다
$sql_select = "SELECT friends, friends_hide, friends_block FROM user WHERE pid = ?";
$stmt_select = $conn->prepare($sql_select);
$stmt_select->bind_param("s", $my_pid);
$stmt_select->execute();
$stmt_select->bind_result($current_friends, $current_friends_hide, $current_friends_block);
$stmt_select->fetch();
$stmt_select->close();

// JSON 배열로 디코드
$friends_array = json_decode($current_friends, true);
$friends_hide_array = json_decode($current_friends_hide, true);
$friends_block_array = json_decode($current_friends_block, true);

// JSON 디코딩 오류나 비어있는 배열 처리
if (json_last_error() !== JSON_ERROR_NONE || !is_array($friends_array)) {
    $friends_array = array();
}
if (json_last_error() !== JSON_ERROR_NONE || !is_array($friends_hide_array)) {
    $friends_hide_array = array();
}
if (json_last_error() !== JSON_ERROR_NONE || !is_array($friends_block_array)) {
    $friends_block_array = array();
}

// 상태 설정
$state = 'add'; // 기본 상태는 추가
if (in_array($f_pid, $friends_hide_array)) {
    $state = 'isHide';
} elseif (in_array($f_pid, $friends_block_array)) {
    $state = 'isBlock';
}

// 상태에 따라 처리
if ($state == 'isHide') {
    // friends_hide에 f_pid를 추가하고 friends에서 제거
    if (!in_array($f_pid, $friends_hide_array)) {
        $friends_hide_array[] = $f_pid;
    }
    $friends_array = array_values(array_diff($friends_array, [$f_pid])); // 배열 재정렬
} elseif ($state == 'isBlock') {
    // friends_block에 f_pid를 추가하고 friends에서 제거
    if (!in_array($f_pid, $friends_block_array)) {
        $friends_block_array[] = $f_pid;
    }
    $friends_array = array_values(array_diff($friends_array, [$f_pid])); // 배열 재정렬
} elseif ($state == 'add') {
    // friends에 f_pid를 추가
    if (!in_array($f_pid, $friends_array)) {
        $friends_array[] = $f_pid;
    }
}

// JSON 형식으로 인코드
$new_friends = json_encode(array_values($friends_array)); // 배열 재정렬
$new_friends_hide = json_encode(array_values($friends_hide_array)); // 배열 재정렬
$new_friends_block = json_encode(array_values($friends_block_array)); // 배열 재정렬

// friends, friends_hide, friends_block 필드를 업데이트합니다
$sql_update = "UPDATE user SET friends = ?, friends_hide = ?, friends_block = ? WHERE pid = ?";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param("ssss", $new_friends, $new_friends_hide, $new_friends_block, $my_pid);
$success = $stmt_update->execute();
$stmt_update->close();

// 응답 생성
$response = array();
$response["success"] = $success;

// JSON 형식으로 응답 전송
echo json_encode($response);

// 데이터베이스 연결 종료
$conn->close();
?>
