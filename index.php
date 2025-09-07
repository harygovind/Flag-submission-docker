<?php
// =========================================================================
// SECTION 1: GLOBAL SETUP & DATABASE CONNECTION
// This runs on every request before any page-specific logic.
// MODIFIED: This section now connects to your Supabase PostgreSQL database.
// =========================================================================

// --- Database Configuration from Environment Variables ---
// This code now reads your database credentials from Render's Environment Variables.
$db_host = getenv('DB_HOST');
$db_port = getenv('DB_PORT');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');

// --- Establish Database Connection (PDO for PostgreSQL) ---
try {
    // MODIFIED: The connection string now uses the 'pgsql' driver for PostgreSQL.
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
    
    // Creates the database connection object.
    $pdo = new PDO($dsn, $db_user, $db_pass);

    // Set PDO to throw exceptions on error for easier debugging.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // If connection fails, stop the script and show an error message.
    // In a live production environment, you might want to log this error instead of showing it.
    die("Error: Could not connect to the database. " . $e->getMessage());
}

// --- Start Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// SECTION 2: REUSABLE VIEW FUNCTIONS (HTML TEMPLATES)
// =========================================================================

/**
 * Renders the HTML head and navigation bar.
 * @param string $page_title The title for the <title> tag.
 */
function render_header($page_title = 'CTF Platform') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($page_title) . ' - Calicore CTF'; ?></title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <nav>
            <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                <a href="?page=dashboard" class="nav-btn">Dashboard</a>
                <a href="?page=profile" class="nav-btn">Profile</a>
                <a href="?page=leaderboard" class="nav-btn">Leaderboard</a>
                <a href="?page=logout" class="nav-btn">Logout</a>
            <?php else: ?>
                <a href="?page=leaderboard" class="nav-btn">Leaderboard</a>
            <?php endif; ?>
        </nav>
        <div class="container">
    <?php
}

/**
 * Renders the standard footer with the mouse trail effect.
 */
function render_footer() {
    global $show_persistent_hints;
    ?>
        </div> <?php
        // This block handles the display of special information revealed after certain flags.
        if (isset($show_persistent_hints) && $show_persistent_hints && !empty($_SESSION['persistent_messages'])) {
            echo '<div class="persistent-hints-container">';
            echo '<h4>Revealed Information</h4>';
            if (isset($_SESSION['persistent_messages']['flag_3'])) {
                echo '<p class="milestone-hint">' . htmlspecialchars($_SESSION['persistent_messages']['flag_3']) . '</p>';
            }
            if (isset($_SESSION['persistent_messages']['flag_4'])) {
                echo '<p class="milestone-hint">' . htmlspecialchars($_SESSION['persistent_messages']['flag_4']['line1']) . '</p>';
                echo '<p class="milestone-hint">' . htmlspecialchars($_SESSION['persistent_messages']['flag_4']['line2']) . '</p>';
            }
            echo '</div>';
        }
        ?>

        <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            // This script creates the interactive mouse trail effect
            const body = document.body;
            let canCreateParticle = true;
            document.addEventListener('mousemove', (e) => {
                if (!canCreateParticle) return;
                canCreateParticle = false;
                setTimeout(() => { canCreateParticle = true; }, 50);

                const particle = document.createElement('div');
                particle.classList.add('trail-particle');
                const size = Math.random() * 6 + 2;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = e.clientX + 'px';
                particle.style.top = e.clientY + 'px';
                const driftX = (Math.random() - 0.5) * 100;
                const driftY = (Math.random() - 0.5) * 100;
                particle.style.setProperty('--drift-x', `${driftX}px`);
                particle.style.setProperty('--drift-y', `${driftY}px`);
                body.appendChild(particle);
                setTimeout(() => { particle.remove(); }, 500);
            });
        </script>
    </body>
    </html>
    <?php
}

// =========================================================================
// SECTION 3: PAGE ROUTER
// This is the main controller that decides which page to show.
// =========================================================================

// Determine which page to load. Default to 'login' if not logged in.
$default_page = (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) ? 'dashboard' : 'login';
$page = $_GET['page'] ?? $default_page;

