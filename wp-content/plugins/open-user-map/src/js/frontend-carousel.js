/**
 * Carousel Module - Handles carousel functionality for location images
 */
const OUMCarousel = (function () {
  function initializeCarousel() {
    // Initialize carousels that are added later, including lazy-loaded popup content.
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.addedNodes.length) {
          mutation.addedNodes.forEach((node) => {
            setupCarouselsInNode(node);
          });
        }

        setupCarouselsInNode(mutation.target);
      });
    });

    // Start observing the document body for popup changes
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true
    });

    // Initialize carousels in list view
    document.querySelectorAll('.open-user-map-locations-list .oum-carousel').forEach(carousel => {
      setupCarousel(carousel);
    });

    // Initialize carousels in location value
    document.querySelectorAll('.oum-location-value .oum-carousel').forEach(carousel => {
      setupCarousel(carousel);
    });
  }

  function setupCarouselsInNode(node) {
    if (!node || node.nodeType !== 1) return;

    if (node.classList && node.classList.contains("oum-carousel")) {
      setupCarousel(node);
    }

    node.querySelectorAll(".oum-carousel").forEach((carousel) => {
      setupCarousel(carousel);
    });
  }

  function setupCarousel(carouselEl) {
    if (!carouselEl) return;
    if (carouselEl.dataset.oumCarouselInitialized === "1") return;

    const items = carouselEl.querySelectorAll(".oum-carousel-item");
    if (items.length <= 1) return;

    carouselEl.dataset.oumCarouselInitialized = "1";

    let currentIndex = 0;

    // Create navigation elements
    const { prevBtn, nextBtn, counter } = createCarouselControls();
    
    carouselEl.appendChild(prevBtn);
    carouselEl.appendChild(nextBtn);
    carouselEl.appendChild(counter);

    // Setup navigation
    setupCarouselNavigation(items, prevBtn, nextBtn, counter, currentIndex);

    // Initialize first slide
    items[0].classList.add("active");
    updateCounter();

    function updateCounter() {
      counter.textContent = `${currentIndex + 1}/${items.length}`;
    }
  }

  function createCarouselControls() {
    const prevBtn = document.createElement("button");
    prevBtn.className = "oum-carousel-prev";
    prevBtn.setAttribute("aria-label", "Previous image");

    const nextBtn = document.createElement("button");
    nextBtn.className = "oum-carousel-next";
    nextBtn.setAttribute("aria-label", "Next image");

    const counter = document.createElement("div");
    counter.className = "oum-carousel-counter";

    return { prevBtn, nextBtn, counter };
  }

  function setupCarouselNavigation(items, prevBtn, nextBtn, counter, currentIndex) {
    function goToSlide(index) {
      items[currentIndex].classList.remove("active");
      currentIndex = index;
      if (currentIndex < 0) currentIndex = items.length - 1;
      if (currentIndex >= items.length) currentIndex = 0;
      items[currentIndex].classList.add("active");
      counter.textContent = `${currentIndex + 1}/${items.length}`;
    }

    prevBtn.addEventListener("click", () => goToSlide(currentIndex - 1));
    nextBtn.addEventListener("click", () => goToSlide(currentIndex + 1));

    // Add keyboard navigation
    document.addEventListener("keydown", (e) => {
      if (!items[currentIndex].closest(".leaflet-popup-content-wrapper")) return;

      if (e.key === "ArrowLeft") goToSlide(currentIndex - 1);
      if (e.key === "ArrowRight") goToSlide(currentIndex + 1);
    });

    // Add touch support
    let touchStartX = 0;
    items[0].parentElement.addEventListener("touchstart", (e) => {
      touchStartX = e.changedTouches[0].screenX;
    }, false);

    items[0].parentElement.addEventListener("touchend", (e) => {
      const touchEndX = e.changedTouches[0].screenX;
      const diff = touchStartX - touchEndX;
      if (Math.abs(diff) > 50) {
        if (diff > 0) goToSlide(currentIndex + 1);
        else goToSlide(currentIndex - 1);
      }
    }, false);
  }

  // Public interface
  return {
    init: function () {
      initializeCarousel();
    }
  };
})();

// Initialize when DOM is ready
jQuery(document).ready(function($) {
  OUMCarousel.init();
}); 