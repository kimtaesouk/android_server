<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("db_connect.php");

$pid = $_POST["pid"];

if (isset($pid)) {
    try {
        $sql = "SELECT name, pf_im, friends, friends_hide FROM user WHERE pid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $stmt->bind_result($name, $pf_im, $friends, $friends_hide);
        $stmt->fetch();
        $stmt->close();

        $response = array();

        if ($name) {
            error_log("Friends field value: " . $friends);

            $friends_array = json_decode($friends, true);
            $friends_hide_array = json_decode($friends_hide, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                $friends_array = [];
            }

            $friends_count = is_array($friends_array) ? count($friends_array) : 0;

            $friends_info = array();
            $friends_hide_info = array();

            if (!empty($friends_array) && is_array($friends_array)) {
                $placeholders = implode(',', array_fill(0, count($friends_array), '?'));
                $types = str_repeat('s', count($friends_array));
                $sql_friends = "SELECT name, pf_im, pid, birth FROM user WHERE pid IN ($placeholders)";
                $stmt_friends = $conn->prepare($sql_friends);

                $params = [];
                foreach ($friends_array as $key => $value) {
                    $params[] = &$friends_array[$key];
                }

                array_unshift($params, $types);
                call_user_func_array([$stmt_friends, 'bind_param'], $params);

                $stmt_friends->execute();
                $stmt_friends->bind_result($f_name, $f_pf_im, $f_pid, $f_birth);

                while ($stmt_friends->fetch()) {
                    $friends_info[] = array(
                        "pid" => $f_pid,
                        "name" => $f_name,
                        "birth" => $f_birth,
                        "pf_im" => $f_pf_im
                    );
                }
                $stmt_friends->close();
            }
            if(!empty($friends_hide_array) && is_array($friends_hide_array)){
                $placeholders = implode(',', array_fill(0, count($friends_hide_array), '?'));
                $types = str_repeat('s', count($friends_hide_array));
                $sql_friends = "SELECT name, pf_im, pid, birth FROM user WHERE pid IN ($placeholders)";
                $stmt_friends = $conn->prepare($sql_friends);

                $params = [];
                foreach ($friends_hide_array as $key => $value) {
                    $params[] = &$friends_hide_array[$key];
                }

                array_unshift($params, $types);
                call_user_func_array([$stmt_friends, 'bind_param'], $params);

                $stmt_friends->execute();
                $stmt_friends->bind_result($f_name, $f_pf_im, $f_pid, $f_birth);

                while ($stmt_friends->fetch()) {
                    $friends_hide_info[] = array(
                        "pid" => $f_pid,
                        "name" => $f_name,
                        "birth" => $f_birth,
                        "pf_im" => $f_pf_im
                    );
                }
                $stmt_friends->close();
            }

            $response["success"] = true;
            $response["user"] = array(
                "name" => $name,
                "pf_im" => $pf_im,
                "friends_count" => $friends_count,
                "friends_hide" => $friends_hide_info,
                "friends" => $friends_info

            );
        } else {
            $response["success"] = false;
            $response["message"] = "Invalid pid.";
        }
    } catch (Exception $e) {
        $response = array("success" => false, "message" => "Error: " . $e->getMessage());
    }
} else {
    $response = array("success" => false, "message" => "pid is missing.");
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();

?>