switch ($page) {

    // --- Login Page ---
    case 'login':
        $page_title = 'Login';
        
        if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
            header("location: ?page=dashboard");
            exit;
        }

        $message = $message_class = "";
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                if ($password === $user['password']) { // NOTE: Using plain text password comparison
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("location: ?page=dashboard");
                    exit;
                }
            }
            $message = "Incorrect username or password.";
            $message_class = "error shake";
        }

        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $message_class = $_SESSION['flash_class'];
            unset($_SESSION['flash_message'], $_SESSION['flash_class']);
        }
        
        render_header($page_title);
        ?>
        <h2>Calicore CTF</h2>
        <p>Please enter your credentials to continue.</p>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_class; ?> show"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="POST" action="?page=login">
            <div class="form-group"><label for="username">Team Name</label><input type="text" id="username" name="username" class="form-input" required></div>
            <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" class="form-input" required></div>
            <button type="submit" class="btn">Log In</button>
        </form>
        <div class="link-group"><p>Don't have an account? <a href="?page=signup">Sign Up</a></p></div>
        <?php
        render_footer();
        break;

    // --- Signup Page ---
    case 'signup':
        $page_title = 'Sign Up';
        
        if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
            header("location: ?page=dashboard");
            exit;
        }

        $message = $message_class = "";
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                $message = "Team Name already exists.";
                $message_class = "error";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                if ($stmt->execute([$username, $password])) {
                    $_SESSION['flash_message'] = "Account created! You can now log in.";
                    $_SESSION['flash_class'] = "success";
                    header("location: ?page=login");
                    exit;
                } else {
                    $message = "Something went wrong. Please try again.";
                    $message_class = "error";
                }
            }
        }
        
        render_header($page_title);
        ?>
        <h2>Create Your Account</h2>
        <p>Join the challenge and start hacking!</p>
        <div class="message warning"><strong>Important:</strong> No password recovery. Remember your credentials!</div>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_class; ?> show"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="POST" action="?page=signup">
            <div class="form-group"><label for="username">Team Name</label><input type="text" id="username" name="username" class="form-input" required></div>
            <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" class="form-input" required></div>
            <button type="submit" class="btn">Sign Up</button>
        </form>
        <div class="link-group"><p>Already have an account? <a href="?page=login">Log In</a></p></div>
        <?php
        render_footer();
        break;

    // --- Dashboard Page ---
    case 'dashboard':
        $page_title = 'Dashboard';
        
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header("location: ?page=login");
            exit;
        }

        $flash_message = $flash_class = "";
        if (isset($_SESSION['flash_message'])) {
            $flash_message = json_encode($_SESSION['flash_message']);
            $flash_class = json_encode($_SESSION['flash_class']);
            unset($_SESSION['flash_message'], $_SESSION['flash_class']);
        }
        
        render_header($page_title);
        ?>
        <h2>Flag Submission</h2>
        <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>! Submit your flags below.</p>
        <form method="POST" action="?page=submit_flag">
            <div class="form-group"><label for="flag">Enter Flag</label><input type="text" id="flag" name="flag" class="form-input" placeholder="Format: {{string}}" required></div>
            <button type="submit" class="btn">Submit Flag</button>
        </form>
        <?php
        if (isset($_SESSION['persistent_messages']) && !empty($_SESSION['persistent_messages'])) {
            echo '<div class="message warning" style="margin-top: 2rem; text-align: center; padding: 1.5rem;">'; 
            echo '<p><strong>Important: Save this information. It will not be shown again.</strong></p>';
            foreach ($_SESSION['persistent_messages'] as $message) {
                echo '<p style="margin-top: 0.5rem; margin-bottom: 0.5rem;">';
                echo is_array($message) ? implode('<br>', array_map('htmlspecialchars', $message)) : htmlspecialchars($message);
                echo '</p>';
            }
            echo '</div>';
            unset($_SESSION['persistent_messages']);
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const flashMessage = <?php echo !empty($flash_message) ? $flash_message : 'null'; ?>;
            const flashClass = <?php echo !empty($flash_class) ? $flash_class : 'null'; ?>;
            if (flashMessage) {
                Swal.fire({
                    title: flashClass === 'success' ? 'Success!' : 'Oops...', text: flashMessage, icon: flashClass,
                    confirmButtonText: flashClass === 'success' ? 'Next!' : 'Try Again',
                    background: '#1e1e1e', color: '#e0e0e0', confirmButtonColor: '#bb86fc'
                });
            }
        });
        </script>
        <?php
        render_footer();
        break;

    // --- Profile Page ---
    case 'profile':
        $page_title = 'My Profile';
        
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header("location: ?page=login");
            exit;
        }

        $user_id = $_SESSION['user_id'];
        $stmt_user = $pdo->prepare("SELECT username, points FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $user = $stmt_user->fetch();

        $stmt_flags = $pdo->prepare("SELECT f.id, f.points FROM submissions s JOIN flags f ON s.flag_id = f.id WHERE s.user_id = ? ORDER BY f.id ASC");
        $stmt_flags->execute([$user_id]);
        $found_flags = $stmt_flags->fetchAll();

        $total_flags = $pdo->query("SELECT COUNT(*) FROM flags")->fetchColumn();
        
        render_header($page_title);
        ?>
        <h2>Profile: <?php echo htmlspecialchars($user['username']); ?></h2>
        <p>Here are your current stats in the challenge.</p>
        <div class="profile-stats">
            <div class="stat-box"><h4>Total Points</h4><p><?php echo $user['points']; ?></p></div>
            <div class="stat-box"><h4>Flags Found</h4><p><?php echo count($found_flags); ?> / <?php echo $total_flags; ?></p></div>
        </div>
        <h3>Solved Challenges</h3>
        <ul class="found-flags-list">
            <?php if (count($found_flags) > 0): ?>
                <?php foreach ($found_flags as $flag): ?>
                    <li><span class="flag-check">🚩</span><span class="flag-name">Flag <?php echo $flag['id']; ?></span><span class="flag-points">+<?php echo $flag['points']; ?> pts</span></li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="no-flags">You haven't submitted any flags yet.</li>
            <?php endif; ?>
        </ul>
        <?php
        render_footer();
        break;

    // --- Leaderboard Page ---
    case 'leaderboard':
        $page_title = 'Leaderboard';
        
        $stmt = $pdo->query("SELECT username, points FROM users ORDER BY points DESC, last_submission ASC");
        $initial_users = $stmt->fetchAll();
        
        render_header($page_title);
        ?>
        <h2>🏆 Leaderboard 🏆</h2>
        <table class="leaderboard-table">
            <thead><tr><th>Rank</th><th>Team Name</th><th>Points</th></tr></thead>
            <tbody id="leaderboard-body">
                <?php if (count($initial_users) > 0): ?>
                    <?php foreach ($initial_users as $index => $user): ?>
                    <tr><td><?php echo $index + 1; ?></td><td><?php echo htmlspecialchars($user['username']); ?></td><td><?php echo $user['points']; ?></td></tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">No submissions yet. Be the first!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true): ?>
        <div class="link-group"><p><a href="?page=login">Back to Login</a></p></div>
        <?php endif; ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const updateLeaderboard = async () => {
                try {
                    const response = await fetch('?page=get_leaderboard_data');
                    if (!response.ok) return;
                    const users = await response.json();
                    const leaderboardBody = document.getElementById('leaderboard-body');
                    leaderboardBody.innerHTML = '';
                    if (users.length === 0) {
                        leaderboardBody.innerHTML = '<tr><td colspan="3">No submissions yet. Be the first!</td></tr>'; return;
                    }
                    users.forEach((user, index) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${index + 1}</td><td>${escapeHTML(user.username)}</td><td>${user.points}</td>`;
                        leaderboardBody.appendChild(row);
                    });
                } catch (error) { console.error('Failed to update leaderboard:', error); }
            };
            const escapeHTML = (str) => { const p = document.createElement('p'); p.textContent = str; return p.innerHTML; };
            setInterval(updateLeaderboard, 5000);
        });
        </script>
        <?php
        render_footer();
        break;
        
    // --- Leaderboard JSON Data Endpoint (API) ---
    case 'get_leaderboard_data':
        $stmt = $pdo->query("SELECT username, points FROM users ORDER BY points DESC, last_submission ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($users);
        exit;

    // --- Flag Submission Action ---
    case 'submit_flag':
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("location: ?page=login"); exit; }
        if ($_SERVER["REQUEST_METHOD"] != "POST") { header("location: ?page=dashboard"); exit; }

        $special_flags = ['flag_3' => '{{R7tQ4mPz!kV0eN1jW5}}', 'flag_4' => '{{aQ4sW6&eDcFvRtGyH}}'];
        $special_messages = ['flag_3' => 'creds=username:password', 'flag_4' => ['line1' => 'you can connect it with rdp', 'line2' => 'Don\'t bruteforce']];
        
        $submitted_flag = trim($_POST['flag']);
        $user_id = $_SESSION['user_id'];

        $stmt_flag = $pdo->prepare("SELECT id, points FROM flags WHERE flag_text = ?");
        $stmt_flag->execute([$submitted_flag]);
        $flag = $stmt_flag->fetch();

        if (!$flag) {
            $_SESSION['flash_message'] = "That's not the right flag. Keep trying!";
            $_SESSION['flash_class'] = "error";
        } else {
            $flag_id = $flag['id'];
            $stmt_sub = $pdo->prepare("SELECT id FROM submissions WHERE user_id = ? AND flag_id = ?");
            $stmt_sub->execute([$user_id, $flag_id]);
            if ($stmt_sub->rowCount() > 0) {
                $_SESSION['flash_message'] = "You've already submitted this flag!";
                $_SESSION['flash_class'] = "error";
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("INSERT INTO submissions (user_id, flag_id) VALUES (?, ?)")->execute([$user_id, $flag_id]);
                    $pdo->prepare("UPDATE users SET points = points + ?, last_submission = NOW() WHERE id = ?")->execute([$flag['points'], $user_id]);
                    $pdo->commit();
                    $_SESSION['flash_message'] = "Congratulations! Flag found.";
                    $_SESSION['flash_class'] = "success";
                    if (in_array($submitted_flag, $special_flags)) {
                        $_SESSION['persistent_messages'] = $_SESSION['persistent_messages'] ?? [];
                        $flag_key = array_search($submitted_flag, $special_flags);
                        if ($flag_key !== false) $_SESSION['persistent_messages'][$flag_key] = $special_messages[$flag_key];
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['flash_message'] = "A database error occurred. Please try again.";
                    $_SESSION['flash_class'] = "error";
                }
            }
        }
        header("location: ?page=dashboard");
        exit;

    // --- Logout Action ---
    case 'logout':
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        header("location: ?page=login");
        exit;

    // --- Default case for unknown pages ---
    default:
        http_response_code(404);
        render_header('404 Not Found');
        echo "<h2>404 - Page Not Found</h2><p>The page you requested could not be found.</p>";
        render_footer();
        break;
}
?>
