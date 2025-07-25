<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Frequently Asked Questions - KGX Gaming</title>

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
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .faq-header {
      background: linear-gradient(135deg, var(--raisin-black-2), var(--eerie-black-1));
      padding: 80px 0 60px;
      text-align: center;
      border-bottom: 2px solid var(--orange-web);
    }

    .faq-header h1 {
      color: var(--orange-web);
      font-size: 3rem;
      margin-bottom: 15px;
      text-shadow: 0 0 20px rgba(255, 117, 56, 0.3);
    }

    .faq-header p {
      font-size: 1.2rem;
      color: var(--light-gray-70);
      max-width: 600px;
      margin: 0 auto;
    }

    .faq-content {
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

    .faq-categories {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 30px;
      margin-bottom: 50px;
    }

    .category-card {
      background: var(--raisin-black-2);
      border: 1px solid var(--eerie-black-2);
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .category-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(255, 117, 56, 0.1);
      border-color: var(--orange-web);
    }

    .category-card .icon {
      font-size: 3rem;
      color: var(--blue-ryb);
      margin-bottom: 20px;
    }

    .category-card h3 {
      color: var(--orange-web);
      font-size: 1.4rem;
      margin-bottom: 15px;
    }

    .category-card p {
      color: var(--light-gray-70);
      font-size: 0.95rem;
    }

    .faq-section {
      background: var(--raisin-black-2);
      border: 1px solid var(--eerie-black-2);
      border-radius: 12px;
      padding: 40px;
      margin-bottom: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .faq-section h2 {
      color: var(--orange-web);
      font-size: 1.8rem;
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .faq-section h2::before {
      content: '🎮';
      font-size: 1.5rem;
    }

    .faq-item {
      border-bottom: 1px solid var(--eerie-black-1);
      padding: 20px 0;
    }

    .faq-item:last-child {
      border-bottom: none;
    }

    .faq-question {
      background: none;
      border: none;
      color: var(--light-gray);
      font-family: 'Oxanium', cursive;
      font-size: 1.1rem;
      font-weight: 600;
      text-align: left;
      width: 100%;
      padding: 0;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: color 0.3s ease;
    }

    .faq-question:hover {
      color: var(--orange-web);
    }

    .faq-question::after {
      content: '+';
      font-size: 1.5rem;
      color: var(--blue-ryb);
      transition: transform 0.3s ease;
    }

    .faq-question.active::after {
      transform: rotate(45deg);
      color: var(--orange-web);
    }

    .faq-answer {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
      color: var(--light-gray-70);
      margin-top: 0;
    }

    .faq-answer.active {
      max-height: 500px;
      margin-top: 15px;
    }

    .faq-answer p {
      margin-bottom: 10px;
    }

    .faq-answer ul {
      margin: 10px 0;
      padding-left: 20px;
    }

    .faq-answer li {
      margin-bottom: 5px;
    }

    .search-box {
      background: var(--raisin-black-2);
      border: 2px solid var(--eerie-black-2);
      border-radius: 12px;
      padding: 30px;
      margin-bottom: 40px;
      text-align: center;
    }

    .search-box h3 {
      color: var(--orange-web);
      margin-bottom: 20px;
      font-size: 1.4rem;
    }

    .search-input {
      width: 100%;
      max-width: 500px;
      padding: 15px 20px;
      border: 2px solid var(--eerie-black-1);
      border-radius: 8px;
      background: var(--raisin-black-1);
      color: var(--light-gray);
      font-family: 'Oxanium', cursive;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    .search-input:focus {
      outline: none;
      border-color: var(--orange-web);
    }

    .contact-cta {
      background: linear-gradient(135deg, var(--eerie-black-1), var(--raisin-black-2));
      border: 2px solid var(--blue-ryb);
      border-radius: 12px;
      padding: 40px;
      text-align: center;
      margin-top: 50px;
    }

    .contact-cta h3 {
      color: var(--blue-ryb);
      font-size: 1.6rem;
      margin-bottom: 15px;
    }

    .contact-cta p {
      color: var(--light-gray);
      margin-bottom: 25px;
    }

    .contact-cta .btn {
      display: inline-block;
      background: linear-gradient(135deg, var(--blue-ryb), #4a90e2);
      color: white;
      padding: 15px 30px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .contact-cta .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
    }

    @media (max-width: 768px) {
      .faq-header h1 {
        font-size: 2.2rem;
      }

      .faq-section {
        padding: 25px;
      }

      .faq-section h2 {
        font-size: 1.5rem;
      }

      .container {
        padding: 0 15px;
      }

      .faq-categories {
        grid-template-columns: 1fr;
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

  <!-- FAQ Header -->
  <section class="faq-header">
    <div class="container">
      <h1>Frequently Asked Questions</h1>
      <p>Find answers to common questions about KGX Gaming platform, tournaments, and gaming services.</p>
    </div>
  </section>

  <!-- FAQ Content -->
  <section class="faq-content">
    <div class="container">
      
      <a href="../index.php" class="back-btn">← Back to Home</a>

      <!-- Search Box -->
      <div class="search-box">
        <h3>Search FAQ</h3>
        <input type="text" class="search-input" placeholder="Type your question here..." id="faqSearch">
      </div>

      <!-- FAQ Categories -->
      <div class="faq-categories">
        <div class="category-card">
          <div class="icon">🎮</div>
          <h3>Gaming & Platform</h3>
          <p>Questions about gameplay, features, and platform usage</p>
        </div>
        <div class="category-card">
          <div class="icon">🏆</div>
          <h3>Tournaments</h3>
          <p>Everything about competitions, prizes, and participation</p>
        </div>
        <div class="category-card">
          <div class="icon">👤</div>
          <h3>Account & Profile</h3>
          <p>Account management, profiles, and security settings</p>
        </div>
        <div class="category-card">
          <div class="icon">💳</div>
          <h3>Payments & Prizes</h3>
          <p>Payment methods, withdrawals, and prize distribution</p>
        </div>
      </div>

      <!-- Gaming & Platform FAQ -->
      <div class="faq-section">
        <h2>Gaming & Platform</h2>
        
        <div class="faq-item">
          <button class="faq-question">
            What is KGX Gaming and what games do you offer?
          </button>
          <div class="faq-answer">
            <p>KGX Gaming is a competitive gaming platform that offers various popular games including battle royales, MOBAs, FPS games, and strategy games. We host tournaments, leaderboards, and provide a community for gamers to compete and connect.</p>
            <p>Our current game catalog includes popular titles with regular updates and new additions based on community demand.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            How do I get started on the platform?
          </button>
          <div class="faq-answer">
            <p>Getting started is easy:</p>
            <ul>
              <li>Create a free account by registering on our platform</li>
              <li>Complete your gaming profile with your preferences</li>
              <li>Browse available games and tournaments</li>
              <li>Join your first competition or practice match</li>
              <li>Connect with other gamers in our community</li>
            </ul>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            What are the system requirements for playing?
          </button>
          <div class="faq-answer">
            <p>System requirements vary by game, but generally you'll need:</p>
            <ul>
              <li>Stable internet connection (minimum 10 Mbps recommended)</li>
              <li>Modern web browser or our desktop application</li>
              <li>Compatible gaming device (PC, console, or mobile)</li>
              <li>Updated graphics drivers for optimal performance</li>
            </ul>
            <p>Specific requirements for each game are listed on their respective pages.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            Can I play games offline or do I need internet connection?
          </button>
          <div class="faq-answer">
            <p>Most games on our platform require an internet connection for:</p>
            <ul>
              <li>Real-time multiplayer gameplay</li>
              <li>Tournament participation</li>
              <li>Leaderboard updates</li>
              <li>Progress synchronization</li>
            </ul>
            <p>Some single-player modes may be available offline, but full platform features require internet connectivity.</p>
          </div>
        </div>
      </div>

      <!-- Tournaments FAQ -->
      <div class="faq-section">
        <h2>Tournaments & Competitions</h2>
        
        <div class="faq-item">
          <button class="faq-question">
            How do I join tournaments?
          </button>
          <div class="faq-answer">
            <p>Joining tournaments is straightforward:</p>
            <ul>
              <li>Browse available tournaments in the tournaments section</li>
              <li>Check entry requirements and fees (if applicable)</li>
              <li>Click "Register" and confirm your participation</li>
              <li>Prepare for the tournament schedule</li>
              <li>Show up on time for your matches</li>
            </ul>
            <p>Some tournaments may have skill level requirements or entry fees.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            Are there entry fees for tournaments?
          </button>
          <div class="faq-answer">
            <p>Tournament fees vary:</p>
            <ul>
              <li><strong>Free Tournaments:</strong> Many tournaments are completely free to enter</li>
              <li><strong>Premium Tournaments:</strong> Some high-stakes tournaments may have entry fees</li>
              <li><strong>Sponsored Events:</strong> Special events are usually free with prizes provided by sponsors</li>
              <li><strong>Season Passes:</strong> Some tournaments require active subscription or season pass</li>
            </ul>
            <p>All fees are clearly displayed before registration, and we accept various payment methods.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            What prizes can I win?
          </button>
          <div class="faq-answer">
            <p>Prize pools vary by tournament type:</p>
            <ul>
              <li>Cash prizes for major tournaments</li>
              <li>Gaming hardware and peripherals</li>
              <li>Platform credits and premium features</li>
              <li>Exclusive badges and titles</li>
              <li>Gaming merchandise and collectibles</li>
            </ul>
            <p>Prize details are announced with each tournament and distributed according to our prize policy.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            What happens if I miss a tournament match?
          </button>
          <div class="faq-answer">
            <p>Missing tournament matches has consequences:</p>
            <ul>
              <li>Automatic forfeit of the missed match</li>
              <li>Possible elimination from the tournament</li>
              <li>Entry fee may not be refunded</li>
              <li>Impact on your tournament rating</li>
            </ul>
            <p>Contact support immediately if you have technical issues or emergencies that prevent participation.</p>
          </div>
        </div>
      </div>

      <!-- Account & Profile FAQ -->
      <div class="faq-section">
        <h2>Account & Profile Management</h2>
        
        <div class="faq-item">
          <button class="faq-question">
            How do I create an account?
          </button>
          <div class="faq-answer">
            <p>Creating an account is simple and free:</p>
            <ul>
              <li>Click "Register" on our homepage</li>
              <li>Fill in your basic information (username, email, password)</li>
              <li>Verify your email address</li>
              <li>Complete your gaming profile</li>
              <li>Start gaming immediately</li>
            </ul>
            <p>You can also sign up using social media accounts for faster registration.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            How do I reset my password?
          </button>
          <div class="faq-answer">
            <p>To reset your password:</p>
            <ul>
              <li>Go to the login page and click "Forgot Password"</li>
              <li>Enter your registered email address</li>
              <li>Check your email for reset instructions</li>
              <li>Follow the link and create a new password</li>
              <li>Log in with your new credentials</li>
            </ul>
            <p>If you don't receive the email, check your spam folder or contact support.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            Can I change my username?
          </button>
          <div class="faq-answer">
            <p>Username changes are possible with some limitations:</p>
            <ul>
              <li>Free username change is available once every 30 days</li>
              <li>Additional changes may require premium credits</li>
              <li>Username must be unique and follow our community guidelines</li>
              <li>Some tournament history may remain linked to previous usernames</li>
            </ul>
            <p>Go to your profile settings to request a username change.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            How do I delete my account?
          </button>
          <div class="faq-answer">
            <p>Account deletion is permanent and includes:</p>
            <ul>
              <li>Removal of all personal data and game statistics</li>
              <li>Loss of purchased items and credits</li>
              <li>Cancellation of active tournaments</li>
              <li>Deletion of friend connections and messages</li>
            </ul>
            <p>To delete your account, contact our support team with your request. We'll verify your identity and process the deletion within 48 hours.</p>
          </div>
        </div>
      </div>

      <!-- Payments & Prizes FAQ -->
      <div class="faq-section">
        <h2>Payments & Prizes</h2>
        
        <div class="faq-item">
          <button class="faq-question">
            What payment methods do you accept?
          </button>
          <div class="faq-answer">
            <p>We accept various secure payment methods:</p>
            <ul>
              <li>Credit and debit cards (Visa, MasterCard, American Express)</li>
              <li>PayPal and other digital wallets</li>
              <li>Bank transfers and wire transfers</li>
              <li>Cryptocurrency payments (Bitcoin, Ethereum)</li>
              <li>Gaming gift cards and platform credits</li>
            </ul>
            <p>All transactions are secured with industry-standard encryption.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            How do I withdraw prize winnings?
          </button>
          <div class="faq-answer">
            <p>Prize withdrawal process:</p>
            <ul>
              <li>Verify your identity and payment information</li>
              <li>Meet minimum withdrawal requirements</li>
              <li>Request withdrawal through your account dashboard</li>
              <li>Wait for processing (typically 3-7 business days)</li>
              <li>Receive funds in your chosen payment method</li>
            </ul>
            <p>Withdrawal fees may apply depending on the method and amount.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            Are there any fees for transactions?
          </button>
          <div class="faq-answer">
            <p>Our fee structure is transparent:</p>
            <ul>
              <li>Account registration and basic features are free</li>
              <li>Tournament entry fees are clearly displayed</li>
              <li>Small processing fees may apply to withdrawals</li>
              <li>Premium features have upfront pricing</li>
              <li>No hidden fees or surprise charges</li>
            </ul>
            <p>All applicable fees are shown before you complete any transaction.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            How long does it take to receive prizes?
          </button>
          <div class="faq-answer">
            <p>Prize distribution timeline:</p>
            <ul>
              <li><strong>Digital Prizes:</strong> Instant to 24 hours</li>
              <li><strong>Cash Prizes:</strong> 3-7 business days after verification</li>
              <li><strong>Physical Prizes:</strong> 1-3 weeks shipping time</li>
              <li><strong>Large Prizes:</strong> May require additional verification</li>
            </ul>
            <p>You'll receive tracking information and updates throughout the process.</p>
          </div>
        </div>
      </div>

      <!-- Technical Support FAQ -->
      <div class="faq-section">
        <h2>Technical Support</h2>
        
        <div class="faq-item">
          <button class="faq-question">
            I'm experiencing lag or connection issues. What should I do?
          </button>
          <div class="faq-answer">
            <p>For connection problems, try these steps:</p>
            <ul>
              <li>Check your internet connection speed and stability</li>
              <li>Close other applications using bandwidth</li>
              <li>Try connecting to a different server region</li>
              <li>Restart your router and gaming device</li>
              <li>Contact your ISP if problems persist</li>
            </ul>
            <p>Our support team can help diagnose server-side issues if needed.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            The game won't load or crashes frequently. How can I fix this?
          </button>
          <div class="faq-answer">
            <p>For game loading and stability issues:</p>
            <ul>
              <li>Clear your browser cache and cookies</li>
              <li>Update your browser to the latest version</li>
              <li>Disable browser extensions temporarily</li>
              <li>Check if your system meets minimum requirements</li>
              <li>Try using our desktop application instead</li>
            </ul>
            <p>Report persistent issues to our technical support team with details about your system.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question">
            How do I report bugs or technical issues?
          </button>
          <div class="faq-answer">
            <p>To report technical problems:</p>
            <ul>
              <li>Use the "Report Bug" feature in your account dashboard</li>
              <li>Include detailed steps to reproduce the issue</li>
              <li>Attach screenshots or error messages if possible</li>
              <li>Specify your device, browser, and operating system</li>
              <li>Contact support directly for urgent issues</li>
            </ul>
            <p>We investigate all reports and provide updates on fixes.</p>
          </div>
        </div>
      </div>

      <!-- Contact CTA -->
      <div class="contact-cta">
        <h3>Still Have Questions?</h3>
        <p>Can't find the answer you're looking for? Our support team is here to help you with any questions or issues.</p>
        <a href="dashboard/help-contact.php" class="btn">Contact Support</a>
      </div>

    </div>
  </section>

  <!-- FAQ JavaScript -->
  <script>
    // FAQ Accordion functionality
    document.querySelectorAll('.faq-question').forEach(question => {
      question.addEventListener('click', () => {
        const answer = question.nextElementSibling;
        const isActive = question.classList.contains('active');
        
        // Close all other FAQ items
        document.querySelectorAll('.faq-question').forEach(q => {
          q.classList.remove('active');
          q.nextElementSibling.classList.remove('active');
        });
        
        // Toggle current item
        if (!isActive) {
          question.classList.add('active');
          answer.classList.add('active');
        }
      });
    });

    // Search functionality
    document.getElementById('faqSearch').addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const faqItems = document.querySelectorAll('.faq-item');
      
      faqItems.forEach(item => {
        const question = item.querySelector('.faq-question').textContent.toLowerCase();
        const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
        
        if (question.includes(searchTerm) || answer.includes(searchTerm)) {
          item.style.display = 'block';
        } else {
          item.style.display = searchTerm ? 'none' : 'block';
        }
      });
    });

    // Category card click functionality
    document.querySelectorAll('.category-card').forEach(card => {
      card.addEventListener('click', () => {
        const categoryTitle = card.querySelector('h3').textContent;
        const searchInput = document.getElementById('faqSearch');
        
        // Scroll to relevant section
        if (categoryTitle.includes('Gaming')) {
          document.querySelector('[data-category="gaming"]')?.scrollIntoView({ behavior: 'smooth' });
        } else if (categoryTitle.includes('Tournament')) {
          document.querySelector('[data-category="tournaments"]')?.scrollIntoView({ behavior: 'smooth' });
        } else if (categoryTitle.includes('Account')) {
          document.querySelector('[data-category="account"]')?.scrollIntoView({ behavior: 'smooth' });
        } else if (categoryTitle.includes('Payment')) {
          document.querySelector('[data-category="payments"]')?.scrollIntoView({ behavior: 'smooth' });
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
