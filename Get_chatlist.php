<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$room_pid = $_POST["room_pid"];

// Check if room_pid is set and not empty
if (isset($room_pid) && !empty($room_pid)) {
    try {
        // Prepare the SQL query to fetch chat room details
        $sql = "SELECT pid, room_pid, sender_pid, msg, `create`, reader, status 
                FROM Chatting 
                WHERE room_pid = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $room_pid);
        $stmt->execute();
        $stmt->store_result();

        $response = array();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($pid, $room_pid, $sender_pid, $msg, $create, $reader, $status);

            $rooms = array();

            while ($stmt->fetch()) {
                // Fetch the sender's name from the user table
                $sql_user = "SELECT name FROM user WHERE pid = ?";
                $stmt_user = $conn->prepare($sql_user);
                $stmt_user->bind_param("s", $sender_pid);
                $stmt_user->execute();
                $stmt_user->store_result();
                $stmt_user->bind_result($name);
                $stmt_user->fetch();
                $stmt_user->close();

                // Count the number of readers (assuming `reader` is a JSON array)
                $reader_array = json_decode($reader, true);
                $count = is_array($reader_array) ? count($reader_array) : 0;

                // Prepare chat room info
                $chatroom_info = array(
                    "pid" => $pid,
                    "room_pid" => $room_pid,
                    "sender_pid" => $sender_pid,
                    "sender_name" => $name,
                    "msg" => $msg,
                    "create" => $create,
                    "count" => $count,
                    "status" => $status
                );
                $rooms[] = $chatroom_info;
            }

            $response["success"] = true;
            $response["rooms"] = $rooms;
        } else {
            $response["success"] = false;
            $response["message"] = "No chat rooms found for the given room_pid.";
        }

        $stmt->close();

    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }
} else {
    $response = array("success" => false, "message" => "room_pid is missing.");
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();
?>
