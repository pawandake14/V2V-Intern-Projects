// ===== MAIN JAVASCRIPT FILE =====

// Global variables
let isLoading = false;
let currentUser = null;
let roomsData = [];
let testimonialsData = [];

// ===== INITIALIZATION =====
document.addEventListener("DOMContentLoaded", function () {
  initializeApp();
});

function initializeApp() {
  // Initialize AOS (Animate On Scroll)
  AOS.init({
    duration: 800,
    easing: "ease-out-cubic",
    once: true,
    offset: 100,
  });

  // Initialize loading screen
  initializeLoadingScreen();

  // Initialize navigation
  initializeNavigation();

  // Initialize smooth scrolling
  initializeSmoothScrolling();

  // Initialize scroll effects
  initializeScrollEffects();

  // Load dynamic content
  loadRoomsData();
  loadTestimonials();

  // Check authentication status
  checkAuthStatus();

  // Initialize form handlers
  initializeFormHandlers();

  // Initialize interactive elements
  initializeInteractiveElements();

  console.log("Hotel website initialized successfully");
}

// ===== LOADING SCREEN =====
function initializeLoadingScreen() {
  const loadingScreen = document.getElementById("loading-screen");

  // Simulate loading time
  setTimeout(() => {
    if (loadingScreen) {
      loadingScreen.style.opacity = "0";
      setTimeout(() => {
        loadingScreen.style.display = "none";
      }, 500);
    }
  }, 2000);
}

// ===== NAVIGATION =====
function initializeNavigation() {
  const navbar = document.getElementById("mainNav");

  // Navbar scroll effect
  window.addEventListener("scroll", function () {
    if (window.scrollY > 100) {
      navbar.classList.add("scrolled");
    } else {
      navbar.classList.remove("scrolled");
    }
  });

  // Mobile menu close on link click
  const navLinks = document.querySelectorAll(".navbar-nav .nav-link");
  const navbarCollapse = document.querySelector(".navbar-collapse");

  navLinks.forEach((link) => {
    link.addEventListener("click", () => {
      if (navbarCollapse.classList.contains("show")) {
        const bsCollapse = new bootstrap.Collapse(navbarCollapse);
        bsCollapse.hide();
      }
    });
  });
}

// ===== SMOOTH SCROLLING =====
function initializeSmoothScrolling() {
  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        const offsetTop = target.offsetTop - 80; // Account for fixed navbar
        window.scrollTo({
          top: offsetTop,
          behavior: "smooth",
        });
      }
    });
  });
}

// ===== SCROLL EFFECTS =====
function initializeScrollEffects() {
  // Parallax effect for hero section
  window.addEventListener("scroll", function () {
    const scrolled = window.pageYOffset;
    const parallaxElements = document.querySelectorAll(".parallax");

    parallaxElements.forEach((element) => {
      const speed = element.dataset.speed || 0.5;
      const yPos = -(scrolled * speed);
      element.style.transform = `translateY(${yPos}px)`;
    });
  });

  // Scroll reveal animations
  const observerOptions = {
    threshold: 0.1,
    rootMargin: "0px 0px -50px 0px",
  };

  const observer = new IntersectionObserver(function (entries) {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("revealed");
      }
    });
  }, observerOptions);

  document
    .querySelectorAll(
      ".scroll-reveal, .scroll-reveal-left, .scroll-reveal-right, .scroll-reveal-scale"
    )
    .forEach((el) => {
      observer.observe(el);
    });
}

// ===== AUTHENTICATION =====
async function checkAuthStatus() {
  try {
    const response = await fetch(
      "/hotel-website/backend/api/auth.php?action=check"
    );
    const data = await response.json();

    if (data.success) {
      if (data.data.user_logged_in) {
        const userResponse = await fetch(
          "/hotel-website/backend/api/auth.php?action=user"
        );
        const userData = await userResponse.json();
        if (userData.success) {
          currentUser = userData.data;
          updateUIForLoggedInUser();
        }
      }
    }
  } catch (error) {
    console.error("Error checking auth status:", error);
  }
}

