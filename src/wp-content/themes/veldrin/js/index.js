// Hamburger menu toggle
document.addEventListener('DOMContentLoaded', function() {
  const hamburger = document.querySelector('.hamburger');
  const menu = document.querySelector('.header_menu');
  const body = document.body;

  if (hamburger && menu) {
    // Function to toggle menu state
    function toggleMenu() {
      const isActive = menu.classList.contains('active');
      menu.classList.toggle('active');
      hamburger.classList.toggle('hidden_smooth');
      body.classList.toggle('lock');

      // Update aria-expanded for accessibility
      hamburger.setAttribute('aria-expanded', !isActive);
    }

    // Function to close menu
    function closeMenu() {
      menu.classList.remove('active');
      hamburger.classList.remove('hidden_smooth');
      body.classList.remove('lock');
      hamburger.setAttribute('aria-expanded', 'false');
    }

    // Click handler
    hamburger.addEventListener('click', toggleMenu);

    // Keyboard handler (Enter and Space keys)
    hamburger.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleMenu();
      }
    });

    // Escape key handler to close menu
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && menu.classList.contains('active')) {
        closeMenu();
      }
    });

    // Close menu when clicking on a menu item
    const menuItems = menu.querySelectorAll('.menu-item a');
    menuItems.forEach(function(item) {
      item.addEventListener('click', closeMenu);
    });

    // Close menu when clicking on the overlay (outside menu_list)
    menu.addEventListener('click', function(e) {
      // Check if click is on the overlay (header_menu) and not on menu_list or its children
      if (e.target === menu || !e.target.closest('.menu_list')) {
        closeMenu();
      }
    });
  }

  // Feedback / contact prompt (bottom-right, delayed)
  var feedbackPopup = document.getElementById('veldrin-feedback-popup');
  if (feedbackPopup) {
    var storageKey = 'veldrin_feedback_popup_dismissed';
    if (sessionStorage.getItem(storageKey)) {
      return;
    }

    var showPopup = function() {
      feedbackPopup.removeAttribute('hidden');
      feedbackPopup.setAttribute('aria-hidden', 'false');
      requestAnimationFrame(function() {
        feedbackPopup.classList.add('is-visible');
      });
    };

    var hidePopup = function() {
      feedbackPopup.classList.remove('is-visible');
      feedbackPopup.setAttribute('aria-hidden', 'true');
      sessionStorage.setItem(storageKey, '1');
      window.setTimeout(function() {
        feedbackPopup.setAttribute('hidden', '');
      }, 400);
    };

    var closeBtn = feedbackPopup.querySelector('.veldrin-feedback-popup__close');
    if (closeBtn) {
      closeBtn.addEventListener('click', hidePopup);
    }

    var cta = feedbackPopup.querySelector('.veldrin-feedback-popup__cta');
    if (cta) {
      cta.addEventListener('click', function() {
        sessionStorage.setItem(storageKey, '1');
      });
    }

    document.addEventListener('keydown', function onEscape(e) {
      if (e.key === 'Escape' && feedbackPopup.classList.contains('is-visible')) {
        hidePopup();
      }
    });

    window.setTimeout(showPopup, 5000);
  }
});