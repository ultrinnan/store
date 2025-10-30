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
});