function updateUIForLoggedInUser() {
  const loginLink = document.querySelector('a[href="pages/login.html"]');
  if (loginLink && currentUser) {
    loginLink.innerHTML = `<i class="fas fa-user me-1"></i>${currentUser.first_name}`;
    loginLink.href = "pages/profile.html";
  }
}

// ===== DYNAMIC CONTENT LOADING =====
async function loadRoomsData() {
  try {
    showLoading();
    const response = await fetch(
      "/hotel-website/backend/api/rooms.php?action=list"
    );
    const data = await response.json();

    if (data.success) {
      roomsData = data.data;
      displayRooms(roomsData.slice(0, 3)); // Show first 3 rooms on homepage
    } else {
      console.error("Failed to load rooms:", data.message);
      displayRoomsError();
    }
  } catch (error) {
    console.error("Error loading rooms:", error);
    displayRoomsError();
  } finally {
    hideLoading();
  }
}

function displayRooms(rooms) {
  const container = document.getElementById("rooms-container");
  if (!container) return;

  container.innerHTML = "";

  rooms.forEach((room, index) => {
    const roomCard = createRoomCard(room, index);
    container.appendChild(roomCard);
  });
}

function createRoomCard(room, index) {
  const col = document.createElement("div");
  col.className = "col-lg-4 col-md-6 mb-4";
  col.setAttribute("data-aos", "fade-up");
  col.setAttribute("data-aos-delay", (index * 100).toString());

  const amenitiesHtml = room.amenities
    ? room.amenities
        .slice(0, 3)
        .map((amenity) => `<span class="amenity-tag">${amenity}</span>`)
        .join("")
    : "";

  col.innerHTML = `
        <div class="room-card">
            <div class="room-image">
                <img src="${
                  room.image_url || "images/rooms/default.jpg"
                }" alt="${room.name}" class="img-fluid">
                <div class="room-price">$${room.base_price}/night</div>
            </div>
            <div class="room-content">
                <h3 class="room-title">${room.name}</h3>
                <p class="room-description">${
                  room.description ||
                  "Luxurious accommodation with premium amenities"
                }</p>
                <div class="room-amenities">
                    ${amenitiesHtml}
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Max ${
                      room.max_occupancy
                    } guests</span>
                    <a href="pages/booking.html?room_type=${
                      room.id
                    }" class="btn btn-primary">Book Now</a>
                </div>
            </div>
        </div>
    `;

  return col;
}

function displayRoomsError() {
  const container = document.getElementById("rooms-container");
  if (!container) return;

  container.innerHTML = `
        <div class="col-12 text-center">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Unable to load room information at the moment. Please try again later.
            </div>
        </div>
    `;
}

async function loadTestimonials() {
  try {
    const response = await fetch(
      "/hotel-website/backend/api/feedback.php?action=ratings&rating_type=overall&limit=6"
    );
    const data = await response.json();

    if (data.success && data.data.ratings) {
      testimonialsData = data.data.ratings;
      displayTestimonials(testimonialsData);
    } else {
      displayDefaultTestimonials();
    }
  } catch (error) {
    console.error("Error loading testimonials:", error);
    displayDefaultTestimonials();
  }
}

function displayTestimonials(testimonials) {
  const container = document.getElementById("testimonials-container");
  if (!container) return;

  container.innerHTML = "";

  const testimonialsToShow =
    testimonials.length > 0
      ? testimonials.slice(0, 3)
      : getDefaultTestimonials();

  testimonialsToShow.forEach((testimonial, index) => {
    const testimonialCard = createTestimonialCard(testimonial, index);
    container.appendChild(testimonialCard);
  });
}

