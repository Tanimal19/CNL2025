<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotspot Login</title>

    <style>
        body {
            font-family: sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            width: 400px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #333;
            margin-top: 20px;
        }

        input[type="text"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            box-sizing: border-box;
            border-radius: 4px;
        }

        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        .error {
            background-color: #f44336;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .success {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php
        session_start();

        /* settings */
        $UAM_SECRET = "tanum";
        $DB_HOST = "localhost";
        $DB_USER = "root";
        $DB_PASS = "123";
        $DB_NAME = "radius";

        /* utils */
        function generate_response($challenge, $password, $uamsecret)
        {
            $hexchal = pack("H*", $challenge);
            $hash = md5($hexchal . $password . $uamsecret, true);
            return bin2hex($hash);
        }

        function is_param_matched($mandatory_params)
        {
            $get_params = array_intersect(array_keys($_GET), $mandatory_params);
            return !array_diff($mandatory_params, $get_params);
        }

        /* database operations */
        /*
         * table structure
         * - radcheck: store user credentials
         * - radusergroup: store user group
         * - radreply: store user limitations
         * - radacct: store user session information
         */
        function add_user($conn, $username, $password, $traffic_limit, $time_limit)
        {
            $query_stream = [
                // add new user
                "INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'User-Password', ':=', '$password')",
                // add to user group
                "INSERT INTO radusergroup (username, groupname) VALUES ('$username', 'user')",
                // add limitations
                "INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', 'Max-Traffic', ':=', '$traffic_limit')",
                "INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', 'Max-Session', ':=', '$time_limit')",
            ];

            foreach ($query_stream as $query) {
                $conn->query($query);
            }
        }

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

        function get_user_limits($conn, $username)
        {
            $query = "SELECT attribute, value FROM radreply WHERE username = '$username' AND (attribute = 'Max-Traffic' OR attribute = 'Max-Session')";
            $result = $conn->query($query);
            $limits = array();

            while ($row = $result->fetch_assoc()) {
                $limits[$row['attribute']] = $row['value'];
            }
            return $limits;
        }

        function get_user_usage($conn, $username)
        {
            $query = "SELECT SUM(acctsessiontime) AS time_usage, SUM(acctinputoctets + acctoutputoctets) AS traffic_usage FROM radacct WHERE username='$username'";
            $result = $conn->query($query);

            if ($result->num_rows > 0) {
                return $result->fetch_assoc();
            } else {
                return null;
            }
        }

        // return info if user is within limits
        function check_limit($conn, $username)
        {
            $limits = get_user_limits($conn, $username);
            $usage = get_user_usage($conn, $username);

            if ($usage['traffic_usage'] > $limits['Max-Traffic'] or $usage['time_usage'] > $limits['Max-Session']) {
                return false;
            }

            $ret = array(
                'traffic_usage' => $usage['traffic_usage'],
                'time_usage' => $usage['time_usage'],
                'traffic_limit' => $limits['Max-Traffic'],
                'time_limit' => $limits['Max-Session'],
            );
            return $ret;
        }

        // connect to database
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if ($conn->connect_error) {
            die("db connection error: " . $conn->connect_error);
        }

        /* display functions */
        function display_notyet()
        {
            echo "<h2>Wifi Login</h2>";

            // login form
            echo "<form method='POST'>";
            echo "<input type='hidden' name='res' value='" . $_GET['res'] . "'>";
            echo "<input type='hidden' name='challenge' value='" . $_GET['challenge'] . "'>";
            echo "<input type='hidden' name='uamip' value='" . $_GET['uamip'] . "'>";
            echo "<input type='hidden' name='uamport' value='" . $_GET['uamport'] . "'>";
            echo "<input type='hidden' name='userurl' value='" . $_GET['userurl'] . "'>";
            echo "<input type='text' name='log-username' placeholder='username' required><br>";
            echo "<input type='password' name='log-password' placeholder='password' required><br>";
            echo "<input type='submit' name='login' value='Login'>";
            echo "</form>";

            // register form
            echo "<h3>Register Now.</h3>";
            echo "<form method='POST'>";
            echo "<input type='text' name='reg-username' placeholder='username' required><br>";
            echo "<input type='password' name='reg-password' placeholder='password' required><br>";
            echo "<input type='number' name='traffic-limit' placeholder='traffic limit (mb)' required><br>";
            echo "<input type='number' name='time-limit' placeholder='time limit (sec)' required><br>";
            echo "<input type='submit' name='register' value='Register'>";
            echo "</form>";
        }

        function display_success()
        {
            $logoff_url = "http://{$_GET['uamip']}:{$_GET['uamport']}/logoff";

            $username = $_GET['username'];
            $info = check_limit($conn, $username);

            if ($info === false) {
                header("Location: $logoff_url");
                return;
            }

            // display user info
            echo "<h2>$username's Dashboard</h2>";
            echo "<div class='info'>Traffic Usage: " . $info['traffic_usage'] . " / " . $info['traffic_limit'] . " mb</div>";
            echo "<div class='info'>Time Usage: " . $info['time_usage'] . " / " . $info['time_limit'] . " sec</div>";
            echo "<p><a href='$logoff_url'>Log off</a></p>";
        }

        function display_failed()
        {
            $prelogin_url = 'http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/prelogin?userurl=' . $_GET['userurl'];

            echo "<p><a href='$prelogin_url'>Back to login</a></p>";
        }

        function display_logout()
        {
            $prelogin_url = 'http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/prelogin?userurl=' . $_GET['userurl'];

            echo "<p><a href='$prelogin_url'>Back to login</a></p>";
        }

        function display_already()
        {
            $logoff_url = 'http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/logoff';

            echo "<p><a href='$logoff_url'>Log off</a></p>";
        }


        /* main logic */
        $res = $_GET['res'];
        if ($res) {
            switch ($res) {
                case 'notyet':
                    display_notyet();
                    break;
                case 'success':
                    display_success();
                    break;
                case 'failed':
                    display_failed();
                    break;
                case 'logoff':
                    display_logout();
                    break;
                case 'already':
                    display_already();
                    break;
            }
        } else {
            $error_message = "Missing Chillispot parameters. You can still register.";
            display_notyet();
        }

        // receive login form
        if (isset($_POST['login'])) {
            $log_params = array('challenge', 'uamip', 'uamport', 'userurl');

            if (is_param_matched($log_params)) {
                $challenge = $_POST['challenge'];
                $uamip = $_POST['uamip'];
                $uamport = $_POST['uamport'];
                $userurl = $_POST['userurl'];
                $username = $_POST['log-username'];
                $password = $_POST['log-password'];

                $real_password = get_user_password($conn, $username);

                if ($real_password) {
                    if ($real_password == $password) {
                        if (check_limit($conn, $username) === false) {
                            $error_message = "Session limit exceeded.";
                        } else {
                            $response = generate_response($challenge, $real_password, $UAM_SECRET);
                            $login_url = "http://$uamip:$uamport/logon?username=$username&password=$password&response=$response&userurl=$userurl";
                            header("Location: $login_url");
                        }
                    } else {
                        $error_message = "Invalid password.";
                    }
                } else {
                    $error_message = "Username not found.";
                }
            } else {
                $error_message = "Missing Chillispot parameters.";
            }
        }

        // receive register form
        if (isset($_POST['register'])) {
            $username = $_POST['reg-username'];
            $password = $_POST['reg-password'];
            $traffic_limit = $_POST['traffic-limit'];
            $time_limit = $_POST['time-limit'];

            if (is_user_exists($conn, $username)) {
                $error_message = "Username already exists.";
            } else {
                add_user($conn, $username, $password, $traffic_limit, $time_limit);
                $success_message = "User registered successfully. You can now log in.";
            }
        }

        if ($error_message) {
            echo "<div class='error'>$error_message</div>";
        }
        if ($success_message) {
            echo "<div class=success'>$success_message</div>";
        }

        $conn->close();
        ?>
    </div>
</body>

</html>