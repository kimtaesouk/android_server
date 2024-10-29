<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$chat_pid = $_POST["chat_pid"];
$my_pid = $_POST["my_pid"];
$delete_option = $_POST["delete_option"];

if (isset($chat_pid) && isset($my_pid)) {
    try {
        // chat_pid가 일치하는 행을 조회
        $sql = "SELECT sender_pid, recipient_pid, msg, reader FROM Chatting WHERE pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $chat_pid);
        $stmt->execute();
        $stmt->bind_result($sender_pid, $recipient_pid, $msg, $reader);
        $stmt->fetch();
        $stmt->close();  // SELECT 쿼리가 완료된 후 반드시 close() 호출

        // recipient_pid를 JSON으로 변환
        $recipient_array = json_decode($recipient_pid, true);

        // reader가 비어 있거나 null이면 빈 배열로 처리
        if (empty($reader)) {
            $reader_array = [];
        } else {
            $reader_array = json_decode($reader, true);
            // JSON 변환에 실패하면 예외 처리
            if ($reader_array === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid reader data: " . json_last_error_msg());
            }
        }

        // recipient_pid가 올바른 JSON인지 확인
        if ($recipient_array === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid recipient data: " . json_last_error_msg());
        }

        // reader의 개수 확인
        $reader_count = count($reader_array);

        // 1. reader가 0명이면 recipient_pid에서 my_pid 제거
        if ($reader_count === 0 || $delete_option === "only") {
            // recipient_array에서 my_pid 제거
            $recipient_array = array_diff($recipient_array, [$my_pid]);
            $recipient_array = array_values($recipient_array); // 배열 인덱스 재정렬

            // recipient_pid를 업데이트
            $new_recipient_pid = json_encode($recipient_array);
            $sql_update = "UPDATE Chatting SET recipient_pid = ? WHERE pid = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ss", $new_recipient_pid, $chat_pid);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // 2. reader의 개수가 recipient_pid의 개수 - 1과 같으면 메시지 삭제
            $recipient_count = count($recipient_array);
            if ($reader_count === ($recipient_count - 1)) {
                // 메시지를 "삭제된 메시지입니다"로 업데이트
                $new_msg = "삭제된 메시지입니다";
                $sql_update_msg = "UPDATE Chatting SET msg = ? WHERE pid = ?";
                $stmt_update_msg = $conn->prepare($sql_update_msg);
                $stmt_update_msg->bind_param("ss", $new_msg, $chat_pid);
                $stmt_update_msg->execute();
                $stmt_update_msg->close();
            }
        }
        // 응답 생성
        $response = array("success" => true);

    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }
} else {
    $response = array("success" => false, "message" => "chat_pid or my_pid is missing.");
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();

?>
