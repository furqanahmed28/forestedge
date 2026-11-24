<?php
// web/login.php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT user_id, password_hash, role FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ForestEdge Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7f8; display: flex; justify-content: center; align-items: center; height: 100vh; margin:0;}
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px;}
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;}
        button { width: 100%; padding: 10px; background: #2E7D32; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;}
        button:hover { background: #1B5E20; }
        .error { color: red; margin-bottom: 10px; }
        .logo { text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 20px;}
        .logo span { color: #2E7D32; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Forest<span>Edge</span></div>
        <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required value="admin@forestedge.local">
            <div style="position:relative; margin: 10px 0;">
                <input type="password" id="passInput" name="password" placeholder="Password" style="margin:0; padding-right:40px;" required value="password">
                <button type="button" onclick="togglePass()" style="position:absolute; right:5px; top:5px; width:30px; background:none; color:#555; padding:5px; font-size:12px;">👁</button>
            </div>
            <button type="submit" style="margin-top:10px;">Login</button>
        </form>
    </div>
    <script>
      function togglePass() {
        let ipt = document.getElementById('passInput');
        if (ipt.type === "password") {
            ipt.type = "text";
        } else {
            ipt.type = "password";
        }
      }
    </script>
</body>
</html>
