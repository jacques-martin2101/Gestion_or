<?php
// filepath: c:\xampp\htdocs\Gestion\login.php
session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: interface.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === 'admin' && $password === '1234') {
        $_SESSION['logged_in'] = true;
        header('Location: interface.php');
        exit;
    } else {
        $error = 'Identifiants incorrects.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body {
            background: #1a2236 url('images/fount/fout2.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .login-container {
            background: rgba(30,34,54,0.95);
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(31,38,135,0.37);
            width: 350px;
            margin: 80px auto;
            padding: 40px 35px 30px 35px;
            text-align: center;
            color: #fff;
        }
        .login-container h2 {
            margin-bottom: 30px;
            font-size: 2.2em;
            font-weight: bold;
        }
        .input-group {
            position: relative;
            margin-bottom: 22px;
        }
        .input-group input {
            width: 100%;
            padding: 13px 7px 13px 18px;
            border-radius: 25px;
            border: none;
            background: #232b47;
            color: #fff;
            font-size: 1em;
            outline: none;
        }
        .input-group .icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 1.2em;
        }
        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95em;
            margin-bottom: 18px;
        }
        .options label {
            cursor: pointer;
        }
        .login-btn {
            width: 100%;
            padding: 13px 0;
            border-radius: 25px;
            border: none;
            background: #fff;
            color: #232b47;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 10px;
            transition: background 0.2s;
        }
        .login-btn:hover {
            background:rgb(139, 136, 136);
        }
        .register-link {
            color: #fff;
            font-size: 0.97em;
        }
        .register-link a {
            color: #fff;
            font-weight: bold;
            text-decoration: underline;
        }
        .error {
            color: #ff5252;
            margin-bottom: 15px;
            font-size: 1em;
        }
    </style>
    <!-- Icons from FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required autofocus>
                <span class="icon"><i class="fa fa-user"></i></span>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
                <span class="icon"><i class="fa fa-lock"></i></span>
            </div>
            <div class="options">
               
                
            </div>
            <button type="submit" class="login-btn">Login</button>
        </form>
        <div class="register-link">
           
        </div>
    </div>
</body>
</html>