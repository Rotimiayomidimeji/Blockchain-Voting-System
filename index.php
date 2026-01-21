<?php
// Include configuration and functions
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/admin.php');
    } else {
        redirect('voter/dashboard.php');
    }
    exit();
}

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $username = sanitize($_POST['voter_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Debug
    error_log("Login attempt - Username: $username, Password: " . (!empty($password) ? "[SET]" : "[EMPTY]"));
    
    // Basic validation
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
        error_log("Login validation failed: Empty fields");
    } else {
        // Attempt login using the auth.php function
        $result = loginUser($username, $password);
        
        error_log("Login result: " . print_r($result, true));
        
        if ($result['success']) {
            // Set session variables
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['user_role'] = $result['role'];
            $_SESSION['user_name'] = $result['user_name'];
            $_SESSION['auth_verified'] = isset($result['auth_verified']) ? $result['auth_verified'] : 0;
            
            error_log("Session variables set: " . print_r($_SESSION, true));
            
            // Debug: Check if user exists in database
            $user = getUserById($result['user_id']);
            error_log("User from database: " . print_r($user, true));
            
            // If user is voter and not email verified
            if ($result['role'] === 'voter') {
                if ($user && $user['auth_verified'] == 0) {
                    error_log("Voter not verified, redirecting to verify_auth.php");
                    redirect('voter/verify_auth.php');
                    exit();
                } else {
                    error_log("Voter verified, redirecting to dashboard.php");
                    redirect('voter/dashboard.php');
                }
            } else {
                error_log("Admin user, redirecting to admin.php");
                redirect('admin/admin.php');
            }
            exit();
        } else {
            $error = $result['message'];
            error_log("Login failed: " . $error);
        }
    }
}// This closes the if ($_SERVER['REQUEST_METHOD'] == 'POST') block
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Login • E-Voting Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
<style>
  body{
    margin:0;
    padding:0;
    font-family:Poppins,sans-serif;
    background:#0d0f16;
    color:#fff;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background-image: 
      radial-gradient(circle at 20% 80%, rgba(0, 224, 168, 0.15) 0%, transparent 50%),
      radial-gradient(circle at 80% 20%, rgba(0, 100, 255, 0.1) 0%, transparent 50%);
  }

  .container{
    width:90%;
    max-width:420px;
    background:rgba(255,255,255,0.07);
    backdrop-filter:blur(18px);
    padding:40px;
    border-radius:24px;
    box-shadow:0 0 25px rgba(0,0,0,0.45);
    border:1px solid rgba(255,255,255,0.1);
    animation:fadeIn .7s ease;
  }

  @keyframes fadeIn{
    from{opacity:0; transform:translateY(20px);} 
    to{opacity:1; transform:translateY(0);}
  }

  h2{
    text-align:center;
    margin-top:0;
    font-size:28px;
    font-weight:700;
    background:linear-gradient(90deg, #00e0a8, #00ffbf);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
  }

  p{
    text-align:center;
    opacity:.8;
    margin-bottom:25px;
    font-size:15px;
  }

  label{
    font-weight:600;
    font-size:14px;
    display:block;
    margin-bottom:6px;
    color:#e0e0e0;
  }

  input{
    width:100%;
    padding:14px;
    margin:8px 0 18px;
    border:none;
    border-radius:12px;
    background:rgba(255,255,255,0.12);
    color:#fff;
    font-size:15px;
    border:1px solid rgba(255,255,255,0.1);
    transition:all 0.3s ease;
  }

  input:focus{
    outline:none;
    border-color:#00e0a8;
    box-shadow:0 0 0 3px rgba(0, 224, 168, 0.2);
    background:rgba(255,255,255,0.15);
  }

  input::placeholder{color:#aaa;}

  .btn{
    width:100%;
    padding:16px;
    background:linear-gradient(90deg, #00e0a8, #00ffbf);
    border:none;
    border-radius:14px;
    font-size:17px;
    font-weight:700;
    cursor:pointer;
    color:#000;
    transition:.3s;
    margin-top:10px;
  }

  .btn:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 20px rgba(0, 224, 168, 0.3);
  }

  .btn:active{
    transform:translateY(0);
  }

  .footer-link{
    margin-top:22px;
    text-align:center;
    font-size:14px;
    opacity:.8;
  }

  .footer-link a{
    color:#00ffbf;
    text-decoration:none;
    font-weight:600;
  }

  .footer-link a:hover{
    text-decoration:underline;
  }

  .error-box{
    background:rgba(255, 77, 77, 0.18);
    border-left:4px solid #ff4d4d;
    padding:14px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:14px;
    animation:fadeIn .3s ease;
    display:flex;
    align-items:center;
    gap:10px;
  }

  .error-box:before{
    content:"⚠";
    font-size:18px;
  }

  .success-box{
    background:rgba(0, 224, 168, 0.18);
    border-left:4px solid #00e0a8;
    padding:14px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:14px;
    animation:fadeIn .3s ease;
    display:flex;
    align-items:center;
    gap:10px;
  }

  .demo-creds{
    background:rgba(255,255,255,0.05);
    border-radius:12px;
    padding:15px;
    margin-top:20px;
    font-size:13px;
  }

  .demo-creds h4{
    margin:0 0 10px 0;
    color:#00e0a8;
    font-size:14px;
  }

  .demo-creds ul{
    margin:0;
    padding-left:20px;
    opacity:.9;
  }

  .demo-creds li{
    margin-bottom:6px;
  }

  @media (max-width: 480px){
    .container{
      padding:30px 20px;
    }
    
    h2{
      font-size:24px;
    }
  }
</style>
</head>
<body>

<div class="container">
  
  <form method="POST" action="">
    <h2>Welcome Back</h2>
    <p>Login to access the E-Voting Dashboard</p>

    <?php if (!empty($error)): ?>
      <div class="error-box">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['registered'])): ?>
      <div class="success-box">
        ✅ Registration successful! Your account is pending admin verification.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['logout'])): ?>
      <div class="success-box">
        ✅ You have been successfully logged out.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['session_expired'])): ?>
      <div class="error-box">
        ⚠ Your session has expired. Please login again.
      </div>
    <?php endif; ?>

    <label>Username / Voter ID</label>
    <input type="text" name="voter_id" placeholder="Enter your Username or Voter ID" 
           value="<?php echo htmlspecialchars($_POST['voter_id'] ?? ''); ?>" required>

    <label>Password</label>
    <input type="password" name="password" placeholder="Enter your Password" required>

    <button class="btn" type="submit">Login</button>

    <!-- <div class="demo-creds">
      <h4>Demo Credentials:</h4>
      <ul>
        <li><strong>Admin:</strong> admin / Admin@2024</li>
        <li><strong>Voter:</strong> voter001 / Voter@2024</li>
      </ul>
    </div> -->

    <div class="footer-link">
      Don't have an account? <a href="register.php">Register here</a>
    </div>
  </form>

</div>

<script>
  // Add some interactive effects
  document.querySelectorAll('input').forEach(input => {
    input.addEventListener('focus', function() {
      this.style.transform = 'scale(1.02)';
    });
    
    input.addEventListener('blur', function() {
      this.style.transform = 'scale(1)';
    });
  });

  // Auto-hide error messages after 5 seconds
  setTimeout(() => {
    const errorBox = document.querySelector('.error-box');
    if (errorBox) {
      errorBox.style.opacity = '0';
      setTimeout(() => errorBox.remove(), 300);
    }
    
    const successBox = document.querySelector('.success-box');
    if (successBox) {
      setTimeout(() => {
        successBox.style.opacity = '0';
        setTimeout(() => successBox.remove(), 300);
      }, 3000);
    }
  }, 5000);

  // Prevent form resubmission on page refresh
  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }
</script>

</body>
</html>