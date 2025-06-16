// Select all navigation list items
const list = document.querySelectorAll(".navigation li");

// Function to set the clicked item as active
function setActiveLink() {
  list.forEach((item) => item.classList.remove("hovered"));
  this.classList.add("hovered");
}

// Add click event to each list item
list.forEach((item) => item.addEventListener("click", setActiveLink));

// Optional: Add hover effect using mouseenter
list.forEach((item) => {
  item.addEventListener("mouseenter", () => {
    item.classList.add("hovered");
  });

  item.addEventListener("mouseleave", () => {
    // Only remove hover if it's not the active one
    if (!item.classList.contains("hovered-clicked")) {
      item.classList.remove("hovered");
    }
  });
});

// Sidebar toggle
const toggle = document.querySelector(".toggle");
const navigation = document.querySelector(".navigation");
const main = document.querySelector(".main");

toggle.onclick = function () {
  navigation.classList.toggle("active");
  main.classList.toggle("active");
}; 