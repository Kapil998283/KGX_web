<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terms of Use - KGX Gaming</title>

  <!-- 
    - favicon
  -->
  <link rel="shortcut icon" href="../assets/images/logo.ico" type="image/x-icon">

  <!-- 
    - custom css link
  -->
  <link rel="stylesheet" href="../assets/css/root.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Oxanium', cursive;
      background: var(--raisin-black-1);
      color: var(--light-gray);
      line-height: 1.6;
    }

    .container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .terms-header {
      background: linear-gradient(135deg, var(--raisin-black-2), var(--eerie-black-1));
      padding: 80px 0 60px;
      text-align: center;
      border-bottom: 2px solid var(--orange-web);
    }

    .terms-header h1 {
      color: var(--orange-web);
      font-size: 3rem;
      margin-bottom: 15px;
      text-shadow: 0 0 20px rgba(255, 117, 56, 0.3);
    }

    .terms-header p {
      font-size: 1.2rem;
      color: var(--light-gray-70);
      max-width: 700px;
      margin: 0 auto 20px;
    }

    .last-updated {
      color: var(--blue-ryb);
      font-weight: 600;
      font-size: 1rem;
    }

    .terms-content {
      padding: 60px 0;
    }

    .back-btn {
      display: inline-block;
      background: linear-gradient(135deg, var(--orange-web), #ff8c42);
      color: var(--raisin-black-1);
      padding: 12px 30px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      margin-bottom: 40px;
    }

    .back-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(255, 117, 56, 0.3);
    }

    .table-of-contents {
      background: var(--raisin-black-2);
      border: 2px solid var(--eerie-black-2);
      border-radius: 12px;
      padding: 30px;
      margin-bottom: 40px;
    }

    .table-of-contents h3 {
      color: var(--orange-web);
      font-size: 1.4rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .table-of-contents h3::before {
      content: '📋';
    }

    .toc-list {
      list-style: none;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 10px;
    }

    .toc-list li {
      padding: 8px 0;
    }

    .toc-list a {
      color: var(--light-gray);
      text-decoration: none;
      transition: color 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .toc-list a:hover {
      color: var(--blue-ryb);
    }

    .toc-list a::before {
      content: '▶';
      color: var(--blue-ryb);
      font-size: 0.8rem;
    }

    .terms-section {
      background: var(--raisin-black-2);
      border: 1px solid var(--eerie-black-2);
      border-radius: 12px;
      padding: 40px;
      margin-bottom: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .terms-section h2 {
      color: var(--orange-web);
      font-size: 1.8rem;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 15px;
      scroll-margin-top: 100px;
    }

    .terms-section h2::before {
      content: '⚖️';
      font-size: 1.5rem;
    }

    .terms-section h3 {
      color: var(--blue-ryb);
      font-size: 1.3rem;
      margin: 25px 0 15px;
    }

    .terms-section p {
      color: var(--light-gray);
      margin-bottom: 15px;
      text-align: justify;
    }

    .terms-section ul, .terms-section ol {
      margin: 15px 0;
      padding-left: 25px;
    }

    .terms-section li {
      color: var(--light-gray);
      margin-bottom: 8px;
    }

    .terms-section strong {
      color: var(--orange-web);
    }

    .highlight-box {
      background: linear-gradient(135deg, var(--eerie-black-1), var(--raisin-black-1));
      border-left: 4px solid var(--blue-ryb);
      padding: 20px;
      margin: 20px 0;
      border-radius: 8px;
    }

    .highlight-box h4 {
      color: var(--blue-ryb);
      margin-bottom: 10px;
      font-size: 1.1rem;
    }

    .highlight-box p {
      color: var(--light-gray-70);
      margin-bottom: 8px;
    }

    .warning-box {
      background: linear-gradient(135deg, rgba(255, 117, 56, 0.1), rgba(255, 117, 56, 0.05));
      border: 2px solid var(--orange-web);
      border-radius: 8px;
      padding: 20px;
      margin: 20px 0;
    }

    .warning-box h4 {
      color: var(--orange-web);
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .warning-box h4::before {
      content: '⚠️';
    }

    .contact-section {
      background: linear-gradient(135deg, var(--eerie-black-1), var(--raisin-black-2));
      border: 2px solid var(--blue-ryb);
      border-radius: 12px;
      padding: 40px;
      text-align: center;
      margin-top: 50px;
    }

    .contact-section h3 {
      color: var(--blue-ryb);
      font-size: 1.6rem;
      margin-bottom: 15px;
    }

    .contact-section p {
      color: var(--light-gray);
      margin-bottom: 25px;
    }

    .contact-section .btn {
      display: inline-block;
      background: linear-gradient(135deg, var(--blue-ryb), #4a90e2);
      color: white;
      padding: 15px 30px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      margin: 0 10px;
    }

    .contact-section .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
    }

    .effective-date {
      background: var(--eerie-black-2);
      border-radius: 8px;
      padding: 15px 20px;
      margin-bottom: 30px;
      text-align: center;
      border: 1px solid var(--orange-web);
    }

    .effective-date strong {
      color: var(--orange-web);
    }

    @media (max-width: 768px) {
      .terms-header h1 {
        font-size: 2.2rem;
      }

      .terms-section {
        padding: 25px;
      }

      .terms-section h2 {
        font-size: 1.5rem;
      }

      .container {
        padding: 0 15px;
      }

      .toc-list {
        grid-template-columns: 1fr;
      }

      .contact-section .btn {
        display: block;
        margin: 10px 0;
      }
    }
  </style>

  <!-- 
    - google font link
  -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@600;700;800&display=swap" rel="stylesheet">

</head>

<body>

  <!-- Terms Header -->
  <section class="terms-header">
    <div class="container">
      <h1>Terms of Use</h1>
      <p>Please read these terms carefully before using KGX Gaming platform. By accessing our services, you agree to be bound by these terms.</p>
      <div class="last-updated">Last Updated: December 2024</div>
    </div>
  </section>

  <!-- Terms Content -->
  <section class="terms-content">
    <div class="container">
      
      <a href="../index.php" class="back-btn">← Back to Home</a>

      <!-- Effective Date -->
      <div class="effective-date">
        <strong>Effective Date:</strong> These Terms of Use are effective as of December 1, 2024
      </div>

      <!-- Table of Contents -->
      <div class="table-of-contents">
        <h3>Table of Contents</h3>
        <ul class="toc-list">
          <li><a href="#acceptance">1. Acceptance of Terms</a></li>
          <li><a href="#description">2. Service Description</a></li>
          <li><a href="#eligibility">3. Eligibility</a></li>
          <li><a href="#accounts">4. User Accounts</a></li>
          <li><a href="#conduct">5. User Conduct</a></li>
          <li><a href="#content">6. User Content</a></li>
          <li><a href="#tournaments">7. Tournaments & Competitions</a></li>
          <li><a href="#payments">8. Payments & Prizes</a></li>
          <li><a href="#intellectual">9. Intellectual Property</a></li>
          <li><a href="#privacy">10. Privacy</a></li>
          <li><a href="#termination">11. Termination</a></li>
          <li><a href="#disclaimers">12. Disclaimers</a></li>
          <li><a href="#limitation">13. Limitation of Liability</a></li>
          <li><a href="#governing">14. Governing Law</a></li>
          <li><a href="#changes">15. Changes to Terms</a></li>
          <li><a href="#contact">16. Contact Information</a></li>
        </ul>
      </div>

      <!-- 1. Acceptance of Terms -->
      <div class="terms-section" id="acceptance">
        <h2>1. Acceptance of Terms</h2>
        <p>By accessing, browsing, or using the KGX Gaming platform (the "Service"), you acknowledge that you have read, understood, and agree to be bound by these Terms of Use ("Terms") and our Privacy Policy.</p>
        
        <div class="highlight-box">
          <h4>Important Notice</h4>
          <p>If you do not agree to these Terms, you must not access or use our Service. Your continued use of the Service constitutes acceptance of any modifications to these Terms.</p>
        </div>

        <p>These Terms constitute a legally binding agreement between you and KGX Gaming ("we," "us," or "our"). We reserve the right to modify these Terms at any time, and such modifications will be effective immediately upon posting.</p>
      </div>

      <!-- 2. Service Description -->
      <div class="terms-section" id="description">
        <h2>2. Service Description</h2>
        <p>KGX Gaming is an online competitive gaming platform that provides:</p>
        <ul>
          <li>Access to various online games and competitions</li>
          <li>Tournament hosting and participation opportunities</li>
          <li>Community features and social interaction tools</li>
          <li>Leaderboards and ranking systems</li>
          <li>Prize distribution and reward systems</li>
          <li>Gaming-related content and resources</li>
        </ul>

        <p>We reserve the right to modify, suspend, or discontinue any part of our Service at any time without prior notice. We are not liable for any modification, suspension, or discontinuation of the Service.</p>
      </div>

      <!-- 3. Eligibility -->
      <div class="terms-section" id="eligibility">
        <h2>3. Eligibility</h2>
        <p>To use our Service, you must:</p>
        <ul>
          <li>Be at least 13 years of age (or the minimum age required in your jurisdiction)</li>
          <li>Have the legal capacity to enter into binding agreements</li>
          <li>Not be prohibited from using the Service under applicable laws</li>
          <li>Comply with all local, state, national, and international laws</li>
        </ul>

        <div class="warning-box">
          <h4>Age Restrictions</h4>
          <p>Users under 18 years of age must have parental consent to use our Service. Parents or guardians are responsible for monitoring their minor's use of the platform.</p>
        </div>

        <p>By using our Service, you represent and warrant that you meet these eligibility requirements.</p>
      </div>

      <!-- 4. User Accounts -->
      <div class="terms-section" id="accounts">
        <h2>4. User Accounts</h2>
        
        <h3>Account Creation</h3>
        <p>To access certain features, you must create an account. You agree to:</p>
        <ul>
          <li>Provide accurate, current, and complete information</li>
          <li>Maintain and update your account information</li>
          <li>Keep your login credentials secure and confidential</li>
          <li>Accept responsibility for all activities under your account</li>
          <li>Notify us immediately of any unauthorized use</li>
        </ul>

        <h3>Account Security</h3>
        <div class="highlight-box">
          <h4>Your Responsibility</h4>
          <p>You are solely responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>
        </div>

        <h3>Account Termination</h3>
        <p>We reserve the right to suspend or terminate accounts that violate these Terms or engage in fraudulent, abusive, or illegal activities.</p>
      </div>

      <!-- 5. User Conduct -->
      <div class="terms-section" id="conduct">
        <h2>5. User Conduct</h2>
        <p>You agree not to use our Service to:</p>
        
        <h3>Prohibited Activities</h3>
        <ul>
          <li>Violate any applicable laws, regulations, or third-party rights</li>
          <li>Engage in cheating, hacking, or use of unauthorized software</li>
          <li>Harass, abuse, or harm other users</li>
          <li>Share inappropriate, offensive, or illegal content</li>
          <li>Attempt to gain unauthorized access to our systems</li>
          <li>Interfere with the normal operation of the Service</li>
          <li>Create multiple accounts to circumvent restrictions</li>
          <li>Engage in any form of commercial activity without permission</li>
        </ul>

        <div class="warning-box">
          <h4>Zero Tolerance Policy</h4>
          <p>We have a zero tolerance policy for cheating, harassment, or any form of abuse. Violations may result in immediate account termination and forfeiture of prizes.</p>
        </div>

        <h3>Fair Play</h3>
        <p>All users must compete fairly and honestly. The use of cheats, exploits, bots, or any unauthorized assistance is strictly prohibited and will result in penalties including account suspension or termination.</p>
      </div>

      <!-- 6. User Content -->
      <div class="terms-section" id="content">
        <h2>6. User Content</h2>
        
        <h3>Content Ownership</h3>
        <p>You retain ownership of content you create and share on our platform. However, by posting content, you grant us a non-exclusive, worldwide, royalty-free license to use, modify, and display your content in connection with our Service.</p>

        <h3>Content Standards</h3>
        <p>All user content must:</p>
        <ul>
          <li>Comply with applicable laws and regulations</li>
          <li>Not infringe on third-party rights</li>
          <li>Not contain harmful, offensive, or inappropriate material</li>
          <li>Not promote illegal activities or violence</li>
          <li>Respect the privacy and dignity of others</li>
        </ul>

        <h3>Content Moderation</h3>
        <p>We reserve the right to review, modify, or remove any user content that violates these Terms or our community guidelines. We are not obligated to monitor all content but may do so at our discretion.</p>
      </div>

      <!-- 7. Tournaments & Competitions -->
      <div class="terms-section" id="tournaments">
        <h2>7. Tournaments & Competitions</h2>
        
        <h3>Participation</h3>
        <p>Tournament participation is subject to:</p>
        <ul>
          <li>Eligibility requirements specific to each tournament</li>
          <li>Payment of entry fees (where applicable)</li>
          <li>Compliance with tournament rules and regulations</li>
          <li>Fair play and sportsmanship standards</li>
        </ul>

        <h3>Tournament Rules</h3>
        <div class="highlight-box">
          <h4>Binding Agreement</h4>
          <p>By entering a tournament, you agree to abide by the specific rules and regulations for that competition. Tournament rules may vary and will be clearly posted.</p>
        </div>

        <h3>Disputes and Decisions</h3>
        <p>All tournament disputes will be handled by our moderation team. Our decisions regarding tournament matters are final and binding. We reserve the right to disqualify participants who violate rules or engage in unsportsmanlike conduct.</p>
      </div>

      <!-- 8. Payments & Prizes -->
      <div class="terms-section" id="payments">
        <h2>8. Payments & Prizes</h2>
        
        <h3>Entry Fees</h3>
        <p>Some tournaments may require entry fees. All fees are clearly displayed before registration. Entry fees are generally non-refundable except in cases of tournament cancellation by us.</p>

        <h3>Prize Distribution</h3>
        <ul>
          <li>Prizes are awarded according to tournament rules and rankings</li>
          <li>Winners must verify their identity before receiving prizes</li>
          <li>Tax obligations for prizes are the responsibility of winners</li>
          <li>We reserve the right to withhold prizes in cases of rule violations</li>
        </ul>

        <div class="warning-box">
          <h4>Prize Forfeiture</h4>
          <p>Prizes may be forfeited if winners are found to have violated tournament rules, engaged in cheating, or failed to comply with verification requirements.</p>
        </div>

        <h3>Payment Processing</h3>
        <p>All payments are processed through secure third-party providers. We do not store payment information on our servers. Refunds, when applicable, will be processed according to our refund policy.</p>
      </div>

      <!-- 9. Intellectual Property -->
      <div class="terms-section" id="intellectual">
        <h2>9. Intellectual Property</h2>
        
        <h3>Our Rights</h3>
        <p>The KGX Gaming platform, including its design, functionality, code, and content, is protected by intellectual property laws. We own or have licensed all rights to:</p>
        <ul>
          <li>The KGX Gaming name, logo, and branding</li>
          <li>Platform software and technology</li>
          <li>Game content and assets</li>
          <li>Documentation and promotional materials</li>
        </ul>

        <h3>Limited License</h3>
        <p>We grant you a limited, non-exclusive, non-transferable license to use our Service for personal, non-commercial purposes in accordance with these Terms.</p>

        <h3>Third-Party Content</h3>
        <p>Some content on our platform may be owned by third parties. Such content is used under appropriate licenses and remains the property of its respective owners.</p>
      </div>

      <!-- 10. Privacy -->
      <div class="terms-section" id="privacy">
        <h2>10. Privacy</h2>
        <p>Your privacy is important to us. Our collection, use, and protection of your personal information is governed by our Privacy Policy, which is incorporated into these Terms by reference.</p>
        
        <div class="highlight-box">
          <h4>Data Collection</h4>
          <p>We collect and process personal information as described in our Privacy Policy. By using our Service, you consent to such collection and processing.</p>
        </div>

        <p>Please review our Privacy Policy to understand how we handle your information. If you do not agree with our privacy practices, you should not use our Service.</p>
      </div>

      <!-- 11. Termination -->
      <div class="terms-section" id="termination">
        <h2>11. Termination</h2>
        
        <h3>Termination by You</h3>
        <p>You may terminate your account at any time by contacting our support team. Upon termination, you will lose access to your account and any associated benefits.</p>

        <h3>Termination by Us</h3>
        <p>We may suspend or terminate your account immediately, without prior notice, if:</p>
        <ul>
          <li>You violate these Terms or our policies</li>
          <li>You engage in fraudulent or illegal activities</li>
          <li>Your account remains inactive for an extended period</li>
          <li>We discontinue the Service</li>
        </ul>

        <h3>Effect of Termination</h3>
        <p>Upon termination, your right to use the Service ceases immediately. Provisions that should survive termination (such as intellectual property rights and limitation of liability) will remain in effect.</p>
      </div>

      <!-- 12. Disclaimers -->
      <div class="terms-section" id="disclaimers">
        <h2>12. Disclaimers</h2>
        
        <div class="warning-box">
          <h4>Service Provided "As Is"</h4>
          <p>Our Service is provided on an "as is" and "as available" basis without warranties of any kind, either express or implied.</p>
        </div>

        <p>We disclaim all warranties, including but not limited to:</p>
        <ul>
          <li>Merchantability and fitness for a particular purpose</li>
          <li>Non-infringement of third-party rights</li>
          <li>Uninterrupted or error-free operation</li>
          <li>Security or virus-free operation</li>
          <li>Accuracy or completeness of content</li>
        </ul>

        <p>We do not guarantee that the Service will meet your requirements or that any defects will be corrected. Your use of the Service is at your own risk.</p>
      </div>

      <!-- 13. Limitation of Liability -->
      <div class="terms-section" id="limitation">
        <h2>13. Limitation of Liability</h2>
        <p>To the maximum extent permitted by law, KGX Gaming and its affiliates shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to:</p>
        
        <ul>
          <li>Loss of profits or revenue</li>
          <li>Loss of data or information</li>
          <li>Business interruption</li>
          <li>Personal injury or property damage</li>
          <li>Loss of privacy or security breaches</li>
        </ul>

        <div class="highlight-box">
          <h4>Maximum Liability</h4>
          <p>Our total liability to you for all claims arising from or related to the Service shall not exceed the amount you paid to us in the twelve months preceding the claim.</p>
        </div>
      </div>

      <!-- 14. Governing Law -->
      <div class="terms-section" id="governing">
        <h2>14. Governing Law</h2>
        <p>These Terms are governed by and construed in accordance with the laws of [Your Jurisdiction], without regard to conflict of law principles.</p>
        
        <h3>Dispute Resolution</h3>
        <p>Any disputes arising from these Terms or your use of the Service will be resolved through:</p>
        <ol>
          <li>Good faith negotiation between the parties</li>
          <li>Mediation, if negotiation fails</li>
          <li>Binding arbitration or court proceedings in [Your Jurisdiction]</li>
        </ol>
      </div>

      <!-- 15. Changes to Terms -->
      <div class="terms-section" id="changes">
        <h2>15. Changes to Terms</h2>
        <p>We reserve the right to modify these Terms at any time. When we make changes, we will:</p>
        <ul>
          <li>Update the "Last Updated" date at the top of this page</li>
          <li>Notify users of significant changes via email or platform notifications</li>
          <li>Provide reasonable notice before changes take effect</li>
        </ul>

        <div class="highlight-box">
          <h4>Continued Use Constitutes Acceptance</h4>
          <p>Your continued use of the Service after any modifications to these Terms constitutes your acceptance of the updated Terms.</p>
        </div>
      </div>

      <!-- 16. Contact Information -->
      <div class="terms-section" id="contact">
        <h2>16. Contact Information</h2>
        <p>If you have any questions about these Terms or our Service, please contact us:</p>
        
        <div class="highlight-box">
          <h4>KGX Gaming Support</h4>
          <p><strong>Email:</strong> legal@kgxgaming.com</p>
          <p><strong>Address:</strong> [Your Business Address]</p>
          <p><strong>Phone:</strong> [Your Contact Number]</p>
        </div>

        <p>We will respond to legal inquiries within 5 business days. For general support questions, please use our standard support channels.</p>
      </div>

      <!-- Contact Section -->
      <div class="contact-section">
        <h3>Questions About These Terms?</h3>
        <p>Need clarification on any part of our Terms of Use? Our legal and support teams are here to help.</p>
        <a href="dashboard/help-contact.php" class="btn">Contact Support</a>
        <a href="privacy-policy.php" class="btn">View Privacy Policy</a>
      </div>

    </div>
  </section>

  <!-- Smooth Scrolling Script -->
  <script>
    // Smooth scrolling for table of contents links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Highlight current section in table of contents
    const sections = document.querySelectorAll('.terms-section[id]');
    const tocLinks = document.querySelectorAll('.toc-list a');

    window.addEventListener('scroll', () => {
      let current = '';
      sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (scrollY >= sectionTop - 200) {
          current = section.getAttribute('id');
        }
      });

      tocLinks.forEach(link => {
        link.style.color = '';
        if (link.getAttribute('href') === `#${current}`) {
          link.style.color = 'var(--orange-web)';
        }
      });
    });
  </script>

  <!-- 
    - ionicon link
  -->
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

</body>

</html>
