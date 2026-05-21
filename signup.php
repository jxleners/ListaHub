<?php
/* ============================================================
   signup.php
   Redirect to dashboard if already logged in.
   ============================================================ */
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="initial-scale=1, width=device-width" />
  <title>ListaHub – Sign Up</title>

  <!-- Google Fonts -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@900&display=swap" />

  <!-- Stylesheets -->
  <link rel="stylesheet" href="global_signup.css" />
  <link rel="stylesheet" href="signup.css" />
</head>
<body>

<div class="signup-page">
  <div class="body-signup">

    <!-- ════════════════════════════════════════════════════
         NAVIGATION
         ════════════════════════════════════════════════════ -->
    <header class="nav">

      <div class="logo">
        <!--
          ┌─────────────────────────────────────────────────┐
          │  NAV LOGO IMAGE                                  │
          │  File:  ListaHub-logo-3-1@2x.png                │
          │  Replace src="" with your actual image path.    │
          │  Example: src="./public/ListaHub-logo-3-1@2x.png" │
          └─────────────────────────────────────────────────┘
        -->
        <img
          src="/ListaHub/pics_icons/ListaHub-logo-3-1@2x.png"
          alt="ListaHub"
        />
      </div>

      <nav class="menu">
        <!-- Log in → goes to login.php -->
        <a href="login.php" class="nav-login">Log in</a>

        <!-- Sign up → stays on this page (current page) -->
        <a href="signup.php" class="nav-signup-btn">Sign up</a>
      </nav>

    </header>

    <!-- ════════════════════════════════════════════════════
         MAIN CONTENT
         ════════════════════════════════════════════════════ -->
    <main class="content-area">

      <!-- ── Left: description ── -->
      <div class="context">
        <h1 class="listahub-title">ListaHub</h1>
        <p class="listahub-desc">
          ListaHub is a database-driven Point-of-Sale (POS) and Inventory
          Management System developed to provide an efficient, organized,
          and automated solution for managing the daily operations of small
          businesses, particularly sari-sari stores and mini groceries. It
          includes secure user authentication, product and category
          management, inventory and stock monitoring, stock-in and stock-out
          recording, sales and transaction processing, customer debt
          management, and csv import and export.
        </p>
      </div>

      <!-- ── Right: sign-up card ── -->
      <section class="sign-up-card">
        <!--
          ┌─────────────────────────────────────────────────┐
          │  CARD BACKGROUND IMAGE                           │
          │  File:  sign-up-card@3x.png                     │
          │  Set this in signup.css → .sign-up-card         │
          │  background-image: url('YOUR_PATH_HERE')        │
          └─────────────────────────────────────────────────┘
        -->

        <form class="input-form" method="POST" action="signup_process.php">

          <!-- Card logo -->
          <div class="card-logo-wrap">
            <!--
              ┌───────────────────────────────────────────────┐
              │  CARD LOGO IMAGE                               │
              │  File:  ListaHub-Logo-1@2x.png                │
              │  Replace src="" with your actual image path.  │
              │  Example: src="./public/ListaHub-Logo-1@2x.png" │
              └───────────────────────────────────────────────┘
            -->
            <img
              src="/ListaHub/pics_icons/ListaHub-Logo-1@2x.png"
              alt="ListaHub"
            />
          </div>

          <!-- ── Email ── -->
          <div class="field-group">
            <label class="label" for="email">Email</label>
            <div class="input-field">
              <!--
                Field-Icons.svg — person/user icon
                Replace src="" with: src="./public/Field-Icons.svg"
              -->
              <img class="field-icon-user" src="/ListaHub/pics_icons/Field-Icons.svg" alt="" aria-hidden="true" />
              <input
                id="email"
                name="email"
                type="email"
                placeholder="listahub@gmail.com"
                required
              />
            </div>
          </div>

          <!-- ── Username ── -->
          <div class="field-group">
            <label class="label" for="username">Username</label>
            <div class="input-field">
              <!--
                Field-Icons.svg — person/user icon (same icon as email)
                Replace src="" with: src="./public/Field-Icons.svg"
              -->
              <img class="field-icon-user" src="/ListaHub/pics_icons/Field-Icons.svg" alt="" aria-hidden="true" />
              <input
                id="username"
                name="username"
                type="text"
                placeholder="listahub67"
                required
              />
            </div>
          </div>

          <!-- ── Password ── -->
          <div class="field-group">
            <label class="label" for="password">Password</label>
            <div class="input-field">
              <!--
                Vector.svg — lock icon
                Replace src="" with: src="./public/Vector.svg"
              -->
              <img class="field-icon-lock" src="/ListaHub/pics_icons/Vector.svg" alt="" aria-hidden="true" />
              <input
                id="password"
                name="password"
                type="password"
                placeholder="enter your password"
                required
              />
            </div>
          </div>

          <!-- ── Confirm Password ── -->
          <div class="field-group">
            <label class="label" for="confirm">Confirm Password</label>
            <div class="input-field">
              <!--
                Vector1.svg — lock icon (confirm variant)
                Replace src="" with: src="./public/Vector1.svg"
              -->
              <img class="field-icon-lock" src="/ListaHub/pics_icons/Vector1.svg" alt="" aria-hidden="true" />
              <input
                id="confirm"
                name="confirm"
                type="password"
                placeholder="confirm your password"
                required
              />
            </div>
          </div>

          <!-- ── Store Name ── -->
          <div class="field-group">
            <label class="label" for="store_name">Store Name</label>
            <div class="input-field">
              <!--
                Vector2.svg — store/shelf icon
                Replace src="" with: src="./public/Vector2.svg"
              -->
              <img class="field-icon-store" src="/ListaHub/pics_icons/Vector2.svg" alt="" aria-hidden="true" />
              <input
                id="store_name"
                name="store_name"
                type="text"
                placeholder="Enter the name of your store"
                required
              />
            </div>
          </div>

          <!-- ── Bottom: terms, submit, login link ── -->
          <div class="section">
            <p class="terms-text">
              I agree to ListaHub's <b>Terms of Service &amp; Privacy Policy.</b>
            </p>

            <!-- Submit → signup_process.php → on success → dashboard.php -->
            <button class="signup-btn" type="submit" name="terms" value="1">
              Sign up
            </button>

            <p class="login-link-text">
              Already have an account? <a href="login.php">Log in</a>
            </p>
          </div>

        </form>
      </section>

    </main>
  </div>
</div>

</body>
</html>