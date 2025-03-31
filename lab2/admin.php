<?php
include_once 'database.php';
include_once 'config.php';
include_once 'message.php';

$conn = create_connection($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $traffic_limit = $_POST['traffic_limit'];
        $time_limit = $_POST['time_limit'];

        if (!is_user_exists($conn, $username)) {
            add_user($conn, $username, $password, $traffic_limit, $time_limit);
            echo_message("User $username added successfully!");
        } else {
            echo_error_message("User $username already exists!");
        }
    }

    if (isset($_POST['remove_user'])) {
        $username = $_POST['username'];

        if (is_user_exists($conn, $username)) {
            remove_user($conn, $username);
            echo_message("User $username removed successfully!");
        } else {
            echo_error_message("User $username does not exist!");
        }
    }

    if (isset($_POST['set_usage'])) {
        $username = $_POST['username'];
        $session_time = $_POST['session_time'];
        $act_input = $_POST['act_input'];
        $act_output = $_POST['act_output'];

        if (is_user_exists($conn, $username)) {
            update_user_usage($conn, $username, $session_time, $act_input, $act_output);
            echo_message("Usage for user $username updated successfully!");
        } else {
            echo_error_message("User $username does not exist!");
        }
    }
}

$all_usernames = get_all_username($conn);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Admin Page</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1>Admin Page</h1>

        <h2>User List</h2>
        <?php if ($all_usernames != null): ?>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Traffic (Usage/Limit)</th>
                        <th>Session (Usage/Limit)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_usernames as $username): ?>
                        <tr>
                            <?php
                            $password = get_user_password($conn, $username);
                            $traffic_limit = get_user_limit($conn, $username, 'traffic');
                            $session_limit = get_user_limit($conn, $username, 'session');
                            $traffic_usage = get_user_usage($conn, $username, 'traffic');
                            $session_usage = get_user_usage($conn, $username, 'session');
                            $exceed_traffic = $traffic_usage > $traffic_limit;
                            $exceed_session = $session_usage > $session_limit;
                            ?>
                            <td><?php echo $username; ?></td>
                            <td><?php echo $password; ?></td>
                            <td style="<?php echo $exceed_traffic ? 'color: #e74c3c' : ''; ?>">
                                <?php echo "$traffic_usage / $traffic_limit mb"; ?>
                            </td>
                            <td style="<?php echo $exceed_session ? 'color: #e74c3c' : ''; ?>">
                                <?php echo "$session_usage / $session_limit sec"; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No users found.</p>
        <?php endif; ?>

        <div class="forms-container">
            <form method="post">
                <input type="hidden" name="add_user">
                <h3>Add New User</h3>
                <input type="text" name="username" placeholder="Username" required><br>
                <input type="password" name="password" placeholder="Password" required><br>
                <input type="text" name="traffic_limit" placeholder="Traffic Limit" required><br>
                <input type="text" name="time_limit" placeholder="Time Limit" required><br>
                <button type="submit">Add User</button>
            </form>
            <form method="post">
                <input type="hidden" name="remove_user">
                <h3>Remove User</h3>
                <input type="text" name="username" placeholder="Username" required><br>
                <button type="submit">Remove User</button>
            </form>
            <form method="post">
                <input type="hidden" name="set_usage">
                <h3>Update User Usage</h3>
                <input type="text" name="username" placeholder="Username" required><br>
                <input type="text" name="session_time" placeholder="Session Time" required><br>
                <input type="text" name="act_input" placeholder="Input Octets" required><br>
                <input type="text" name="act_output" placeholder="Output Octets" required><br>
                <button type="submit">Set Usage</button>
            </form>
        </div>
    </div>
</body>

</html>

<?php
$conn->close();
?>