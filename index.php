<?php
// index.php - Login Page
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
$loginError = isset($_GET['error']) ? htmlspecialchars(trim($_GET['error'])) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Log In – ListaHub</title>
  <link rel="stylesheet" href="css/global_index.css"/>
  <link rel="stylesheet" href="css/index.css"/>
  <link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600;1,700&display=swap"
  />
  <link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@900&display=swap"
  />
</head>
<body>
  <div class="login-page">
    <section class="body-login">

      <!-- ── NAVBAR ── -->
      <nav class="nav">
        <div class="logo">
          <div class="logos">
            <img
              class="listahub-logo-3-1"
              loading="lazy"
              alt="ListaHub Logo"
              src="pics_icons/ListaHub-logo-3-1@2x.png"
            />
          </div>
        </div>
        <div class="menu">
          <a class="menus" href="index.php">
            <div class="log-in">Log in</div>
          </a>
          <a class="menus2" href="signup.php">
            <div class="sign-up">Sign up</div>
          </a>
        </div>
      </nav>

      <!-- ── MAIN CONTENT ── -->
      <main class="content-area">
        <!-- Left placeholder (brand text positioned absolutely) -->
        <div class="context">
          <h1 class="listahub-title">ListaHub</h1>
            <div class="listahub-desc">
              ListaHub is a database-driven Point-of-Sale (POS) and Inventory
              Management System developed to provide an efficient, organized, and
              automated solution for managing the daily operations of small
              businesses, particularly sari-sari stores and mini groceries. It
              includes secure user authentication, product and category management,
              inventory and stock monitoring, stock-in and stock-out recording,
              sales and transaction processing, customer debt management, and csv
              import and export.
            </div>
        </div>

        <!-- ── AUTH CARD ── -->
        <section class="sign-up-card">
          <form class="logos-parent" method="POST" action="login.php">
            <!-- Card logo -->
            <div class="logos2">
              <img
                class="listahub-logo-1"
                loading="lazy"
                alt="ListaHub"
                src="pics_icons/ListaHub-Logo-1@2x.png"
              />
            </div>

            <!-- Email / Username field -->
            <div class="emailiiactive">
              <div class="stateactive">
                <label class="label" for="login">Email or username</label>
                <div class="input-field">
                  <img
                    class="vector-icon"
                    alt="user icon"
                    src="pics_icons/Vector.svg"
                  />
                  <input
                    class="placeholder"
                    id="login"
                    name="login"
                    placeholder="listahub@gmail.com"
                    type="text"
                    required
                  />
                </div>
              </div>
            </div>

            <!-- Password field -->
            <div class="emailiiactive">
              <div class="stateactive">
                <label class="label" for="password">Password</label>
                <div class="input-field">
                  <img
                    class="vector-icon2"
                    alt="lock icon"
                    src="pics_icons/Vector1.svg"
                  />
                  <input
                    class="placeholder2"
                    id="password"
                    name="password"
                    placeholder="enter your password"
                    type="password"
                    required
                  />
                </div>
              </div>
            </div>

            <!-- Terms, button, footer -->
            <div class="section">
              <button class="buttons" type="submit">
                <div class="text2">Log in</div>
              </button>

              <div class="text3">
                <span class="dont-have-an">Don't have an account? </span>
                <a class="register" href="signup.php">Register</a>
              </div>
            </div>
          </form>
        </section>
      </main>

      
    </section>
  </div>

  <!-- ── LOGIN ERROR MODAL ── -->
  <?php if ($loginError): ?>
  <div class="login-error-overlay" id="loginErrorOverlay">
    <div class="login-error-card" id="loginErrorCard">
      <div class="login-error-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="#3e2c23" stroke-width="2"/>
          <path d="M12 7v5" stroke="#3e2c23" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="16.5" r="1" fill="#3e2c23"/>
        </svg>
      </div>
      <p class="login-error-msg"><?= $loginError ?></p>
      <button class="login-error-btn" onclick="closeLoginError()">OK</button>
    </div>
  </div>
  <style>
    .login-error-overlay {
      display: flex;
      position: fixed;
      inset: 0;
      z-index: 9999;
      background: linear-gradient(
        180deg,
        rgba(235, 233, 225, 0.55),
        rgba(169, 174, 181, 0.55)
      );
      backdrop-filter: blur(51px);
      -webkit-backdrop-filter: blur(51px);
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-error-card {
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
      width: 100%;
      max-width: 360px;
      background: linear-gradient(
          146.01deg,
          rgba(253, 253, 253, 0.58),
          rgba(254, 246, 227, 0.49) 49.52%,
          rgba(255, 244, 216, 0.6)
        ),
        linear-gradient(rgba(252, 248, 238, 0.2), rgba(252, 248, 238, 0.2));
      border: 2px solid #3e2c23;
      border-radius: 15px;
      padding: 28px 24px 22px;
      box-shadow:
        36px 30px 13px transparent,
        23px 19px 12px rgba(62, 44, 35, 0.01),
        13px 11px 10px rgba(62, 44, 35, 0.05),
        6px 5px 8px rgba(62, 44, 35, 0.09),
        1px 1px 4px rgba(62, 44, 35, 0.1);
      backdrop-filter: blur(20.6px);
      -webkit-backdrop-filter: blur(20.6px);
      animation: loginErrIn 0.2s ease;
    }
    @keyframes loginErrIn {
      from { opacity: 0; transform: scale(0.92); }
      to   { opacity: 1; transform: scale(1); }
    }
    .login-error-icon {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-error-msg {
      margin: 0;
      font-family: 'Inter', sans-serif;
      font-size: 15px;
      font-weight: 600;
      color: #3e2c23;
      text-align: center;
      line-height: 1.4;
    }
    .login-error-btn {
      margin-top: 4px;
      padding: 9px 36px;
      background: #3e2c23;
      color: #fff;
      font-family: 'Inter', sans-serif;
      font-size: 14px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.15s;
    }
    .login-error-btn:hover {
      background: #5a3e30;
    }
  </style>
  <script>
    function closeLoginError() {
      var overlay = document.getElementById('loginErrorOverlay');
      if (overlay) overlay.style.display = 'none';
    }
    // Close on backdrop click
    document.getElementById('loginErrorOverlay').addEventListener('click', function(e) {
      if (e.target === this) closeLoginError();
    });
  </script>
  <?php endif; ?>
</body>
</html>
