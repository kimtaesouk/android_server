<?php
// 데이터베이스 연결 정보
$host = "49.247.32.169"; // 데이터베이스 호스트
$username = "kim"; // 데이터베이스 사용자 이름
$password = "sonnaeun0513"; // 데이터베이스 암호
$database = "NewProject"; // 데이터베이스 이름

// 데이터베이스 연결
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>