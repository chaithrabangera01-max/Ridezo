<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "ridezo");
$error = "";
$success = "";

// Pick up flash alerts set by reset-password.php (or any page using showAlert)
if (isset($_SESSION['alert'])) {
    $flash = $_SESSION['alert'];
    unset($_SESSION['alert']);
    if ($flash['type'] === 'success') {
        $success = $flash['message'];
    } else {
        $error = $flash['message'];
    }
}

// ─── LOGOUT ───────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// ─── SIGNUP LOGIC ─────────────────────────────────────────────────────────────
if (isset($_POST['signup'])) {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $pass    = $_POST['password'];
    $dob     = $_POST['dob'];
    $license = mysqli_real_escape_string($conn, strtoupper(trim($_POST['license_number'])));

    // Age check
    $today     = new DateTime();
    $birthdate = new DateTime($dob);
    $age       = $today->diff($birthdate)->y;

    // Password strength
    $uppercase    = preg_match('@[A-Z]@', $pass);
    $lowercase    = preg_match('@[a-z]@', $pass);
    $specialChars = preg_match('@[^\w]@', $pass);

    // License format: letters + digits, 6–20 chars (adjust regex to your country format)
    // License format (India): SS-RR-YYYY-NNNNNNN
    $validLicense = preg_match('/^[A-Z]{2}[0-9]{2}[0-9]{4}[0-9]{7}$|(?=.*[ -])[A-Z0-9 -]{15,18}$/', $license);

    // Check duplicate email
    $check_email = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($check_email, "s", $email);
    mysqli_stmt_execute($check_email);
    mysqli_stmt_store_result($check_email);

    // Check duplicate license
    $check_license = mysqli_prepare($conn, "SELECT user_id FROM users WHERE license_number = ?");
    mysqli_stmt_bind_param($check_license, "s", $license);
    mysqli_stmt_execute($check_license);
    mysqli_stmt_store_result($check_license);

    if ($age < 20) {
        $error = "You must be at least 20 years old to register.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (mysqli_stmt_num_rows($check_email) > 0) {
        $error = "An account already exists with this email. Please login.";
    } elseif (!$validLicense) {
        $error = "Invalid Indian License format. Use e.g. KA-01-2023-1234567";
    } elseif (mysqli_stmt_num_rows($check_license) > 0) {
        $error = "This license number is already registered.";
    } elseif (!$uppercase || !$lowercase || !$specialChars || strlen($pass) < 6) {
        $error = "Password must be 6+ chars with uppercase, lowercase & special character.";
    } else {
        // ── Handle profile photo upload ──────────────────────────────────────
        $photo_path = null;
        if (!empty($_FILES['profile_photo']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $file_type     = $_FILES['profile_photo']['type'];
            $file_size     = $_FILES['profile_photo']['size'];
            $max_size      = 2 * 1024 * 1024; // 2MB

            if (!in_array($file_type, $allowed_types)) {
                $error = "Profile photo must be JPG, PNG or WEBP.";
            } elseif ($file_size > $max_size) {
                $error = "Profile photo must be under 2MB.";
            } else {
                $upload_dir = "uploads/profiles/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $ext        = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $photo_name = uniqid('user_', true) . '.' . $ext;
                $photo_path = $upload_dir . $photo_name;

                if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $photo_path)) {
                    $error = "Failed to upload photo. Try again.";
                    $photo_path = null;
                }
            }
        }

        if (!$error) {
            $hashed_pass = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (name, email, password, date_of_birth, license_number, profile_photo, role)
                 VALUES (?, ?, ?, ?, ?, ?, 'Customer')"
            );
            mysqli_stmt_bind_param($stmt, "ssssss",
                $name, $email, $hashed_pass, $dob, $license, $photo_path
            );

            if (mysqli_stmt_execute($stmt)) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

// ─── LOGIN LOGIC ──────────────────────────────────────────────────────────────
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $pass  = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($pass, $row['password'])) {
            // Regenerate session ID to prevent fixation attacks
            session_regenerate_id(true);
            $_SESSION['user_id']       = $row['user_id'];
            $_SESSION['name']          = $row['name'];
            $_SESSION['email']         = $row['email'];
            $_SESSION['profile_photo'] = $row['profile_photo'];
            $_SESSION['license']       = $row['license_number'];
            $_SESSION['role']          = $row['role'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "No account found with this email address.";
    }
}

