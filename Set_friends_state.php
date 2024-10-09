<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once("db_connect.php");

// 사용자의 입력 데이터를 안전하게 처리합니다.
$my_pid = $_POST['my_pid'];
$f_pid = $_POST['f_pid'];
$state = $_POST['state'];

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

// JSON 배열로 디코드 (디코딩 후 PHP 배열로 변환)
$friends_array = json_decode($current_friends ?: '[]', true);
$friends_hide_array = json_decode($current_friends_hide ?: '[]', true);
$friends_block_array = json_decode($current_friends_block ?: '[]', true);

// 상태에 따라 친구 목록 업데이트
if ($state == 'isHide') {
    // state가 'isHide'인 경우
    if (!in_array($f_pid, $friends_hide_array)) {
        // f_pid가 friends_hide_array에 없다면 추가
        $friends_hide_array[] = $f_pid;
    }
    // friends_array에서 f_pid 제거
    $friends_array = array_diff($friends_array, [$f_pid]);
}elseif ($state == 'isBlock') {
    // state가 'isBlock'인 경우
    if (!isset($friends_block_array[$f_pid])) {
        // f_pid가 friends_block_array에 없다면 추가
        $friends_block_array[$f_pid] = date('Y-m-d H:i:s'); // 현재 시간을 함께 저장
    }
    // friends_array에서 f_pid 제거
    $friends_array = array_diff($friends_array, [$f_pid]);

    // friends_hide_array에서 f_pid 제거
    $friends_hide_array = array_diff($friends_hide_array, [$f_pid]);
}
 elseif ($state == 'unblock') {
    // state가 'unblock'인 경우
    // friends_block_array에서 f_pid를 제거
    if (isset($friends_block_array[$f_pid])) {
        // friends_block_array에서 f_pid를 키로 가지는 항목을 제거
        unset($friends_block_array[$f_pid]);
    }
} elseif ($state == 'delete') {
    // state가 'delete'인 경우
    // friends_array에서 f_pid를 제거
    $friends_array = array_diff($friends_array, [$f_pid]);
    // friends_hide_array에서 f_pid를 제거
    $friends_hide_array = array_diff($friends_hide_array, [$f_pid]);
    // friends_block_array에서 f_pid를 제거
    if (isset($friends_block_array[$f_pid])) {
        // friends_block_array에서 f_pid를 키로 가지는 항목을 제거
        unset($friends_block_array[$f_pid]);
    }
}elseif($state == "isReturn"){
    // state가 'isReturn'인 경우
    if (!in_array($f_pid, $friends_array)) {
        // f_pid가 friends_block_array에 없다면 추가
        $friends_array[] = $f_pid;
    }
    // friends_array에서 f_pid 제거
    $friends_hide_array = array_diff($friends_hide_array, [$f_pid]);
}

// 업데이트된 배열을 JSON 형식으로 인코드
$new_friends = json_encode($friends_array);
$new_friends_hide = json_encode($friends_hide_array);
$new_friends_block = json_encode($friends_block_array);

// user 테이블의 해당 pid에 대한 friends, friends_hide, friends_block 필드를 업데이트합니다
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
