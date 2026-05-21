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
  <link rel="stylesheet" href="global_index.css"/>
  <link rel="stylesheet" href="index.css"/>
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
            <!-- INSERT: Nav logo image URL below (dark logo on light background) -->
            <img
              class="listahub-logo-3-1"
              loading="lazy"
              alt="ListaHub Logo"
              src="pics_icons/ListaHub-logo-3-1@2x.png"
            />
          </div>
        </div>
        <div class="menu">
          <!-- Log in button — stays on this page (active state) -->
          <a class="menus" href="index.php">
            <div class="log-in">Log in</div>
          </a>
          <!-- Sign up button — goes to signup.php -->
          <a class="menus2" href="signup.php">
            <div class="sign-up">Sign up</div>
          </a>
        </div>
      </nav>

      <!-- ── MAIN CONTENT ── -->
      <main class="context-parent">

        <!-- Left placeholder (brand text positioned absolutely) -->
        <div class="context"></div>

        <!-- ── AUTH CARD ── -->
        <section class="sign-up-card">
          <form class="logos-parent" method="POST" action="login.php">

            <!-- Card logo -->
            <div class="logos2">
              <!-- INSERT: Card logo image URL below (logo for the card, e.g. yellow/cream version) -->
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
                  <!-- INSERT: User/person icon SVG or image URL below -->
                  <img class="vector-icon" alt="user icon" src="pics_icons/Vector.svg"/>
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
                  <!-- INSERT: Lock/password icon SVG or image URL below -->
                  <img class="vector-icon2" alt="lock icon" src="pics_icons/Vector1.svg"/>
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
              <div class="text">
                <label>
                  <input type="checkbox" name="terms" required style="margin-right:5px;accent-color:#ebd665;"/>
                  <span class="i-agree-to">I agree to ListaHub's </span>
                  <b class="i-agree-to"><a href="#" style="color:inherit;text-decoration:none;">Terms of Service &amp; Privacy Policy.</a></b>
                </label>
              </div>

              <!-- Log in — submits form; login.php should redirect to dashboard.php on success -->
              <button class="buttons" type="submit">
                <div class="text2">Log in</div>
              </button>

              <div class="text3">
                <span class="dont-have-an">Don't have an account? </span>
                <!-- Sign up link — goes to signup.php -->
                <a class="register" href="signup.php">Register</a>
              </div>
            </div>

          </form>
        </section>
      </main>

      <!-- Brand title (positioned absolutely over background) -->
      <h1 class="listahub">ListaHub</h1>

      <!-- Brand description (positioned absolutely) -->
      <div class="listahub-is-a">
        ListaHub is a database-driven Point-of-Sale (POS) and Inventory
        Management System developed to provide an efficient, organized, and
        automated solution for managing the daily operations of small
        businesses, particularly sari-sari stores and mini groceries. It
        includes secure user authentication, product and category management,
        inventory and stock monitoring, stock-in and stock-out recording,
        sales and transaction processing, customer debt management, and csv
        import and export.
      </div>

    </section>
  </div>
</body>
</html>