// Default tab: show signup form if ?tab=signup
$default_tab = isset($_GET['tab']) && $_GET['tab'] === 'signup' ? 'signup' : 'login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ridezo | Login / Sign Up</title>
    
    <!-- ✅ TAILWIND FIRST -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- ✅ ALL CUSTOM STYLES INLINE (properly formatted) -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 500px;
        }

        .auth-card {
            background: white;
            border-radius: 2rem;
            box-shadow: 0 30px 80px -10px rgba(0, 0, 0, 0.12);
            border: 1px solid #f1f5f9;
            padding: 2.5rem;
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            font-size: 2.5rem;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .logo-text {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.02em;
        }

        .tab-switcher {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .tab-btn {
            flex: 1;
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f1f5f9;
            color: #64748b;
        }

        .tab-btn.active {
            background: #2563eb;
            color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }

        .tab-btn:hover:not(.active) {
            background: #e2e8f0;
        }

        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .form-input:-webkit-autofill,
        .form-input:-webkit-autofill:hover, 
        .form-input:-webkit-autofill:focus, 
        .form-input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px white inset !important;
            -webkit-text-fill-color: #1e293b !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: #f8fafc;
        }

        .form-input::placeholder {
            color: #cbd5e1;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            pointer-events: none;
        }

        .form-input:focus ~ .input-icon,
        .form-input:not(:placeholder-shown) ~ .input-icon {
            color: #2563eb;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #2563eb;
        }

        .submit-btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #2563eb;
            color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin-top: 1.25rem;
        }

        .submit-btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .forgot-link {
            text-align: right;
            margin-bottom: 1.25rem;
        }

        .forgot-link a {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .forgot-link a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .divider-line {
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .divider-text {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .switch-form-btn {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #2563eb;
            background: white;
            color: #2563eb;
            border-radius: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .switch-form-btn:hover {
            background: #eff6ff;
            transform: translateY(-2px);
        }

        .toast {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            animation: toastSlideIn 0.4s ease-out;
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        .toast.error {
            border-left: 4px solid #ef4444;
        }

        .toast.success {
            border-left: 4px solid #22c55e;
        }

        .toast-content {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .toast-icon {
            font-size: 1.5rem;
        }

        .toast.error .toast-icon {
            color: #ef4444;
        }

        .toast.success .toast-icon {
            color: #22c55e;
        }

        .profile-photo-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed #e2e8f0;
            border-radius: 1rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.25rem;
            background: #f8fafc;
        }

        .profile-photo-upload:hover {
            border-color: #2563eb;
            background: #eff6ff;
        }

        .profile-photo-upload img {
            max-width: 100%;
            max-height: 120px;
            border-radius: 0.5rem;
        }

        .photo-placeholder {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }

        .photo-text {
            font-size: 0.875rem;
            color: #64748b;
            text-align: center;
        }

        .strength-bar {
            height: 0.375rem;
            background: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 0.5rem;
            display: none;
        }

        .strength-bar.show {
            display: block;
        }

        .strength-fill {
            height: 100%;
            border-radius: 9999px;
            transition: all 0.5s ease;
            width: 0%;
        }

        .strength-text {
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 0.25rem;
            display: none;
        }

        .strength-text.show {
            display: block;
        }

        .terms-text {
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: center;
            line-height: 1.5;
        }

        .terms-text a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .terms-text a:hover {
            text-decoration: underline;
        }

        .hidden {
            display: none !important;
        }
    
        /* Global Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main, var(--bg, #020617)); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color, var(--accent, #22c55e)); }

</style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo-icon"><img src="../assets/ridezo_logo.png" alt="Ridezo Logo" style="height: 50px; width: auto;"></div>
                <div class="logo-text">Ridezo</div>
            </div>

            <!-- Tab Switcher -->
            <div class="tab-switcher">
                <button class="tab-btn active" id="tab-login" onclick="switchTab('login')">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
                <button class="tab-btn" id="tab-signup" onclick="switchTab('signup')">
                    <i class="fas fa-user-plus mr-2"></i>Sign Up
                </button>
            </div>

            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div id="toast" class="toast error">
                    <div class="toast-content">
                        <i class="fas fa-times-circle toast-icon"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div id="toast" class="toast success">
                    <div class="toast-content">
                        <i class="fas fa-check-circle toast-icon"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form id="form-login" method="POST" class="<?php echo $default_tab !== 'login' ? 'hidden' : ''; ?>">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email Address" required class="form-input">
                    <i class="fas fa-envelope input-icon"></i>
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="loginPass" placeholder="Password" required class="form-input">
                    <i class="fas fa-lock input-icon"></i>
                    <button type="button" onclick="togglePass('loginPass', 'eyeLogin')" class="password-toggle">
                        <i class="fas fa-eye" id="eyeLogin"></i>
                    </button>
                </div>

                <div class="forgot-link">
                    <a href="../app/forgot-password.php">Forgot your password?</a>
                </div>

                <button type="submit" name="login" class="submit-btn">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>

                <div class="divider">
                    <div class="divider-line"></div>
                    <span class="divider-text">New to Ridezo?</span>
                    <div class="divider-line"></div>
                </div>

                <button type="button" onclick="switchTab('signup')" class="switch-form-btn">
                    <i class="fas fa-user-plus mr-2"></i>Create Account
                </button>
            </form>

            <!-- SIGNUP FORM -->
            <form id="form-signup" method="POST" enctype="multipart/form-data" class="<?php echo $default_tab === 'signup' ? '' : 'hidden'; ?>">
                <!-- Profile Photo Upload -->
                <div class="form-group">
                    <label for="profile_photo" class="profile-photo-upload">
                        <div class="photo-placeholder">
                            <i class="fas fa-camera"></i>
                        </div>
                        <img id="photoPreview" src="" style="display:none; width:100px; height:100px;">
                        <div id="photoPlaceholder">
                            <div class="photo-text">Click to upload profile photo</div>
                        </div>
                    </label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewPhoto(this)">
                    <p class="text-xs text-gray-400 mt-2">JPG, PNG or WEBP • Max 2MB</p>
                </div>

                <!-- Full Name -->
                <div class="form-group">
                    <input type="text" name="name" placeholder="Full Name" required class="form-input">
                    <i class="fas fa-user input-icon"></i>
                </div>

                <!-- Date of Birth -->
                <div class="form-group">
                    <input type="date" name="dob" required class="form-input" max="<?php echo date('Y-m-d', strtotime('-20 years')); ?>">
                    <i class="fas fa-calendar-alt input-icon"></i>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email Address" required class="form-input">
                    <i class="fas fa-envelope input-icon"></i>
                </div>

                <!-- License Number -->
                <div class="form-group">
                    <input type="text" name="license_number" placeholder="License Number (e.g. KA-01-2023-1234567)" required maxlength="18" pattern="^[A-Z]{2}[0-9]{2}[ -]?[0-9]{4}[ -]?[0-9]{7}$" title="Enter valid Indian DL number (e.g. KA-01-2023-1234567)" class="form-input uppercase" style="text-transform: uppercase;">
                    <i class="fas fa-id-card input-icon"></i>
                    <p class="text-xs text-gray-400 mt-1 ml-1">
                        <i class="fas fa-shield-alt" style="color: #3b82f6;"></i>
                        Used for identity verification only
                    </p>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <input type="password" name="password" id="signupPass" placeholder="Password (A, a, #, 6+ chars)" required class="form-input">
                    <i class="fas fa-lock input-icon"></i>
                    <button type="button" onclick="togglePass('signupPass', 'eyeSignup')" class="password-toggle">
                        <i class="fas fa-eye" id="eyeSignup"></i>
                    </button>
                </div>

                <!-- Password Strength Bar -->
                <div class="strength-bar" id="strengthBar">
                    <div class="strength-fill" id="strengthFill"></div>
                </div>
                <p class="strength-text" id="strengthText"></p>

                <button type="submit" name="signup" class="submit-btn">
                    <i class="fas fa-user-plus mr-2"></i>Create Account
                </button>

                <div class="terms-text">
                    By signing up, you agree to our
                    <a href="#">Terms</a> &
                    <a href="#">Privacy Policy</a>
                </div>

                <div class="divider">
                    <div class="divider-line"></div>
                    <span class="divider-text">Have an account?</span>
                    <div class="divider-line"></div>
                </div>

                <button type="button" onclick="switchTab('login')" class="switch-form-btn">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In Instead
                </button>
            </form>
        </div>
    </div>

    <!-- ✅ JAVASCRIPT -->
    <script>
        // ── Tab Switching ──────────────────────────────────────────────────
        function switchTab(tab) {
            document.getElementById('form-login').classList.toggle('hidden', tab !== 'login');
            document.getElementById('form-signup').classList.toggle('hidden', tab !== 'signup');
            document.getElementById('tab-login').classList.toggle('active', tab === 'login');
            document.getElementById('tab-signup').classList.toggle('active', tab === 'signup');
        }

        // ── Profile Photo Preview ─────────────────────────────────────────
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('photoPreview');
                    const placeholder = document.getElementById('photoPlaceholder');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // ── Password Visibility Toggle ─────────────────────────────────────
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // ── Password Strength ──────────────────────────────────────────────
        document.getElementById('signupPass')?.addEventListener('input', function() {
            const val = this.value;
            const bar = document.getElementById('strengthBar');
            const fill = document.getElementById('strengthFill');
            const txt = document.getElementById('strengthText');

            bar.classList.add('show');
            txt.classList.add('show');

            let score = 0;
            if (val.length >= 6) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[a-z]/.test(val)) score++;
            if (/[^\w]/.test(val)) score++;
            if (val.length >= 10) score++;

            const levels = [
                { label: 'Very Weak', color: '#ef4444', width: '20%' },
                { label: 'Weak', color: '#f97316', width: '40%' },
                { label: 'Fair', color: '#eab308', width: '60%' },
                { label: 'Strong', color: '#22c55e', width: '80%' },
                { label: 'Very Strong', color: '#16a34a', width: '100%' }
            ];
            const lvl = levels[Math.min(score, 4)];
            fill.style.width = lvl.width;
            fill.style.background = lvl.color;
            txt.textContent = lvl.label;
            txt.style.color = lvl.color;
        });

        // ── Auto-dismiss Toast ─────────────────────────────────────────────
        const toast = document.getElementById('toast');
        if (toast) {
            setTimeout(() => {
                toast.style.transition = 'all 0.4s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translate(-50%, -50%) scale(0.9)';
                setTimeout(() => toast.remove(), 400);
            }, 4000);
        }

        // ── Open signup tab if PHP error was on signup ─────────────────────
        <?php if($error && isset($_POST['signup'])): ?>
            switchTab('signup');
        <?php endif; ?>
    </script>
</body>
</html>