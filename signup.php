<?php

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
  <link rel="stylesheet" href="css/global_signup.css" />
  <link rel="stylesheet" href="css/signup.css" />
</head>
<body>

<div class="signup-page">
  <div class="body-signup">

    <header class="nav">

      <div class="logo">
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
    <main class="content-area">
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

      <section class="sign-up-card">
        <form class="input-form" method="POST" action="signup_process.php">
          <div class="card-logo-wrap">
            <img
              src="/ListaHub/pics_icons/ListaHub-Logo-1@2x.png"
              alt="ListaHub"
            />
          </div>
          <div class="field-group">
            <label class="label" for="email">Email</label>
            <div class="input-field">
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

          <div class="field-group">
            <label class="label" for="username">Username</label>
            <div class="input-field">
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
              <input type="checkbox" name="terms" required style="margin-right:5px;accent-color:#ebd665;"/>
              I agree to ListaHub's
              <button
                type="button"
                class="terms-link"
                id="terms-link"
                aria-controls="terms-privacy"
                aria-expanded="false"
              >
                <b>Terms of Service &amp; Privacy Policy.</b>
              </button>
            </p>

            <button class="signup-btn" type="submit" name="terms" value="1">
              Sign up
            </button>

            <p class="login-link-text">
              Already have an account? <a href="login.php">Log in</a>
            </p>
          </div>
        </form>
      </section>

      <section class="terms-privacy" id="terms-privacy" hidden>
        <div class="terms-container">
          <div class="terms-title">
            <h3 class="terms-of-service">Terms of Service & Privacy Policy</h3>
          </div>
          <section class="terms-content-container">
                  <div class="content-text">
                    <div class="contain-terms">
                      <div class="contain-privacy">
                        <div class="terms-privacy-text">
                          <p class="the-following-describes">
                            The following describes how we govern the use of our
                            platform and how we collect, protect, and handle your data.
                            By creating an account on ListaHub, you agree to these
                            terms.
                          </p>
                          <p class="the-following-describes">&nbsp;</p>
                          <p class="the-following-describes">
                            <b>TERMS OF SERVICE </b>
                          </p>
                          <p class="the-following-describes">
                            Account Registration and Security
                          </p>
                          <p class="the-following-describes">
                            To access ListaHub, you must register for an account by
                            providing a username, email address, password, and store
                            name.
                          </p>
                          <ul class="you-are-entirely-responsible-f">
                            <li>
                              <span
                                >You are entirely responsible for maintaining the
                                confidentiality of your login credentials.</span
                              >
                            </li>
                            <li>
                              <span
                                >You agree to accept responsibility for all activities,
                                sales entries, and inventory adjustments that occur
                                under your account.</span
                              >
                            </li>
                          </ul>
                          <p class="the-following-describes">
                            <b>Data Ownership</b>
                          </p>
                          <ul class="you-are-entirely-responsible-f">
                            <li>
                              <span
                                >Your Data: You retain 100% ownership of all product
                                listings, stock counts, and sales figures you input or
                                import into ListaHub.</span
                              >
                            </li>
                            <li>
                              <span
                                >Your Responsibility: You are responsible for ensuring
                                that your data is accurate and legally yours to
                                use.</span
                              >
                            </li>
                            <li>
                              <span
                                >Limitation of Liability: ListaHub provides the tools
                                for calculation, but we are not liable for any data
                                corruption, formatting errors, or inventory
                                discrepancies resulting from improperly formatted CSV
                                files or user mistakes. It is your responsibility to
                                cross-verify critical business figures.</span
                              >
                            </li>
                          </ul>
                          <p class="the-following-describes">&nbsp;</p>
                          <p class="the-following-describes">
                            <b>PRIVACY POLICY</b>
                          </p>
                          <p class="information-we-collect">Information We Collect</p>
                          <p class="the-following-describes">
                            We keep our data collection minimal. We only collect:
                          </p>
                          <ul class="you-are-entirely-responsible-f">
                            <li>
                              <span
                                >Account Information: Username, email address, and
                                account password (which is securely encrypted).</span
                              >
                            </li>
                            <li>
                              <span
                                >Business Information: Your store name and the
                                sales/inventory data you upload (including via CSV
                                files).</span
                              >
                            </li>
                          </ul>
                          <p class="information-we-collect">How We Use Your Data</p>
                          <ul class="you-are-entirely-responsible-f">
                            <li>
                              <span
                                >Your email is used for account verification, password
                                resets, and critical system updates.</span
                              >
                            </li>
                            <li>
                              <span
                                >Your store name and CSV data are used solely to
                                generate your inventory dashboards and sales
                                reports.</span
                              >
                            </li>
                            <li>
                              <span
                                >We do not sell, rent, or share your personal info or
                                business metrics with third-party advertisers.</span
                              >
                            </li>
                          </ul>
                          <p class="information-we-collect">Data Security</p>
                          <ul class="you-are-entirely-responsible-f">
                            <li>
                              <span
                                >We implement standard security measures to protect your
                                account details and inventory data from unauthorized
                                access or leaks.</span
                              >
                            </li>
                            <li>
                              <span
                                >For inquiries, you may contact us at
                                listahub@gmail.com.</span
                              >
                            </li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="terms-buttons">
                    <button type="button" class="terms-cancel" data-close-terms>
                      <span class="cancel-label">Cancel</span>
                    </button>
                    <button type="button" class="terms-complete" data-close-terms>
                      <span class="complete-label">Complete</span>
                    </button>
                  </div>
                </section>
              </div>
        </section>
    </main>
  </div>
</div>

<script>
  const termsLink = document.getElementById('terms-link');
  const termsPopup = document.getElementById('terms-privacy');

  const openTermsPopup = () => {
    termsPopup.hidden = false;
    termsLink.setAttribute('aria-expanded', 'true');
  };

  const closeTermsPopup = () => {
    termsPopup.hidden = true;
    termsLink.setAttribute('aria-expanded', 'false');
  };

  termsLink.addEventListener('click', openTermsPopup);

  termsPopup.querySelectorAll('[data-close-terms]').forEach((control) => {
    control.addEventListener('click', closeTermsPopup);
  });

  termsPopup.addEventListener('click', (event) => {
    if (event.target === termsPopup) {
      closeTermsPopup();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !termsPopup.hidden) {
      closeTermsPopup();
    }
  });
</script>

</body>
</html>