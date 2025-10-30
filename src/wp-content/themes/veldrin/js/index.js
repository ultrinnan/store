console.log('Veldrin ultrin!')

// Hamburger menu toggle
document.addEventListener('DOMContentLoaded', function() {
  const hamburger = document.querySelector('.hamburger');
  const menu = document.querySelector('.header_menu');
  const body = document.body;

  if (hamburger && menu) {
    hamburger.addEventListener('click', function() {
      // Toggle active class on menu
      menu.classList.toggle('active');

      // Toggle hidden_smooth class on hamburger
      hamburger.classList.toggle('hidden_smooth');

      // Toggle body lock to prevent scrolling
      body.classList.toggle('lock');
    });

    // Close menu when clicking on a menu item
    const menuItems = menu.querySelectorAll('.menu-item a');
    menuItems.forEach(function(item) {
      item.addEventListener('click', function() {
        menu.classList.remove('active');
        hamburger.classList.remove('hidden_smooth');
        body.classList.remove('lock');
      });
    });

    // Close menu when clicking on the overlay (outside menu_list)
    menu.addEventListener('click', function(e) {
      // Check if click is on the overlay (header_menu) and not on menu_list or its children
      if (e.target === menu || !e.target.closest('.menu_list')) {
        menu.classList.remove('active');
        hamburger.classList.remove('hidden_smooth');
        body.classList.remove('lock');
      }
    });
  }
});