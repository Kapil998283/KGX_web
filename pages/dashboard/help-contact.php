<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Help & Support | KGX Gaming</title>
  
  <!-- Favicon -->
  <link rel="shortcut icon" href="../../favicon.svg" type="image/svg+xml">
  
  <!-- CSS -->
  <link rel="stylesheet" href="../../assets/css/root.css">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Ion Icons -->
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  
  <style>
    /* Enhanced Help & Contact Page Styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: var(--ff-rajdhani, 'Rajdhani', sans-serif);
      background: linear-gradient(135deg, var(--eerie-black) 0%, var(--raisin-black-2) 50%, var(--eerie-black) 100%);
      min-height: 100vh;
      color: var(--white);
      position: relative;
      overflow-x: hidden;
      padding: 20px;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: 
        radial-gradient(circle at 20% 80%, hsla(140, 100%, 50%, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, hsla(140, 100%, 20%, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, hsla(140, 100%, 50%, 0.05) 0%, transparent 50%);
      pointer-events: none;
      z-index: -1;
    }

    .contact-container {
      max-width: 700px;
      margin: 40px auto;
      background: rgba(26, 26, 26, 0.95);
      backdrop-filter: blur(20px);
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.1);
      position: relative;
      overflow: hidden;
    }

    .contact-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(135deg, var(--orange) 0%, hsl(140, 100%, 45%) 100%);
      z-index: 1;
    }

    .back-arrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 30px;
      color: var(--light-gray);
      font-weight: 600;
      text-decoration: none;
      font-size: 16px;
      transition: all 0.3s ease;
      padding: 8px 16px;
      border-radius: 8px;
    }

    .back-arrow:hover {
      color: var(--orange);
      background: rgba(255, 255, 255, 0.05);
      transform: translateX(-5px);
    }

    .header-section {
      text-align: center;
      margin-bottom: 40px;
    }

    .logo {
      margin-bottom: 20px;
    }

    .brand-text {
      font-family: 'Orbitron', monospace;
      font-size: 36px;
      font-weight: 900;
      background: linear-gradient(135deg, var(--orange) 0%, hsl(140, 100%, 45%) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin: 0;
    }

    .brand-tagline {
      font-family: 'Rajdhani', sans-serif;
      font-size: 10px;
      font-weight: 600;
      color: var(--xiketic);
      letter-spacing: 2px;
      margin-top: 5px;
      display: block;
    }

    h2 {
      font-family: var(--ff-oswald, 'Oswald', sans-serif);
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--white);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .subtitle {
      color: var(--light-gray);
      font-size: 16px;
      margin-bottom: 30px;
    }

    .support-options {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }

    .support-card {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .support-card:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: var(--orange);
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .support-icon {
      width: 50px;
      height: 50px;
      margin: 0 auto 16px;
      background: linear-gradient(135deg, var(--orange) 0%, hsl(140, 100%, 45%) 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
    }

    .support-card h3 {
      font-size: 18px;
      font-weight: 600;
      color: var(--white);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .support-card p {
      color: var(--light-gray);
      font-size: 14px;
      line-height: 1.4;
    }

    .contact-form {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 30px;
      margin-bottom: 30px;
    }

    .form-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .form-header h3 {
      font-size: 24px;
      font-weight: 600;
      color: var(--white);
      margin-bottom: 8px;
      text-transform: uppercase;
    }

    .form-header p {
      color: var(--light-gray);
      font-size: 14px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--white);
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-group label ion-icon {
      font-size: 18px;
      color: var(--orange);
    }

    input,
    textarea {
      width: 100%;
      padding: 14px 16px;
      background: rgba(255, 255, 255, 0.05);
      border: 2px solid rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      color: var(--white);
      font-size: 16px;
      font-family: var(--ff-rajdhani, 'Rajdhani', sans-serif);
      transition: all 0.3s ease;
      box-sizing: border-box;
    }

    input:focus,
    textarea:focus {
      outline: none;
      border-color: var(--orange);
      background: rgba(255, 255, 255, 0.08);
      box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
    }

    input::placeholder,
    textarea::placeholder {
      color: var(--light-gray);
    }

    textarea {
      resize: vertical;
      min-height: 120px;
    }

    .submit-btn {
      background: linear-gradient(135deg, var(--orange) 0%, hsl(140, 100%, 45%) 100%);
      color: white;
      border: none;
      padding: 16px 32px;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      font-family: var(--ff-rajdhani, 'Rajdhani', sans-serif);
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1px;
      width: 100%;
      position: relative;
      overflow: hidden;
    }

    .submit-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s ease;
    }

    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
    }

    .submit-btn:hover::before {
      left: 100%;
    }

    .success-message {
      display: none;
      background: rgba(76, 175, 80, 0.1);
      border: 1px solid rgba(76, 175, 80, 0.3);
      color: #4caf50;
      padding: 16px;
      border-radius: 12px;
      text-align: center;
      margin-top: 20px;
      font-weight: 600;
    }

    .faq-section {
      margin-top: 40px;
    }

    .faq-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .faq-header h3 {
      font-size: 24px;
      font-weight: 600;
      color: var(--white);
      margin-bottom: 8px;
      text-transform: uppercase;
    }

    .faq-item {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      margin-bottom: 16px;
      overflow: hidden;
    }

    .faq-question {
      padding: 20px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.3s ease;
    }

    .faq-question:hover {
      background: rgba(255, 255, 255, 0.05);
    }

    .faq-question h4 {
      font-size: 16px;
      font-weight: 600;
      color: var(--white);
    }

    .faq-answer {
      padding: 0 20px 20px;
      color: var(--light-gray);
      line-height: 1.6;
      display: none;
    }

    .faq-item.active .faq-answer {
      display: block;
    }

    .whatsapp-float {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background: #25D366;
      border-radius: 50%;
      width: 60px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 999;
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(37, 211, 102, 0.3);
      text-decoration: none;
    }

    .whatsapp-float:hover {
      transform: scale(1.1);
      box-shadow: 0 12px 35px rgba(37, 211, 102, 0.4);
    }

    .whatsapp-float ion-icon {
      font-size: 28px;
      color: white;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .contact-container {
        margin: 20px;
        padding: 30px 20px;
      }

      .brand-text {
        font-size: 28px;
      }

      h2 {
        font-size: 24px;
      }

      .support-options {
        grid-template-columns: 1fr;
        gap: 16px;
      }

      .contact-form {
        padding: 20px;
      }

      input,
      textarea {
        font-size: 14px;
        padding: 12px 14px;
      }

      .submit-btn {
        font-size: 14px;
        padding: 14px 28px;
      }
    }

    @media (max-width: 480px) {
      .contact-container {
        margin: 10px;
        padding: 20px 15px;
      }

      .brand-text {
        font-size: 24px;
      }

      h2 {
        font-size: 20px;
      }

      .whatsapp-float {
        bottom: 20px;
        right: 20px;
        width: 55px;
        height: 55px;
      }

      .whatsapp-float ion-icon {
        font-size: 24px;
      }
    }

  </style>
</head>
<body>

  <div class="contact-container">
    <a href="./dashboard.php" class="back-arrow">
      <ion-icon name="arrow-back-outline"></ion-icon>
      <span>Back to Dashboard</span>
    </a>
    
    <div class="header-section">
      <div class="logo">
        <h1 class="brand-text">KGX</h1>
        <span class="brand-tagline">GAMING XTREME</span>
      </div>
      <h2>Help & Support</h2>
      <p class="subtitle">Get the assistance you need for the ultimate gaming experience</p>
    </div>

    <div class="support-options">
      <div class="support-card" onclick="scrollToForm()">
        <div class="support-icon">
          <ion-icon name="mail-outline"></ion-icon>
        </div>
        <h3>Email Support</h3>
        <p>Send us a message and we'll respond within 24 hours</p>
      </div>
      
      <div class="support-card" onclick="openWhatsApp()">
        <div class="support-icon">
          <ion-icon name="logo-whatsapp"></ion-icon>
        </div>
        <h3>Live Chat</h3>
        <p>Get instant help via WhatsApp for urgent issues</p>
      </div>
      
      <div class="support-card" onclick="toggleFAQ()">
        <div class="support-icon">
          <ion-icon name="help-circle-outline"></ion-icon>
        </div>
        <h3>FAQ</h3>
        <p>Find answers to commonly asked questions</p>
      </div>
    </div>

    <div class="contact-form" id="contactForm">
      <div class="form-header">
        <h3>Send us a Message</h3>
        <p>Fill out the form below and we'll get back to you as soon as possible</p>
      </div>
      
      <form id="supportForm">
        <div class="form-group">
          <label for="name">
            <ion-icon name="person-outline"></ion-icon>
            Your Name
          </label>
          <input type="text" name="name" id="name" placeholder="Enter your full name" required />
        </div>
        
        <div class="form-group">
          <label for="email">
            <ion-icon name="mail-outline"></ion-icon>
            Email Address
          </label>
          <input type="email" name="email" id="email" placeholder="Enter your email address" required />
        </div>
        
        <div class="form-group">
          <label for="subject">
            <ion-icon name="bookmark-outline"></ion-icon>
            Subject
          </label>
          <select name="subject" id="subject" required>
            <option value="">Select a topic</option>
            <option value="account">Account Issues</option>
            <option value="tournament">Tournament Support</option>
            <option value="payment">Payment Problems</option>
            <option value="technical">Technical Issues</option>
            <option value="feedback">Feedback & Suggestions</option>
            <option value="other">Other</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="message">
            <ion-icon name="chatbubble-outline"></ion-icon>
            Your Message
          </label>
          <textarea name="message" id="message" placeholder="Describe your issue or question in detail..." required></textarea>
        </div>
        
        <button type="submit" class="submit-btn">
          <span>Send Message</span>
          <ion-icon name="paper-plane-outline"></ion-icon>
        </button>
        
        <div class="success-message" id="successMessage">
          <ion-icon name="checkmark-circle-outline"></ion-icon>
          Thanks! We've received your message and will get back to you within 24 hours.
        </div>
      </form>
    </div>

    <div class="faq-section" id="faqSection" style="display: none;">
      <div class="faq-header">
        <h3>Frequently Asked Questions</h3>
        <p>Quick answers to common questions</p>
      </div>
      
      <div class="faq-item">
        <div class="faq-question" onclick="toggleFAQItem(this)">
          <h4>How do I register for tournaments?</h4>
          <ion-icon name="chevron-down-outline"></ion-icon>
        </div>
        <div class="faq-answer">
          <p>To register for tournaments, go to the Tournaments section, choose your desired tournament, and click "Register". Make sure you have enough coins or tickets to participate.</p>
        </div>
      </div>
      
      <div class="faq-item">
        <div class="faq-question" onclick="toggleFAQItem(this)">
          <h4>How do I earn coins and tickets?</h4>
          <ion-icon name="chevron-down-outline"></ion-icon>
        </div>
        <div class="faq-answer">
          <p>You can earn coins by winning tournaments, completing daily challenges, and participating in special events. Tickets can be purchased or earned through achievements.</p>
        </div>
      </div>
      
      <div class="faq-item">
        <div class="faq-question" onclick="toggleFAQItem(this)">
          <h4>What games are supported?</h4>
          <ion-icon name="chevron-down-outline"></ion-icon>
        </div>
        <div class="faq-answer">
          <p>We currently support PUBG, BGMI, Free Fire, and Call of Duty Mobile. More games will be added based on community demand.</p>
        </div>
      </div>
      
      <div class="faq-item">
        <div class="faq-question" onclick="toggleFAQItem(this)">
          <h4>How do I update my game profile?</h4>
          <ion-icon name="chevron-down-outline"></ion-icon>
        </div>
        <div class="faq-answer">
          <p>Go to Dashboard > Game Profile to update your gaming username, UID, and level for any supported game. You can have multiple game profiles.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- WhatsApp Floating Button -->
  <a href="https://wa.me/91XXXXXXXXXX" target="_blank" class="whatsapp-float" title="Chat on WhatsApp">
    <ion-icon name="logo-whatsapp"></ion-icon>
  </a>
  
  

  <script>
    // Support card functionality
    function scrollToForm() {
      document.getElementById('contactForm').scrollIntoView({ behavior: 'smooth' });
    }
    
    function openWhatsApp() {
      window.open('https://wa.me/91XXXXXXXXXX', '_blank');
    }
    
    function toggleFAQ() {
      const faqSection = document.getElementById('faqSection');
      const contactForm = document.getElementById('contactForm');
      
      if (faqSection.style.display === 'none' || faqSection.style.display === '') {
        faqSection.style.display = 'block';
        contactForm.style.display = 'none';
        faqSection.scrollIntoView({ behavior: 'smooth' });
      } else {
        faqSection.style.display = 'none';
        contactForm.style.display = 'block';
        contactForm.scrollIntoView({ behavior: 'smooth' });
      }
    }
    
    function toggleFAQItem(element) {
      const faqItem = element.closest('.faq-item');
      const icon = element.querySelector('ion-icon');
      
      faqItem.classList.toggle('active');
      
      if (faqItem.classList.contains('active')) {
        icon.name = 'chevron-up-outline';
      } else {
        icon.name = 'chevron-down-outline';
      }
    }
    
    // Form submission handling
    document.getElementById('supportForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const subject = document.getElementById('subject').value;
      const message = document.getElementById('message').value.trim();

      if (!name || !email || !subject || !message) {
        alert('Please fill out all required fields.');
        return;
      }

      // Simulate successful send
      const successMessage = document.getElementById('successMessage');
      successMessage.style.display = 'block';
      
      // Reset form
      this.reset();
      
      // Hide success message after 5 seconds
      setTimeout(() => {
        successMessage.style.display = 'none';
      }, 5000);
    });
    
    // Add some animations on load
    document.addEventListener('DOMContentLoaded', function() {
      // Animate support cards
      const supportCards = document.querySelectorAll('.support-card');
      supportCards.forEach((card, index) => {
        setTimeout(() => {
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, index * 200);
      });
    });
    
    // Add select styling
    const selectElement = document.getElementById('subject');
    selectElement.style.cursor = 'pointer';
    selectElement.style.appearance = 'none';
    selectElement.style.backgroundImage = "url(\"data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e\")";
    selectElement.style.backgroundRepeat = 'no-repeat';
    selectElement.style.backgroundPosition = 'right 12px center';
    selectElement.style.backgroundSize = '16px';
    selectElement.style.paddingRight = '40px';
  </script>

</body>
</html>
