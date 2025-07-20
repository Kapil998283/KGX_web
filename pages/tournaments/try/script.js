"use strict";

/**
 * Element toggle function
 */
const elemToggleFunc = function (elem) {
  elem.classList.toggle("active");
};




/**
 * Navbar variables
 */
const navbar = document.querySelector("[data-nav]");
const navOpenBtn = document.querySelector("[data-nav-open-btn]");
const navCloseBtn = document.querySelector("[data-nav-close-btn]");
const overlay = document.querySelector("[data-overlay]");

const navElemArr = [navOpenBtn, navCloseBtn, overlay];

navElemArr.forEach((element) => {
  element.addEventListener("click", function () {
    elemToggleFunc(navbar);
    elemToggleFunc(overlay);
    elemToggleFunc(document.body);
  });
});



/**
 * Go to top button functionality
 */
const goTopBtn = document.querySelector("[data-go-top]");

window.addEventListener("scroll", function () {
  if (window.scrollY >= 800) {
    goTopBtn.classList.add("active");
  } else {
    goTopBtn.classList.remove("active");
  }
});


/**
 * FAQ Toggle
 */
document.querySelectorAll(".faq-question").forEach((btn) => {
  btn.addEventListener("click", function () {
    this.parentElement.classList.toggle("active");
  });
});

/**
 * Show Section Based on Footer Click
 */
document.querySelectorAll(".quicklink-item").forEach((link) => {
  link.addEventListener("click", function (e) {
    e.preventDefault();

    document.querySelectorAll(".content-section").forEach((section) => {
      section.classList.remove("active");
      section.style.display = "none";
    });

    const targetSection = this.innerText.toLowerCase().replace(/\s+/g, "");

    const sectionMap = {
      faq: "faq",
      helpcenter: "help",
      termsofuse: "terms",
      privacy: "privacy",
    };

    const sectionId = sectionMap[targetSection];
    const sectionToShow = document.querySelector(`#${sectionId}`);

    if (sectionToShow) {
      sectionToShow.style.display = "block";
      sectionToShow.classList.add("active");
      sectionToShow.scrollIntoView({ behavior: "smooth" });
    }
  });
});

/**
 * Close Section on Button Click
 */
function closeSection() {
  document.querySelectorAll(".content-section").forEach((section) => {
    section.classList.remove("active");
    section.style.display = "none";
  });
}






document.addEventListener("DOMContentLoaded", function () {
  // Navbar Dropdowns
  const navbarDropdowns = document.querySelectorAll(".navbar .dropdown");
  navbarDropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector(".dropdown-toggle");
    
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      dropdown.classList.toggle("active");

      // Close other navbar dropdowns
      navbarDropdowns.forEach((item) => {
        if (item !== dropdown) {
          item.classList.remove("active");
        }
      });
    });
  });

  // Header Icons Dropdowns (Wallet, Notifications, Profile)
  const headerDropdowns = document.querySelectorAll(".header-icons .dropdown");

  headerDropdowns.forEach((dropdown) => {
    const button = dropdown.querySelector("button");
    const content = dropdown.querySelector(".dropdown-content");

    button.addEventListener("click", (e) => {
      e.stopPropagation(); // Prevent event bubbling
      dropdown.classList.toggle("active");

      // Close other header dropdowns
      headerDropdowns.forEach((d) => {
        if (d !== dropdown) {
          d.classList.remove("active");
        }
      });
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove("active");
      }
    });
  });
});


document.addEventListener("DOMContentLoaded", function () {
  function toggleMobileLogo() {
      const mobileLogo = document.querySelector(".logo-mobile-only");
      if (window.innerWidth > 768) {
          mobileLogo.style.display = "none";  // Hide on large screens
      } else {
          mobileLogo.style.display = "block"; // Show on small screens
      }
  }

  // Run the function on load and resize
  toggleMobileLogo();
  window.addEventListener("resize", toggleMobileLogo);
});


document.addEventListener("DOMContentLoaded", function () {
  // Function to toggle dropdown visibility
  function toggleDropdown(buttonId, dropdownId) {
      const button = document.getElementById(buttonId);
      const dropdown = document.getElementById(dropdownId);
      
      button.addEventListener("click", function (event) {
          event.stopPropagation(); // Prevent closing immediately
          dropdown.classList.toggle("active");
      });

      // Close dropdown when clicking outside
      document.addEventListener("click", function (event) {
          if (!dropdown.contains(event.target) && !button.contains(event.target)) {
              dropdown.classList.remove("active");
          }
      });
  }

  // Apply toggle function to Wallet, Notifications, and Profile
  toggleDropdown("wallet-btn", "wallet-dropdown");
  toggleDropdown("notif-btn", "notif-dropdown");
  toggleDropdown("profile-btn", "profile-dropdown");
});


document.querySelectorAll('.dropdown').forEach(dropdown => {
  const button = dropdown.querySelector('.icon-button, .profile-button');
  const content = document.querySelector('.dropdown-content'); // One fixed dropdown
  const allDropdowns = document.querySelectorAll('.dropdown');

  button.addEventListener('click', (event) => {
      event.stopPropagation(); // Prevent closing when clicking inside dropdown
      
      // Close other dropdowns
      allDropdowns.forEach(d => d.classList.remove('active'));

      // Change content based on button clicked
      if (dropdown.classList.contains('wallet-dropdown')) {
          content.innerHTML = "<p>Wallet Content Here</p>";
      } else if (dropdown.classList.contains('notification-dropdown')) {
          content.innerHTML = "<p>Notifications Here</p>";
      } else if (dropdown.classList.contains('profile-dropdown')) {
          content.innerHTML = "<p>Profile Options Here</p>";
      }

      // Show the dropdown
      dropdown.classList.toggle('active');
  });
});

// Close dropdown when clicking outside
document.addEventListener('click', (event) => {
  document.querySelectorAll('.dropdown').forEach(dropdown => {
      if (!dropdown.contains(event.target)) {
          dropdown.classList.remove('active');
      }
  });
});





document.addEventListener("DOMContentLoaded", () => {
  const notifBadge = document.getElementById("notif-count");
  const notifDropdown = document.getElementById("notif-dropdown");

  // Hide badge if no notifications
  if (notifDropdown.children.length === 0) {
      notifBadge.classList.add("hidden");
  }
});


document.addEventListener("DOMContentLoaded", function () {
  // Select all dropdowns
  const dropdowns = document.querySelectorAll(".dropdown");

  dropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector(".dropdown-toggle");

    toggle.addEventListener("click", function (event) {
      // Toggle the dropdown on click (for mobile)
      dropdown.classList.toggle("active");

      // Prevent event from bubbling to document
      event.stopPropagation();
    });
  });

  // Close dropdown when clicking outside
  document.addEventListener("click", function () {
    dropdowns.forEach((dropdown) => {
      dropdown.classList.remove("active");
    });
  });
});






