<?php
session_start();

include_once 'database.php';
include_once 'config.php';
include_once 'message.php';

$conn = create_connection($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$exceed = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $username = $_POST['log-username'];
        $password = $_POST['log-password'];

        $real_password = get_user_password($conn, $username);

        if ($real_password) {
            if ($real_password == $password) {
                $_SESSION['username'] = $username;
                $_SESSION['start_time'] = time();
            } else {
                echo_error_message("Wrong password for user $username.");
            }
        } else {
            echo_error_message("User $username does not exist.");
        }
    }

    if (isset($_POST['register'])) {
        $username = $_POST['reg-username'];
        $password = $_POST['reg-password'];
        $traffic_limit = $_POST['traffic-limit'];
        $session_limit = $_POST['session-limit'];

        if (is_user_exists($conn, $username)) {
            echo_error_message("Username $username already exists!");
        } else {
            add_user($conn, $username, $password, $traffic_limit, $session_limit);
            echo_message("User $username registered successfully!");
        }
    }

    if (isset($_POST['refresh']) && isset($_SESSION['username'])) {
        $username = $_SESSION['username'];

        $session_usage = get_user_usage($conn, $username, 'session');
        $traffic_usage = get_user_usage($conn, $username, 'traffic');
        $session_limit = get_user_limit($conn, $username, 'session');
        $traffic_limit = get_user_limit($conn, $username, 'traffic');

        $new_session_usage = time() - $_SESSION['start_time'];
        $new_traffic_usage = $traffic_usage + rand(0, 5);

        update_user_usage($conn, $username, $new_session_usage, $new_traffic_usage, 0);

        if ($new_session_usage > $session_limit || $new_traffic_usage > $traffic_limit) {
            echo_error_message("You've been exceeding your usage limits! session: $new_session_usage s, traffic: $new_traffic_usage mb");
            // log user out
            update_user_usage($conn, $username, 0, 0, 0);
            session_unset();
        }
    }

    if (isset($_POST['logout']) && isset($_SESSION['username'])) {
        $username = $_SESSION['username'];
        update_user_usage($conn, $username, 0, 0, 0);
        session_unset();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <title>Hotspot Login</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <?php
        $is_login = isset($_SESSION['username']);
        $username = $is_login ? $_SESSION['username'] : null;

        if ($is_login) {
            ?>
            <h2>Welcome, <?php echo $username; ?>!</h2>

            <?php
            $traffic_usage = get_user_usage($conn, $username, 'traffic');
            $session_usage = get_user_usage($conn, $username, 'session');
            $traffic_limit = get_user_limit($conn, $username, 'traffic');
            $session_limit = get_user_limit($conn, $username, 'session');
            $exceed_traffic = $traffic_usage > $traffic_limit;
            $exceed_session = $session_usage > $session_limit;
            ?>

            <h3 style="<?php echo $exceed_traffic ? 'color: #e74c3c' : ''; ?>">
                <?php echo "$traffic_usage / $traffic_limit mb"; ?>
            </h3>
            <h3 style="<?php echo $exceed_session ? 'color: #e74c3c' : ''; ?>">
                <?php echo "$session_usage / $session_limit sec"; ?>
            </h3>

            <div class="forms-container">
                <form method='post'>
                    <input type='hidden' name='refresh'>
                    <button type='submit'>Refresh</button>
                </form>

                <form method='post'>
                    <input type='hidden' name='logout'>
                    <button type='submit'>Logout</button>
                </form>
            </div>
            <?php
        } else {
            ?>
            <h1>Wifi Hotspot</h1>

            <div class="forms-container">
                <form method='post'>
                    <input type='hidden' name='login'>
                    <h3>Login</h3>
                    <input type='text' name='log-username' placeholder='username' required><br>
                    <input type='password' name='log-password' placeholder='password' required><br>
                    <button type='submit'>Login</button>
                </form>

                <form method='post'>
                    <input type='hidden' name='register'>
                    <h3>Register</h3>
                    <input type='text' name='reg-username' placeholder='username' required><br>
                    <input type='password' name='reg-password' placeholder='password' required><br>
                    <input type='text' name='traffic-limit' placeholder='traffic limit (mb)' required><br>
                    <input type='text' name='session-limit' placeholder='session limit (sec)' required><br>
                    <button type='submit'>Register</button>
                </form>
            </div>
            <?php
        }
        ?>
    </div>
</body>

</html>

<?php
$conn->close();
?>