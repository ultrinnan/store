<?php
/**
 * Plugin Name: Veldrin – Mobile menu toggles
 * Description: Adds expand/collapse buttons to header submenus on mobile (<=1024px); submenus start collapsed. Styles in Additional CSS (FIXER-MOBILEMENU block). Added by Fixer 2026-11-24.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		$js = <<<'JS'
(function () {
	function init() {
		var menu = document.querySelector('.header_menu');
		if (!menu || menu.classList.contains('vmm-ready')) { return; }
		var items = menu.querySelectorAll('.menu_list > li.menu-item-has-children');
		items.forEach(function (li, i) {
			var link = li.querySelector(':scope > a');
			var sub = li.querySelector(':scope > .sub-menu');
			if (!link || !sub) { return; }
			var id = sub.id || ('vmm-sub-' + i);
			sub.id = id;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'vmm-toggle';
			btn.setAttribute('aria-expanded', 'false');
			btn.setAttribute('aria-controls', id);
			btn.setAttribute('aria-label', 'Show ' + link.textContent.trim() + ' submenu');
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var open = li.classList.toggle('vmm-open');
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
				btn.setAttribute('aria-label', (open ? 'Hide ' : 'Show ') + link.textContent.trim() + ' submenu');
			});
			link.insertAdjacentElement('afterend', btn);
		});
		menu.classList.add('vmm-ready');
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
JS;
		if ( wp_script_is( 'f_scripts', 'enqueued' ) || wp_script_is( 'f_scripts', 'registered' ) ) {
			wp_add_inline_script( 'f_scripts', $js );
		}
	},
	20
);
