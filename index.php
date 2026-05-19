<?php
require_once 'config/constants.php';
require_once 'config/https.php';
require_once 'config/database.php';
require_once 'core/SecurityService.php';
require_once 'core/AuditLog.php';

$role = $_GET['role'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $postRole = $_POST['role'] ?? '';

    // Basic validation: username must be alphanumeric with at least one letter; password min length 8
    if (!preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]+$/', $username)) {
        $error = 'Invalid username. Use letters or letters+numbers; cannot be numeric-only.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
    
    $db = new Database();
    $conn = $db->getConnection();
    $sec = new SecurityService();

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $sec->verifyPassword($password, $user['passwordHash'])) {
        if ($postRole && $postRole !== $user['role']) {
            $error = "Role mismatch";
        } else {
            $_SESSION['userId'] = $user['userId'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            $audit = new AuditLog();
            $audit->log($user['userId'], 'LOGIN_SUCCESS', $user['userId']);

            if ($user['role'] == 'ADMIN') header("Location: modules/admin/dashboard.php");
            elseif ($user['role'] == 'DONOR') header("Location: modules/donor/dashboard.php");
            else header("Location: modules/recipient/dashboard.php");
            exit;
        }
    } else {
        $error = "Invalid credentials";
    }
    }
}

$currentPage = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KNBTS - Secure Blood Banking System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>🩸 KNBTS</h1>
            <nav>
                <a href="?page=home" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">Home</a>
                <a href="?page=about" class="<?php echo $currentPage === 'about' ? 'active' : ''; ?>">About</a>
                <a href="?page=services" class="<?php echo $currentPage === 'services' ? 'active' : ''; ?>">Services</a>
            </nav>
        </div>
    </header>

    <?php if ($currentPage === 'home'): ?>
    <!-- HOME PAGE -->
    <section class="page-section active">
        <div class="page-area">
            <div id="myCarousel" class="carousel slide" data-ride="carousel">
                <!-- Indicators -->
                <ol class="carousel-indicators">
                    <li data-target="#myCarousel" data-slide-to="0" class="active"></li>
                    <li data-target="#myCarousel" data-slide-to="1"></li>
                    <li data-target="#myCarousel" data-slide-to="2"></li>
                </ol>

                <!-- Wrapper for slides -->
                <div class="carousel-inner">
                    <div class="item active">
                        <div class="fill" style="background-image:url('assets/images/s11.jpeg');"></div>
                        <div class="carousel-caption"></div>
                    </div>
                    <div class="item">
                        <div class="fill" style="background-image:url('assets/images/s2.jpg');"></div>
                        <div class="carousel-caption"></div>
                    </div>
                    <div class="item">
                        <div class="fill" style="background-image:url('assets/images/s3.jpg');"></div>
                        <div class="carousel-caption"></div>
                    </div>
                </div>

                <!-- Left and right controls -->
                <a class="left carousel-control" href="#myCarousel" data-slide="prev">
                    <span class="glyphicon glyphicon-chevron-left"></span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="right carousel-control" href="#myCarousel" data-slide="next">
                    <span class="glyphicon glyphicon-chevron-right"></span>
                    <span class="sr-only">Next</span>
                </a>
            </div>
            <div class="page-text">
                <h2>Secure Blood Banking System</h2>
                <p>Connecting blood donors with hospitals for life-saving transfusions. Our platform uses AES-256 encryption to keep data safe while ensuring seamless matching between donors, blood banks, and recipients.</p>
                <p class="highlight-text">Join our network of heroes and help save lives today.</p>
            </div>
        </div>
    </section>

    <!-- GET STARTED SECTION - Only on Home Page -->
    <section class="get-started-section">
        <div class="container">
            <div class="section-header">
                <h2>Get Started</h2>
                <p>Choose your role to access the system</p>
            </div>

            <div class="role-cards">
                <!-- Donor Card -->
                <div class="role-card">
                    <div class="role-icon donor-icon">🩸</div>
                    <h3>Donor</h3>
                    <p>Register as a blood donor, track your donations, and help save lives.</p>
                    <div class="role-actions">
                        <button type="button" onclick="showLoginDropdown('DONOR')" class="btn-login">Login</button>
                        <a href="register_user.php?role=DONOR" class="btn-register">Register</a>
                    </div>
                </div>

                <!-- Recipient Card -->
                <div class="role-card">
                    <div class="role-icon recipient-icon">🏥</div>
                    <h3>Recipient</h3>
                    <p>Request blood, track your requests, and connect with donors.</p>
                    <div class="role-actions">
                        <button type="button" onclick="showLoginDropdown('RECIPIENT')" class="btn-login">Login</button>
                        <a href="register_user.php?role=RECIPIENT" class="btn-register">Register</a>
                    </div>
                </div>

                <!-- Staff Card -->
                <div class="role-card">
                    <div class="role-icon staff-icon">👨‍💼</div>
                    <h3>Staff</h3>
                    <p>Manage inventory, approve requests, and oversee operations.</p>
                    <div class="role-actions">
                        <button type="button" onclick="showLoginDropdown('ADMIN')" class="btn-login">Staff Login</button>
                        <span class="staff-note">Staff accounts are pre-created</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blood Donation Scene Section -->
    <section class="blood-donation-scene">
        <div class="container">
            <div class="scene-content">
                <div class="scene-image">
                    <img src="assets/images/s11.jpeg" alt="Blood donation process - donor giving blood">
                </div>
                <div class="scene-text">
                    <h2>Your Donation Saves Lives</h2>
                    <p>Every blood donation can save up to three lives. When you donate blood, you provide a lifeline to patients undergoing surgery, cancer treatment, trauma care, and those with chronic blood disorders.</p>
                    <p>Our secure system ensures that your generous contribution reaches those who need it most, while keeping your personal information completely protected.</p>
                    <div class="stats-row">
                        <div class="stat-item">
                            <span class="stat-number">3</span>
                            <span class="stat-label">Lives Saved Per Donation</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">20</span>
                            <span class="stat-label">Minutes to Donate</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">56</span>
                            <span class="stat-label">Days Between Donations</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Preview -->
    <section class="features-preview">
        <div class="container">
            <h2>Why Choose KNBTS?</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="icon">🔐</div>
                    <h4>Secure & Encrypted</h4>
                    <p>AES-256 encryption protects all sensitive donor and patient data</p>
                </div>
                <div class="feature-card">
                    <div class="icon">📊</div>
                    <h4>Real-Time Tracking</h4>
                    <p>Track blood requests from submission to approval and use</p>
                </div>
                <div class="feature-card">
                    <div class="icon">🏥</div>
                    <h4>Hospital Ready</h4>
                    <p>Designed for modern healthcare facilities and blood banks</p>
                </div>
                <div class="feature-card">
                    <div class="icon">📱</div>
                    <h4>Easy to Use</h4>
                    <p>Intuitive role-based interface for donors, recipients, and admins</p>
                </div>
            </div>
        </div>
    </section>

    <?php elseif ($currentPage === 'about'): ?>
    <!-- ABOUT PAGE -->
    <section class="page-section active">
        <div class="page-area">
            <div class="page-image">
                <img src="assets/images/s11.jpeg" alt="About KNBTS">
            </div>
            <div class="page-text">
                <h2>About KNBTS</h2>
                <p class="lead">Kenya National Blood Transfusion Service (KNBTS) is dedicated to ensuring a safe, sufficient, and sustainable blood supply for all Kenyans.</p>
            </div>
        </div>
    </section>

    <section class="about-content">
        <div class="container">
            <div class="about-grid">
                <div class="about-card">
                    <h3>🎯 Our Mission</h3>
                    <p>To provide adequate, safe, and quality blood and blood products to all patients in Kenya while ensuring the highest standards of safety and care.</p>
                </div>
                <div class="about-card">
                    <h3>👁️ Our Vision</h3>
                    <p>To be a world-class blood transfusion service with universal access to safe blood and blood products for all patients in Kenya.</p>
                </div>
                <div class="about-card">
                    <h3>💡 Our Innovation</h3>
                    <p>This digital platform represents our commitment to leveraging technology for better healthcare outcomes and streamlined blood banking operations.</p>
                </div>
            </div>

            <div class="about-details">
                <h3>What Makes Our Platform Secure</h3>
                <div class="security-features">
                    <div class="security-item">
                        <span class="security-icon">🔒</span>
                        <div>
                            <h4>Fully Encrypted Data</h4>
                            <p>All donor and recipient personal data is protected with military-grade AES-256 encryption.</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <span class="security-icon">🛡️</span>
                        <div>
                            <h4>Role-Based Access Control</h4>
                            <p>Strict access controls ensure users only see information relevant to their role.</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <span class="security-icon">📋</span>
                        <div>
                            <h4>Complete Audit Logs</h4>
                            <p>Every action is logged for compliance and security tracking purposes.</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <span class="security-icon">⚡</span>
                        <div>
                            <h4>Real-Time Updates</h4>
                            <p>Inventory adjustments and blood request approvals are visible instantly.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="about-workflow">
                <h3>How It Works</h3>
                <div class="workflow-steps">
                    <div class="workflow-step">
                        <div class="step-number">1</div>
                        <h4>Donor Registration</h4>
                        <p>Donors register and create their profiles with health information</p>
                    </div>
                    <div class="workflow-step">
                        <div class="step-number">2</div>
                        <h4>Blood Collection</h4>
                        <p>Safe blood collection at certified centers with quality checks</p>
                    </div>
                    <div class="workflow-step">
                        <div class="step-number">3</div>
                        <h4>Inventory Management</h4>
                        <p>Staff monitor blood inventory levels and manage availability</p>
                    </div>
                    <div class="workflow-step">
                        <div class="step-number">4</div>
                        <h4>Request Processing</h4>
                        <p>Recipients submit requests which are processed and approved</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php elseif ($currentPage === 'services'): ?>
    <!-- SERVICES PAGE -->
    <section class="page-section active">
        <div class="page-area">
            <div class="page-image">
                <img src="assets/images/s3.jpg" alt="Services KNBTS">
            </div>
            <div class="page-text">
                <h2>Our Services</h2>
                <p>Comprehensive blood banking services designed to meet the needs of donors, recipients, and healthcare facilities across Kenya.</p>
            </div>
        </div>
    </section>

    <section class="services-content">
        <div class="container">
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">🩸</div>
                    <h3>Blood Donation</h3>
                    <p>Safe and convenient blood donation services with professional staff and modern equipment. All donors receive health screening and post-donation care.</p>
                    <ul>
                        <li>Mobile donation drives</li>
                        <li>Fixed donation centers</li>
                        <li>Corporate partnerships</li>
                        <li>School and college programs</li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">🔬</div>
                    <h3>Blood Testing</h3>
                    <p>Comprehensive screening of all donated blood units to ensure safety and compatibility.</p>
                    <ul>
                        <li>Blood group typing</li>
                        <li>Infectious disease screening</li>
                        <li>Cross-matching</li>
                        <li>Quality assurance</li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">🏥</div>
                    <h3>Blood Distribution</h3>
                    <p>Efficient distribution network ensuring hospitals have access to the blood products they need, when they need them.</p>
                    <ul>
                        <li>24/7 emergency supply</li>
                        <li>Scheduled deliveries</li>
                        <li>Cold chain maintenance</li>
                        <li>Inventory tracking</li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">📱</div>
                    <h3>Digital Platform</h3>
                    <p>State-of-the-art digital system connecting all stakeholders in the blood banking ecosystem.</p>
                    <ul>
                        <li>Online donor registration</li>
                        <li>Real-time inventory</li>
                        <li>Request management</li>
                        <li>Analytics and reporting</li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">📚</div>
                    <h3>Education & Awareness</h3>
                    <p>Community outreach programs to promote voluntary blood donation and educate the public.</p>
                    <ul>
                        <li>Public awareness campaigns</li>
                        <li>Health education</li>
                        <li>Donor recognition programs</li>
                        <li>Training for healthcare workers</li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">🤝</div>
                    <h3>Research & Development</h3>
                    <p>Continuous improvement of blood banking practices through research and innovation.</p>
                    <ul>
                        <li>Transfusion medicine research</li>
                        <li>Process optimization</li>
                        <li>New technology adoption</li>
                        <li>Best practice sharing</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Login Modal -->
    <div id="loginDropdown" class="login-dropdown">
        <div class="login-dropdown-card">
            <div class="login-dropdown-header">
                <h3 id="dropdownTitle">Role Login</h3>
                <button type="button" class="close-dropdown" onclick="hideLoginDropdown()">✕</button>
            </div>
            <div id="loginErrorContainer" class="error-message" style="display: <?php echo $error ? 'block' : 'none'; ?>;">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <form id="dropdownLoginForm" method="POST" action="index.php?page=<?php echo htmlspecialchars($currentPage); ?>">
                <input type="hidden" name="role" id="roleInput" value="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="login-button">Login</button>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> KNBTS - Kenya National Blood Transfusion Service. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        function showLoginDropdown(role) {
            const dropdown = document.getElementById('loginDropdown');
            const roleInput = document.getElementById('roleInput');
            const title = document.getElementById('dropdownTitle');

            roleInput.value = role;

            if (role === 'DONOR') {
                title.textContent = 'Donor Login';
            } else if (role === 'RECIPIENT') {
                title.textContent = 'Recipient Login';
            } else if (role === 'ADMIN') {
                title.textContent = 'Staff Login';
            }

            dropdown.classList.add('visible');
            document.getElementById('username').focus();
        }

        function hideLoginDropdown() {
            const dropdown = document.getElementById('loginDropdown');
            dropdown.classList.remove('visible');
        }

        window.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideLoginDropdown();
            }
        });

        // Close modal when clicking outside
        document.getElementById('loginDropdown').addEventListener('click', function(e) {
            if (e.target === this) {
                hideLoginDropdown();
            }
        });
    </script>
</body>
</html>