function createTestimonialCard(testimonial, index) {
  const col = document.createElement("div");
  col.className = "col-lg-4 col-md-6 mb-4";
  col.setAttribute("data-aos", "fade-up");
  col.setAttribute("data-aos-delay", (index * 100).toString());

  const rating = testimonial.rating || 5;
  const starsHtml = "★".repeat(rating) + "☆".repeat(5 - rating);
  const authorName = testimonial.first_name
    ? `${testimonial.first_name} ${testimonial.last_name}`
    : testimonial.name || "Anonymous Guest";

  col.innerHTML = `
        <div class="testimonial-card">
            <div class="testimonial-rating">${starsHtml}</div>
            <p class="testimonial-text">"${
              testimonial.review || testimonial.text
            }"</p>
            <div class="testimonial-author">
                <img src="images/avatars/female_avatar.png" alt="${authorName}" class="author-avatar">
                <div class="author-info">
                    <h5>${authorName}</h5>
                    <span>Verified Guest</span>
                </div>
            </div>
        </div>
    `;

  return col;
}

function displayDefaultTestimonials() {
  const defaultTestimonials = getDefaultTestimonials();
  displayTestimonials(defaultTestimonials);
}

function getDefaultTestimonials() {
  return [
    {
      rating: 5,
      review:
        "Absolutely stunning hotel with exceptional service. The staff went above and beyond to make our stay memorable.",
      name: "Sarah Johnson",
    },
    {
      rating: 5,
      review:
        "The luxury and attention to detail at this hotel is unmatched. Every moment was pure bliss.",
      name: "Michael Chen",
    },
    {
      rating: 5,
      review:
        "From the elegant rooms to the world-class dining, everything exceeded our expectations.",
      name: "Emma Rodriguez",
    },
  ];
}

// ===== FORM HANDLERS =====
function initializeFormHandlers() {
  // Newsletter form
  const newsletterForm = document.querySelector(".newsletter-form");
  if (newsletterForm) {
    newsletterForm.addEventListener("submit", handleNewsletterSubmit);
  }

  // Contact forms
  const contactForms = document.querySelectorAll(".contact-form");
  contactForms.forEach((form) => {
    form.addEventListener("submit", handleContactSubmit);
  });
}

function handleNewsletterSubmit(e) {
  e.preventDefault();
  const email = e.target.querySelector('input[type="email"]').value;

  if (validateEmail(email)) {
    showNotification("Thank you for subscribing to our newsletter!", "success");
    e.target.reset();
  } else {
    showNotification("Please enter a valid email address.", "error");
  }
}

function handleContactSubmit(e) {
  e.preventDefault();
  // Handle contact form submission
  showNotification(
    "Thank you for your message. We will get back to you soon!",
    "success"
  );
  e.target.reset();
}

// ===== INTERACTIVE ELEMENTS =====
function initializeInteractiveElements() {
  // Image hover effects
  const images = document.querySelectorAll(
    ".room-image img, .amenity-image img"
  );
  images.forEach((img) => {
    img.addEventListener("mouseenter", function () {
      this.style.transform = "scale(1.05)";
    });

    img.addEventListener("mouseleave", function () {
      this.style.transform = "scale(1)";
    });
  });

  // Button hover effects
  const buttons = document.querySelectorAll(".btn");
  buttons.forEach((btn) => {
    btn.addEventListener("mouseenter", function () {
      this.style.transform = "translateY(-2px)";
    });

    btn.addEventListener("mouseleave", function () {
      this.style.transform = "translateY(0)";
    });
  });

  // Card hover effects
  const cards = document.querySelectorAll(
    ".feature-card, .room-card, .amenity-card, .testimonial-card, .contact-card"
  );
  cards.forEach((card) => {
    card.addEventListener("mouseenter", function () {
      this.style.transform = "translateY(-5px)";
      this.style.boxShadow = "var(--shadow-medium)";
    });

    card.addEventListener("mouseleave", function () {
      this.style.transform = "translateY(0)";
      this.style.boxShadow = "var(--shadow-light)";
    });
  });
}

