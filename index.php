<?php
// index.php - Login Page
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
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
</body>
</html>
