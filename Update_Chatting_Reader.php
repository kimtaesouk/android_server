<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$room_pid = $_POST["room_pid"];
$pid = $_POST["pid"];

if (isset($room_pid) && isset($pid)) {
    try {
        // room_pid가 일치하는 모든 행을 조회
        $sql = "SELECT room_pid, reader FROM Chatting WHERE room_pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $room_pid);
        $stmt->execute();
        $stmt->bind_result($room_pid, $current_readers);
        
        // 결과를 모두 처리한 후 배열에 저장
        $rows = [];
        while ($stmt->fetch()) {
            $rows[] = ['room_pid' => $room_pid, 'reader' => $current_readers];
        }
        $stmt->close();  // SELECT 쿼리가 완료된 후 반드시 close() 호출

        // 각 행의 reader 필드에서 pid를 삭제한 후 업데이트
        foreach ($rows as $row) {
            // 현재 행의 reader 데이터를 JSON 배열로 변환
            $readers_array = json_decode($row['reader'] ?: '[]', true);

            // pid가 있는 경우 배열에서 해당 pid만 제거
            if (in_array($pid, $readers_array)) {
                $readers_array = array_diff($readers_array, [$pid]);
                $readers_array = array_values($readers_array); // 배열 인덱스를 재정렬
                
                // 새로운 reader 값을 JSON으로 인코딩
                $new_readers = json_encode($readers_array);

                // reader 필드를 업데이트 (특정 room_pid에 대해)
                $sql_update = "UPDATE Chatting SET reader = ? WHERE room_pid = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("ss", $new_readers, $row['room_pid']);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }

        // 응답 생성
        $response = array();
        $response["success"] = true;

    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }
} else {
    $response = array("success" => false, "message" => "room_pid or pid is missing.");
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();

?>
