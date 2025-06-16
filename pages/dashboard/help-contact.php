<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Help / Contact Us</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f9f9f9;
      color: #333;
      padding: 20px;
    }

    .contact-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }

    .back-arrow {
      display: inline-flex;
      align-items: center;
      margin-bottom: 20px;
      color: #3b82f6;
      font-weight: 500;
      text-decoration: none;
      font-size: 16px;
    }

    .back-arrow:hover {
      color: #2563eb;
    }

    .back-arrow span {
      margin-left: 6px;
    }

    h2 {
      font-size: 24px;
      margin-bottom: 20px;
      color: #222;
      text-align: center;
    }

    form {
      display: flex;
      flex-direction: column;
    }

    input,
    textarea {
      padding: 10px 14px;
      margin-bottom: 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      background-color: #fff;
      transition: border 0.3s ease;
    }

    input:focus,
    textarea:focus {
      border-color: #3b82f6;
      outline: none;
    }

    textarea {
      resize: vertical;
      min-height: 120px;
    }

    button {
      background-color: #3b82f6;
      color: #fff;
      padding: 12px;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    button:hover {
      background-color: #2563eb;
    }

    .success-message {
      display: none;
      text-align: center;
      color: green;
      margin-top: 10px;
    }

    @media (max-width: 600px) {
      .contact-container {
        padding: 20px;
      }

      h2 {
        font-size: 20px;
      }

      input,
      textarea {
        font-size: 13px;
      }

      button {
        font-size: 14px;
      }
    }

    .whatsapp-float {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background-color: #25D366;
    border-radius: 50%;
    width: 55px;
    height: 55px;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
    transition: transform 0.3s ease;
  }

  .whatsapp-float:hover {
    transform: scale(1.1);
  }

  .whatsapp-float img {
    width: 28px;
    height: 28px;
  }

  </style>
</head>
<body>

  <div class="contact-container">
    <a href="./dashboard.php" class="back-arrow">← <span>Back to Dashboard</span></a>
    <h2>Need Help? Contact Us</h2>
    <form id="contactForm">
      <input type="text" name="name" id="name" placeholder="Your Name" required />
      <input type="email" name="email" id="email" placeholder="Your Email" required />
      <textarea name="message" id="message" placeholder="Type your message here..." required></textarea>
      <button type="submit">Send Message</button>
      <p class="success-message" id="successMessage">Thanks! We'll get back to you shortly.</p>
    </form>
  </div>

  <!-- WhatsApp Floating Button -->
<a href="https://wa.me/91XXXXXXXXXX" target="_blank" class="whatsapp-float" title="Chat on WhatsApp">
    <img src="https://img.icons8.com/ios-filled/50/ffffff/whatsapp.png" alt="WhatsApp" />
  </a>
  
  

  <script>
    // Example form submission handling (you can replace this later with EmailJS/Firebase)
    document.getElementById('contactForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const message = document.getElementById('message').value.trim();

      if (!name || !email || !message) {
        alert('Please fill out all fields.');
        return;
      }

      // Simulate successful send
      document.getElementById('successMessage').style.display = 'block';
      this.reset();
    });
  </script>

</body>
</html>
