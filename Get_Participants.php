<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$pid = $_POST["pid"] ?? null; // 단일 pid 값
$friend_pids_json = $_POST["friend_pids"] ?? null; // friend_pid 리스트 (JSON 문자열)
$chattingroom_pid = $_POST["chattingroom_pid"] ?? null; // friend_pid 리스트 (JSON 문자열)

// JSON 문자열을 배열로 변환
$friend_pids = $friend_pids_json ? json_decode($friend_pids_json, true) : [];

$response = array();

// Exiter 값을 가져와 JSON으로 변환
$sql = "SELECT Exiter FROM ChattingRoom WHERE pid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $chattingroom_pid);
$stmt->execute();
$stmt->bind_result($Exiter);
$stmt->fetch();
$stmt->close();

$exiter_array = json_decode($Exiter, true); // Exiter를 JSON 배열로 변환

// Exiter와 pid, friend_pids 비교
if (!in_array($pid, $exiter_array)) {
    // Exiter에 pid나 friend_pids가 포함되지 않을 때 쿼리 수행
    try {
        // 단일 pid를 사용하여 데이터 가져오기
        $sql = "SELECT name, pid FROM user WHERE pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $pid);

        $stmt->execute();
        $stmt->bind_result($name, $pid);

        while ($stmt->fetch()) {
            $response['user'] = array(
                "pid" => $pid,
                "name" => $name
            );
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(array("success" => false, "message" => "Error: " . $e->getMessage()), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!array_intersect($friend_pids, $exiter_array)) {
    try {
        // friend_pid 리스트를 사용하여 데이터 가져오기
        $placeholders = implode(',', array_fill(0, count($friend_pids), '?'));
        $types = str_repeat('s', count($friend_pids));
        $sql_friends = "SELECT name, pid FROM user WHERE pid IN ($placeholders)";
        $stmt_friends = $conn->prepare($sql_friends);

        $params = [];
        foreach ($friend_pids as $key => $value) {
            $params[] = &$friend_pids[$key];
        }

        array_unshift($params, $types);
        call_user_func_array([$stmt_friends, 'bind_param'], $params);

        $stmt_friends->execute();
        $stmt_friends->bind_result($f_name, $f_pid); // Corrected to match the SELECT statement

        while ($stmt_friends->fetch()) {
            $response['friends'][] = array(
                "pid" => $f_pid,
                "name" => $f_name
            );
        }

        $stmt_friends->close();
    } catch (Exception $e) {
        echo json_encode(array("success" => false, "message" => "Error: " . $e->getMessage()), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

echo json_encode(array("success" => true, "data" => $response), JSON_UNESCAPED_UNICODE);

$conn->close();

?>