// ===== UTILITY FUNCTIONS =====
function showLoading() {
  isLoading = true;
  // Add loading spinner or overlay
  const loadingSpinner = document.createElement("div");
  loadingSpinner.id = "page-loading";
  loadingSpinner.innerHTML = `
        <div class="loading-overlay">
            <div class="spinner-ring">
                <div></div>
                <div></div>
                <div></div>
                <div></div>
            </div>
        </div>
    `;
  document.body.appendChild(loadingSpinner);
}

function hideLoading() {
  isLoading = false;
  const loadingSpinner = document.getElementById("page-loading");
  if (loadingSpinner) {
    loadingSpinner.remove();
  }
}

function showNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)} me-2"></i>
            ${message}
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

  document.body.appendChild(notification);

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

function getNotificationIcon(type) {
  switch (type) {
    case "success":
      return "check-circle";
    case "error":
      return "exclamation-circle";
    case "warning":
      return "exclamation-triangle";
    default:
      return "info-circle";
  }
}

function validateEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

function formatCurrency(amount) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);
}

function formatDate(date) {
  return new Intl.DateTimeFormat("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  }).format(new Date(date));
}

// ===== RESTAURANT VIDEO PLAYER =====
function playRestaurantVideo() {
  // Create modal for video player
  const modal = document.createElement("div");
  modal.className = "video-modal";
  modal.innerHTML = `
        <div class="video-modal-content">
            <span class="video-modal-close" onclick="this.parentElement.parentElement.remove()">&times;</span>
            <video controls autoplay>
                <source src="videos/restaurant-tour.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
    `;

  document.body.appendChild(modal);

  // Close modal when clicking outside
  modal.addEventListener("click", function (e) {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

// ===== SEARCH FUNCTIONALITY =====
function searchRooms(query) {
  if (!query) {
    displayRooms(roomsData.slice(0, 3));
    return;
  }

  const filteredRooms = roomsData.filter(
    (room) =>
      room.name.toLowerCase().includes(query.toLowerCase()) ||
      room.description.toLowerCase().includes(query.toLowerCase())
  );

  displayRooms(filteredRooms.slice(0, 3));
}

// ===== BOOKING HELPERS =====
function redirectToBooking(roomTypeId) {
  window.location.href = `pages/booking.html?room_type=${roomTypeId}`;
}

function checkAvailability(checkIn, checkOut, roomType) {
  // This would typically make an API call to check availability
  console.log("Checking availability:", { checkIn, checkOut, roomType });
}

// ===== ERROR HANDLING =====
window.addEventListener("error", function (e) {
  console.error("JavaScript error:", e.error);
  // You could send error reports to a logging service here
});

window.addEventListener("unhandledrejection", function (e) {
  console.error("Unhandled promise rejection:", e.reason);
  // Handle unhandled promise rejections
});

// ===== PERFORMANCE OPTIMIZATION =====
// Debounce function for scroll events
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Throttle function for resize events
function throttle(func, limit) {
  let inThrottle;
  return function () {
    const args = arguments;
    const context = this;
    if (!inThrottle) {
      func.apply(context, args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
}

// ===== ACCESSIBILITY =====
// Keyboard navigation support
document.addEventListener("keydown", function (e) {
  // Handle keyboard navigation for interactive elements
  if (e.key === "Enter" || e.key === " ") {
    const focusedElement = document.activeElement;
    if (focusedElement.classList.contains("clickable")) {
      focusedElement.click();
    }
  }
});

// Focus management for modals
function trapFocus(element) {
  const focusableElements = element.querySelectorAll(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  );
  const firstElement = focusableElements[0];
  const lastElement = focusableElements[focusableElements.length - 1];

  element.addEventListener("keydown", function (e) {
    if (e.key === "Tab") {
      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          lastElement.focus();
          e.preventDefault();
        }
      } else {
        if (document.activeElement === lastElement) {
          firstElement.focus();
          e.preventDefault();
        }
      }
    }
  });
}

console.log("Main JavaScript loaded successfully");
