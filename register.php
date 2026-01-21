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

// Handle registration
$error = '';
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Initialize form data
    $formData = [
        'full_name' => sanitize($_POST['full_name'] ?? ''),
        'username' => sanitize($_POST['username'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'date_of_birth' => sanitize($_POST['date_of_birth'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'national_id' => sanitize($_POST['national_id'] ?? ''),
        'verification_document' => '' // Initialize
    ];

    $errors = [];
    
    // File upload handling
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    if (isset($_FILES['verification_document']) && $_FILES['verification_document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['verification_document'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = time() . '_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9\.]/', '_', $file['name']);
        $filePath = $uploadDir . $fileName;
        
        // Validate file
        if (!in_array($fileExt, $allowedTypes)) {
            $errors[] = 'Invalid file type. Please upload PDF, JPG, PNG, or DOC files.';
        } elseif ($file['size'] > $maxFileSize) {
            $errors[] = 'File size too large. Maximum 5MB allowed.';
        } elseif (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $errors[] = 'Failed to upload document. Please try again.';
        } else {
            $formData['verification_document'] = $fileName;
        }
    } else {
        $errors[] = 'Verification document is required.';
    }
    
    // Required fields
    $required = ['full_name', 'username', 'email', 'phone', 'address', 'date_of_birth', 'gender', 'national_id'];
    foreach ($required as $field) {
        if (empty($formData[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    
    // Email validation
    if (!empty($formData['email']) && !isValidEmail($formData['email'])) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Date validation
    if (!empty($formData['date_of_birth'])) {
        $dob = strtotime($formData['date_of_birth']);
        $minAge = strtotime('-18 years'); // Must be 18 years old
        if ($dob > $minAge) {
            $errors[] = 'You must be at least 18 years old to register.';
        }
    }
    
    // If no validation errors
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            
            // Check if username, email, or national ID already exists
            $stmt = $pdo->prepare("SELECT id FROM registration_requests WHERE username = ? OR email = ? OR national_id = ?");
            $stmt->execute([$formData['username'], $formData['email'], $formData['national_id']]);
            
            if ($stmt->fetch()) {
                $error = 'Username, email, or national ID already exists in registration requests!';
            } else {
                // Check if username, email, or national ID already exists in users table
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR national_id = ?");
                $stmt->execute([$formData['username'], $formData['email'], $formData['national_id']]);
                
                if ($stmt->fetch()) {
                    $error = 'Username, email, or national ID already exists in the system!';
                } else {
                    // FIXED: Updated INSERT query to include verification_document
                    $stmt = $pdo->prepare("
                        INSERT INTO registration_requests 
                        (username, email, full_name, phone, address, date_of_birth, gender, national_id, verification_document) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $formData['username'],
                        $formData['email'],
                        $formData['full_name'],
                        $formData['phone'],
                        $formData['address'],
                        $formData['date_of_birth'],
                        $formData['gender'],
                        $formData['national_id'],
                        $formData['verification_document']
                    ]);
                    
                    $request_id = $pdo->lastInsertId();
                    
                    // Send professional confirmation email
                    $emailData = [
                        'full_name' => $formData['full_name'],
                        'username' => $formData['username'],
                        'email' => $formData['email'],
                        'phone' => $formData['phone'],
                        'national_id' => $formData['national_id'],
                        'request_id' => $request_id
                    ];

                    if (sendProfessionalEmail($formData['email'], 'registration_submitted', $emailData, '', $formData['full_name'])) {
                        $success = 'Registration submitted successfully! A professional confirmation email has been sent to your email address.';
                    } else {
                        $success = 'Registration submitted successfully! However, confirmation email could not be sent. You will be notified once your registration is approved.';
                    }
                    
                    // Log the registration attempt BEFORE clearing form data
                    if (function_exists('logActivity')) {
                        logActivity(0, 'REGISTRATION_REQUEST', "Registration request submitted: " . $formData['full_name']);
                    }

                    // Clear form data AFTER logging
                    $formData = [];
                }
            }
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Register • E-Voting Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
  body{
    margin:0;
    padding:0;
    font-family:Poppins,sans-serif;
    background:#0d0f16;
    color:#fff;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background-image: 
      radial-gradient(circle at 20% 80%, rgba(0, 224, 168, 0.15) 0%, transparent 50%),
      radial-gradient(circle at 80% 20%, rgba(0, 100, 255, 0.1) 0%, transparent 50%);
    padding: 20px;
  }

  .container{
    width:100%;
    max-width:550px;
    background:rgba(255,255,255,0.07);
    backdrop-filter:blur(18px);
    padding:30px;
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
    margin-bottom: 10px;
  }

  .subtitle{
    text-align:center;
    opacity:.7;
    margin-bottom:25px;
    font-size:15px;
    line-height: 1.5;
  }

  .form-group{
    margin-bottom:20px;
    width: 100%;
  }

  label{
    font-weight:600;
    font-size:14px;
    display:block;
    margin-bottom:8px;
    color:#e0e0e0;
  }

  label .required{
    color:#ff6b6b;
    margin-left:4px;
  }

  input, select, textarea, .file-input{
    width:100%;
    padding:14px 16px;
    border:none;
    border-radius:12px;
    background:rgba(255,255,255,0.12);
    color:#fff;
    font-size:15px;
    border:1px solid rgba(255,255,255,0.1);
    transition:all 0.3s ease;
    font-family:Poppins,sans-serif;
    box-sizing: border-box;
  }

  .file-input {
    padding: 10px;
  }

  select{
    appearance:none;
    background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat:no-repeat;
    background-position:right 16px center;
    background-size:18px;
    padding-right:45px;
  }

  textarea{
    min-height:100px;
    resize:vertical;
    line-height: 1.5;
  }

  input:focus, select:focus, textarea:focus, .file-input:focus{
    outline:none;
    border-color:#00e0a8;
    box-shadow:0 0 0 3px rgba(0, 224, 168, 0.2);
    background:rgba(255,255,255,0.15);
  }

  input::placeholder, textarea::placeholder{color:#aaa;}

  .form-row{
    display:flex;
    gap:15px;
    width: 100%;
  }

  .form-row .form-group{
    flex:1;
    min-width: 0; /* Prevents flex items from overflowing */
  }

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

  .btn-secondary {
    background:rgba(255,255,255,0.1);
    color:#fff;
    margin-top: 10px;
  }

  .btn-secondary:hover {
    background:rgba(255,255,255,0.2);
    transform:translateY(-2px);
    box-shadow:0 8px 20px rgba(255, 255, 255, 0.1);
  }

  .footer-link{
    margin-top:25px;
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
    line-height: 1.5;
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
    line-height: 1.5;
  }

  .success-box:before{
    content:"✓";
    font-size:18px;
  }

  .info-box{
    background:rgba(0, 123, 255, 0.18);
    border-left:4px solid #007bff;
    padding:14px;
    border-radius:10px;
    margin-bottom:25px;
    font-size:14px;
    animation:fadeIn .3s ease;
    display:flex;
    align-items:center;
    gap:10px;
    line-height: 1.5;
  }

  .info-box:before{
    content:"ℹ";
    font-size:18px;
  }

  .terms-check{
    display:flex;
    align-items:flex-start;
    gap:12px;
    margin:25px 0;
    padding:18px;
    background:rgba(255,255,255,0.05);
    border-radius:12px;
    line-height: 1.6;
  }

  .terms-check input{
    width:20px;
    height:20px;
    margin-top:3px;
    accent-color:#00e0a8;
    flex-shrink: 0;
  }

  .terms-check label{
    font-size:14px;
    font-weight:400;
    margin:0;
    line-height:1.6;
  }

  .terms-check a{
    color:#00ffbf;
    text-decoration:none;
    font-weight: 600;
  }

  .terms-check a:hover{
    text-decoration:underline;
  }

  /* Step Navigation */
  .step-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    position: relative;
  }

  .step-navigation::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: rgba(255, 255, 255, 0.1);
    z-index: 1;
  }

  .step {
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
  }

  .step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    color: #9db0c9;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-weight: 600;
    font-size: 18px;
    border: 2px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
  }

  .step.active .step-circle {
    background: linear-gradient(135deg, #00e0a8, #0099cc);
    color: #000;
    border-color: #00e0a8;
    transform: scale(1.1);
  }

  .step.completed .step-circle {
    background: rgba(0, 224, 168, 0.2);
    color: #00e0a8;
    border-color: #00e0a8;
  }

  .step.completed .step-circle i {
    font-size: 18px;
  }

  .step-label {
    font-size: 12px;
    color: #9db0c9;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
  }

  .step.active .step-label {
    color: #00e0a8;
  }

  /* Form Steps */
  .form-step {
    display: none;
    animation: fadeIn 0.5s ease;
  }

  .form-step.active {
    display: block;
  }

  /* Form Actions */
  .form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
  }

  .form-actions .btn {
    flex: 1;
    margin-top: 0;
  }

  /* File Upload Styling */
  .file-upload-area {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 2px dashed rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    text-align: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
  }

  .file-upload-area:hover {
    border-color: #00e0a8;
    background: rgba(0, 224, 168, 0.05);
  }

  .file-upload-area.dragover {
    border-color: #00e0a8;
    background: rgba(0, 224, 168, 0.1);
    transform: translateY(-2px);
  }

  .file-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }

  .upload-icon {
    font-size: 48px;
    color: #00e0a8;
    margin-bottom: 15px;
  }

  .upload-text {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #fff;
  }

  .upload-hint {
    font-size: 14px;
    color: #9db0c9;
    margin-bottom: 15px;
  }

  .file-info {
    display: none;
    background: rgba(0, 224, 168, 0.1);
    padding: 12px;
    border-radius: 8px;
    margin-top: 15px;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.3s ease;
  }

  .file-info.show {
    display: flex;
  }

  .file-icon {
    color: #00e0a8;
    font-size: 20px;
  }

  .file-name {
    flex: 1;
    font-size: 14px;
    color: #fff;
    word-break: break-all;
  }

  .file-size {
    font-size: 12px;
    color: #9db0c9;
  }

  /* Mobile Responsive */
  @media (max-width: 768px) {
    body {
      padding: 15px;
      align-items: flex-start;
      min-height: auto;
    }
    
    .container {
      padding: 25px 20px;
      margin: 10px 0;
      max-width: 100%;
    }
    
    h2 {
      font-size: 24px;
    }
    
    .subtitle {
      font-size: 14px;
      margin-bottom: 20px;
    }
    
    .form-row {
      flex-direction: column;
      gap: 0;
    }
    
    .form-group {
      margin-bottom: 18px;
    }
    
    input, select, textarea, .file-input {
      padding: 14px;
      font-size: 16px; /* Better for mobile touch */
    }
    
    .btn {
      padding: 16px;
      font-size: 16px;
    }
    
    .terms-check {
      padding: 15px;
      gap: 10px;
    }
    
    .info-box, .error-box, .success-box {
      font-size: 14px;
      padding: 12px;
      margin-bottom: 18px;
    }
    
    .step-circle {
      width: 35px;
      height: 35px;
      font-size: 16px;
    }
    
    .step-label {
      font-size: 10px;
    }
    
    .form-actions {
      flex-direction: column;
      gap: 10px;
    }
  }

  @media (max-width: 480px) {
    .container {
      padding: 20px 16px;
      border-radius: 20px;
    }
    
    h2 {
      font-size: 22px;
    }
    
    .subtitle {
      font-size: 13px;
    }
    
    input, select, textarea {
      padding: 13px;
    }
    
    .btn {
      padding: 15px;
    }
    
    .footer-link {
      font-size: 13px;
    }
    
    .upload-icon {
      font-size: 36px;
    }
    
    .upload-text {
      font-size: 14px;
    }
    
    .upload-hint {
      font-size: 12px;
    }
  }

  /* Tablet and larger screens */
  @media (min-width: 769px) and (max-width: 1024px) {
    .container {
      max-width: 600px;
      padding: 35px;
    }
  }
</style>
</head>
<body>

<div class="container">
  
  <form method="POST" action="" id="registrationForm" enctype="multipart/form-data">
    <h2>Create Voter Account</h2>
    <div class="subtitle">Register to participate in the upcoming election</div>

    <?php if (!empty($error)): ?>
      <div class="error-box">
        <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="success-box">
        <?php echo $success; ?>
      </div>
    <?php endif; ?>

    <div class="step-navigation">
      <div class="step active" id="step1">
        <div class="step-circle">1</div>
        <div class="step-label">Personal Info</div>
      </div>
      <div class="step" id="step2">
        <div class="step-circle">2</div>
        <div class="step-label">Verification</div>
      </div>
    </div>

    <!-- Step 1: Personal Information -->
    <div class="form-step active" id="formStep1">
      <div class="info-box">
        Please provide your personal information. All fields are required.
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Full Name <span class="required">*</span></label>
          <input type="text" name="full_name" placeholder="Enter your full name" 
                 value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
          <label>Username <span class="required">*</span></label>
          <input type="text" name="username" placeholder="Choose a username" 
                 value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Email <span class="required">*</span></label>
          <input type="email" name="email" placeholder="Enter your email" 
                 value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
          <label>Phone <span class="required">*</span></label>
          <input type="tel" name="phone" placeholder="Enter your phone number" 
                 value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Date of Birth <span class="required">*</span></label>
          <input type="date" name="date_of_birth" 
                 value="<?php echo htmlspecialchars($formData['date_of_birth'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
          <label>Gender <span class="required">*</span></label>
          <select name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male" <?php echo ($formData['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo ($formData['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
            <option value="Other" <?php echo ($formData['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
            <option value="Prefer not to say" <?php echo ($formData['gender'] ?? '') == 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Address <span class="required">*</span></label>
        <textarea name="address" placeholder="Enter your full address (House number, Street, City, State)" required><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" id="nextStep1">
          Next <i class="fas fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <!-- Step 2: Verification Information -->
    <div class="form-step" id="formStep2">
      <div class="info-box">
        Please provide verification documents for identity confirmation.
      </div>

      <div class="form-group">
        <label>National ID Number <span class="required">*</span></label>
        <input type="text" name="national_id" placeholder="Enter your national ID number" 
               value="<?php echo htmlspecialchars($formData['national_id'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label>Verification Document <span class="required">*</span></label>
        <div class="file-upload-area" id="fileUploadArea">
          <div class="upload-icon">
            <i class="fas fa-cloud-upload-alt"></i>
          </div>
          <div class="upload-text">Upload Verification Document</div>
          <div class="upload-hint">
            Drag & drop or click to upload<br>
            PDF, JPG, PNG, DOC (Max 5MB)
          </div>
          <input type="file" 
                 name="verification_document" 
                 class="file-input" 
                 id="verificationDocument"
                 accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                 required>
        </div>
        <div class="file-info" id="fileInfo">
          <i class="fas fa-file file-icon"></i>
          <div class="file-name" id="fileName">No file selected</div>
          <div class="file-size" id="fileSize"></div>
        </div>
      </div>

      <div class="info-box">
        <strong>Document Requirements:</strong><br>
        1. Clear photo/scan of government-issued ID<br>
        2. Document must be valid and not expired<br>
        3. All information must be clearly visible<br>
        4. Document will be verified by administrators
      </div>

      <div class="info-box">
        <strong>Note:</strong> Your password will be automatically generated and sent to your email 
        once your registration is approved by the administrator.
      </div>

      <div class="terms-check">
        <input type="checkbox" name="terms" id="terms" required>
        <label for="terms">
          I agree to the <a href="#" onclick="showTerms()">Terms and Conditions</a> and confirm that all information provided is accurate.
        </label>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" id="prevStep2">
          <i class="fas fa-arrow-left"></i> Previous
        </button>
        <button class="btn" type="submit" id="submitBtn">
          <i class="fas fa-paper-plane"></i> Submit Registration
        </button>
      </div>
    </div>

    <div class="footer-link">
      Already have an account? <a href="index.php">Login here</a>
    </div>
  </form>

</div>

<!-- Terms Modal -->
<div id="termsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#1a1d29; padding:30px; border-radius:20px; max-width:600px; max-height:80vh; overflow-y:auto; width:100%;">
    <h3 style="color:#00e0a8; margin-top:0;">Terms and Conditions</h3>
    <div style="color:#ccc; font-size:14px; line-height:1.6;">
      <p>By registering for the E-Voting System, you agree to the following terms:</p>
      <ul>
        <li>You confirm that all information provided is accurate and truthful.</li>
        <li>You must be at least 18 years old to register as a voter.</li>
        <li>Each individual may register only once.</li>
        <li>You agree to comply with all election rules and regulations.</li>
        <li>Your registration is subject to administrative verification.</li>
        <li>You will be notified via email once your registration is approved.</li>
        <li>Login credentials will be sent to your registered email upon approval.</li>
        <li>You are responsible for keeping your login credentials secure.</li>
        <li>Any fraudulent activity will result in account termination.</li>
      </ul>
      <p>For more information, contact the election administration office.</p>
    </div>
    <button onclick="closeTerms()" style="margin-top:20px; padding:10px 20px; background:#00e0a8; color:#000; border:none; border-radius:10px; font-weight:600; cursor:pointer;">I Understand</button>
  </div>
</div>

<script>
  // Form Step Management
  let currentStep = 1;
  const totalSteps = 2;
  
  function showStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(step => {
      step.classList.remove('active');
    });
    
    // Show current step
    document.getElementById(`formStep${stepNumber}`).classList.add('active');
    
    // Update step navigation
    document.querySelectorAll('.step').forEach((step, index) => {
      step.classList.remove('active');
      if (index + 1 === stepNumber) {
        step.classList.add('active');
      } else if (index + 1 < stepNumber) {
        step.classList.add('completed');
      } else {
        step.classList.remove('completed');
      }
    });
    
    currentStep = stepNumber;
  }
  
  // Next button for step 1
  document.getElementById('nextStep1').addEventListener('click', function() {
    // Validate step 1
    const step1Inputs = document.querySelectorAll('#formStep1 input, #formStep1 select, #formStep1 textarea');
    let isValid = true;
    
    step1Inputs.forEach(input => {
      if (input.hasAttribute('required') && !input.value.trim()) {
        isValid = false;
        input.style.borderColor = '#ff6b6b';
      } else {
        input.style.borderColor = '';
      }
    });
    
    if (isValid) {
      showStep(2);
    } else {
      alert('Please fill in all required fields before proceeding.');
    }
  });
  
  // Previous button for step 2
  document.getElementById('prevStep2').addEventListener('click', function() {
    showStep(1);
  });
  
  // File upload handling
  const fileInput = document.getElementById('verificationDocument');
  const fileUploadArea = document.getElementById('fileUploadArea');
  const fileInfo = document.getElementById('fileInfo');
  const fileName = document.getElementById('fileName');
  const fileSize = document.getElementById('fileSize');
  
  // Update file info when file is selected
  fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
      const file = this.files[0];
      const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
      
      fileName.textContent = file.name;
      fileSize.textContent = `${fileSizeMB} MB`;
      fileInfo.classList.add('show');
      
      // Validate file type
      const allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
      const fileExt = file.name.split('.').pop().toLowerCase();
      
      if (!allowedTypes.includes(fileExt)) {
        alert('Invalid file type. Please upload PDF, JPG, PNG, or DOC files.');
        this.value = '';
        fileInfo.classList.remove('show');
      }
      
      // Validate file size
      if (file.size > 5 * 1024 * 1024) {
        alert('File size too large. Maximum 5MB allowed.');
        this.value = '';
        fileInfo.classList.remove('show');
      }
    } else {
      fileInfo.classList.remove('show');
    }
  });
  
  // Drag and drop functionality
  fileUploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
  });
  
  fileUploadArea.addEventListener('dragleave', function() {
    this.classList.remove('dragover');
  });
  
  fileUploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    
    if (e.dataTransfer.files.length) {
      fileInput.files = e.dataTransfer.files;
      fileInput.dispatchEvent(new Event('change'));
    }
  });
  
  // Form validation
  const submitBtn = document.getElementById('submitBtn');
  
  document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const terms = document.getElementById('terms').checked;
    
    if (!terms) {
      e.preventDefault();
      alert('Please agree to the Terms and Conditions.');
      return false;
    }
    
    // Age validation
    const dobInput = document.querySelector('input[name="date_of_birth"]');
    if (dobInput.value) {
      const dob = new Date(dobInput.value);
      const minAge = new Date();
      minAge.setFullYear(minAge.getFullYear() - 18);
      
      if (dob > minAge) {
        e.preventDefault();
        alert('You must be at least 18 years old to register.');
        return false;
      }
    }
    
    // File validation
    if (!fileInput.files.length) {
      e.preventDefault();
      alert('Please upload a verification document.');
      return false;
    }
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    submitBtn.disabled = true;
    
    return true;
  });

  // Terms modal functions
  function showTerms() {
    document.getElementById('termsModal').style.display = 'flex';
  }

  function closeTerms() {
    document.getElementById('termsModal').style.display = 'none';
  }

  // Close modal when clicking outside
  document.getElementById('termsModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeTerms();
    }
  });

  // Age calculation for date input
  const dobInput = document.querySelector('input[name="date_of_birth"]');
  const maxDate = new Date();
  maxDate.setFullYear(maxDate.getFullYear() - 18);
  const maxDateString = maxDate.toISOString().split('T')[0];
  
  // Set max date for date picker
  dobInput.max = maxDateString;
  
  // Auto-hide messages after 5 seconds
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
      }, 5000);
    }
  }, 5000);

  // Add some interactive effects
  document.querySelectorAll('input, select, textarea').forEach(input => {
    input.addEventListener('focus', function() {
      this.style.transform = 'translateY(-2px)';
    });
    
    input.addEventListener('blur', function() {
      this.style.transform = 'translateY(0)';
    });
  });

  // Prevent form resubmission on page refresh
  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }

  // Mobile-specific optimizations
  if ('ontouchstart' in window) {
    // Increase tap targets on mobile
    document.querySelectorAll('input, select, textarea').forEach(el => {
      el.style.minHeight = '44px'; // Apple's recommended minimum touch target size
    });
    
    document.querySelectorAll('.btn').forEach(btn => {
      btn.style.minHeight = '44px';
    });
    
    document.querySelectorAll('.step-circle').forEach(circle => {
      circle.style.minWidth = '44px';
      circle.style.minHeight = '44px';
    });
  }

  // Initialize form
  showStep(1);
</script>

</body>
</html>