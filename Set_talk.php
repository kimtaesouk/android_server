<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$my_pid = $_POST["my_pid"];
$friend_pids_string = $_POST["friend_pids"];
$message = $_POST["message"];
$isBlocked = $_POST["isBlocked"];

// 이미지 파일 처리
$image_file_path = null;  // 이미지 파일 경로를 저장할 변수를 초기화
$image_file_name = null;  // 이미지 파일명을 저장할 변수를 초기화
// 클라이언트로부터 이미지 파일이 전송되었는지, 오류 없이 업로드되었는지 확인
if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
    $image_file = $_FILES['image'];  // 업로드된 파일을 변수에 저장=
    // 이미지 저장 경로 설정 (서버 파일 시스템에 저장할 디렉토리 설정)
    $upload_dir = '/var/www/html/NewProject/uploads/';  // 이미지 파일이 저장될 서버 디렉토리
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);  // 디렉토리가 없으면 생성 (읽기, 쓰기, 실행 권한 설정)
    }
    // 파일 이름과 확장자를 처리
    $file_name = $image_file['name'];  // 클라이언트가 업로드한 파일의 원래 이름
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);  // 파일 이름에서 확장자 추출
    $image_file_name = uniqid() . '.' . $file_extension;  // 고유한 파일 이름 생성 (중복 방지)
    $image_file_path = $upload_dir . $image_file_name;  // 최종적으로 파일이 저장될 경로
    // 임시 저장된 파일을 설정된 위치로 이동 (실제 저장)
    if (!move_uploaded_file($image_file['tmp_name'], $image_file_path)) {
        // 파일 이동 실패 시 에러 로그를 남기고, 클라이언트에게 실패 응답을 반환
        error_log('Failed to move uploaded file: ' . $image_file['tmp_name']);
        echo json_encode(array('success' => false, 'message' => 'Image upload failed'));  // JSON 형식으로 실패 메시지 반환
        exit;  // 스크립트 종료
    }
}


$friend_pids_array = [];
if (!empty($friend_pids_string)) {
    $friend_pids_array = explode(",", $friend_pids_string);
}

// 전체 참가자 배열 생성 (내 PID 포함)
$participants_array = array_merge([$my_pid], $friend_pids_array);
$participants_json = json_encode($participants_array);

$response = array();

try {
    // 채팅방이 있는지 확인하는 SQL 구문
    $sql = "SELECT pid FROM ChattingRoom WHERE JSON_CONTAINS(Participants, ?) AND JSON_LENGTH(Participants) = ?";
    $stmt = $conn->prepare($sql);
    $participants_count = count($participants_array);
    $stmt->bind_param("si", $participants_json, $participants_count);
    $stmt->execute();
    $stmt->bind_result($chattingroom_pid);
    $stmt->fetch();
    $stmt->close();
    
    if (count($friend_pids_array) === 1) {
        // friend_pids_json의 개수가 1일 때 실행
        $sql = "SELECT friends_block FROM user WHERE pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $friend_pids_array[0]);  // friends_block을 가져올 대상의 pid는 친구 pid
        $stmt->execute();
        $stmt->bind_result($friends_block);
        $stmt->fetch();
        $stmt->close();
    
        // friends_block이 JSON 형식이면 my_pid가 존재하는지 확인
        $friends_block_array = json_decode($friends_block, true);  // JSON을 PHP 배열로 변환
    
        if (isset($friends_block_array[$my_pid])) {
            // my_pid가 friends_block에 존재하면 participants_array에서 my_pid를 제거
            $participants_array = array_diff($participants_array, [$friend_pids_string]);
    
            // 다시 JSON으로 변환
            $participants_json = json_encode(array_values($participants_array));
        }
    }

    if (!empty($chattingroom_pid)) {
        // 채팅 메시지 저장
        $participants_json_encoded = json_encode($participants_array);
        $friend_pids_json = json_encode($friend_pids_array);

        // 채팅 메시지 저장
        $sql = "INSERT INTO Chatting (room_pid, sender_pid, recipient_pid, msg, image_path, `create`, reader) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($sql);

        // 변수를 참조로 전달
        $stmt->bind_param("ssssss", $chattingroom_pid, $my_pid, $participants_json_encoded, $message, $image_file_path, $friend_pids_json);
        
        // 이미지 업로드가 성공한 경우에만 경로를 설정
        $image_base_url = 'http://49.247.32.169/NewProject/uploads/';
        $image_file_name = isset($image_file_name) ? $image_file_name : '';  // 이미지 파일명이 없을 경우 빈 문자열 처리

        if ($stmt->execute()) {
            $response["success"] = true;
            $response["message"] = "Message and image sent successfully.";
            
            // 이미지 경로를 업로드된 경우에만 설정
            if (!empty($image_file_name)) {
                $response["image_path"] = $image_base_url . $image_file_name;
            } else {
                $response["image_path"] = null;
            }
        } else {
            throw new Exception("Message saving failed.");
        }
        $stmt->close();

    } else {
        // 새로운 채팅방 생성 및 메시지 저장 (새로운 로직을 추가)
        // ...
    }
} catch (Exception $e) {
    $response = array("success" => false, "message" => "Error: " . $e->getMessage());
}

// JSON 형식으로 응답 전송
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// 데이터베이스 연결 종료
$conn->close();
?>
