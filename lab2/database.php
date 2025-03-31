<?php
/* database operations */
/*
 * table structure
 * - radcheck: store user credentials
 * - radusergroup: store user group
 * - radreply: store user limitations
 * - radacct: store user session information
 */

// return mysqli connection
function create_connection($host, $user, $password, $db)
{
    // create connection
    $conn = new mysqli($host, $user, $password, $db);

    // check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// return true if user added successfully
function add_user($conn, $username, $password, $traffic_limit, $session_limit)
{
    $query_stream = [
        // add new user
        "INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'User-Password', ':=', '$password')",
        // add to user group
        "INSERT INTO radusergroup (username, groupname) VALUES ('$username', 'user')",
        // add limitations
        "INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', 'Max-Traffic', ':=', '$traffic_limit')",
        "INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', 'Max-Session', ':=', '$session_limit')",
        // init usage
        "INSERT INTO radacct (username, AcctSessionTime, AcctInputOctets, AcctOutputOctets) VALUES ('$username', 0, 0, 0)",
    ];

    foreach ($query_stream as $query) {
        $conn->query($query);
    }

    return true;
}

// return true if user removed successfully
function remove_user($conn, $username)
{
    $query_stream = [
        // remove user
        "DELETE FROM radcheck WHERE username = '$username'",
        // remove from user group
        "DELETE FROM radusergroup WHERE username = '$username'",
        // remove limitations
        "DELETE FROM radreply WHERE username = '$username'",
        // remove usage
        "DELETE FROM radacct WHERE username = '$username'",
    ];

    foreach ($query_stream as $query) {
        $conn->query($query);
    }

    return true;
}

// return array of usernames or null
function get_all_username($conn)
{
    $query = "SELECT username FROM radcheck";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $usernames = array();
        while ($row = $result->fetch_assoc()) {
            $usernames[] = $row['username'];
        }
        return $usernames;
    } else {
        return null;
    }
}

// return boolean
function is_user_exists($conn, $username)
{
    $query = "SELECT * FROM radcheck WHERE username = '$username'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}

// return password or null
function get_user_password($conn, $username)
{
    $query = "SELECT * FROM radcheck WHERE username = '$username'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['value'];
    } else {
        return null;
    }
}

// return limit: 'traffic' or 'session'
function get_user_limit($conn, $username, $type)
{
    switch ($type) {
        case 'traffic':
            $attribute = 'Max-Traffic';
            break;
        case 'session':
            $attribute = 'Max-Session';
            break;
        default:
            return null;
    }

    $query = "SELECT * FROM radreply WHERE username = '$username' AND attribute = '$attribute'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['value'];
    } else {
        return null;
    }
}

// return usage: 'traffic' or 'session'
function get_user_usage($conn, $username, $type)
{
    switch ($type) {
        case 'traffic':
            $attribute = 'acctinputoctets + acctoutputoctets';
            break;
        case 'session':
            $attribute = 'acctsessiontime';
            break;
        default:
            return null;
    }

    $query = "SELECT SUM($attribute) AS 'usage' FROM radacct WHERE username = '$username'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['usage'];
    } else {
        return null;
    }
}

// return true
function update_user_usage($conn, $username, $session_time, $act_input, $act_output)
{
    $query = "UPDATE radacct SET AcctSessionTime = $session_time, AcctInputOctets = $act_input, AcctOutputOctets = $act_output WHERE username = '$username'";
    $conn->query($query);
    return true;
}

// return boolean
function exceed_limit($conn, $username)
{
    $traffic_usage = get_user_usage($conn, $username, 'traffic');
    $session_usage = get_user_usage($conn, $username, 'session');
    $traffic_limit = get_user_limit($conn, $username, 'traffic');
    $session_limit = get_user_limit($conn, $username, 'session');

    if ($traffic_usage > $traffic_limit || $session_usage > $session_limit) {
        return true;
    }
    return false;
}
?>