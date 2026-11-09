/**
 * F-PRO Bearpaw Dealer - Admin JavaScript
 *
 * @package FPro\BearpawDealer
 */

(function($) {
	'use strict';

	// Get localized data from wp_localize_script
	var fBearpawData = window.fBearpawData || {};
	var ajaxurl = fBearpawData.ajaxurl || window.ajaxurl || (window.wp && wp.ajax && wp.ajax.url) || '/wp-admin/admin-ajax.php';
	var downloadNonce = fBearpawData.nonce || '';
	var step3Url = fBearpawData.step3Url || '';

	/**
	 * Toggle accordion item visibility.
	 *
	 * @param {number} step Step number (1-4).
	 */
	window.toggleAccordion = function(step) {
		// Close all accordion items
		$('.f-bearpaw-accordion-item').each(function() {
			$(this).removeClass('active');
			var $toggle = $(this).find('.f-bearpaw-accordion-toggle');
			if ($toggle.length) {
				$toggle.text('+');
			}
		});

		// Open selected step
		var $targetItem = $('.f-bearpaw-accordion-item:nth-child(' + step + ')');
		if ($targetItem.length && !$targetItem.hasClass('disabled')) {
			$targetItem.addClass('active');
			var $toggle = $targetItem.find('.f-bearpaw-accordion-toggle');
			if ($toggle.length) {
				$toggle.text('−');
			}
		}
	};

	/**
	 * Filter missing products table by search term.
	 */
	window.filterMissingProducts = function() {
		var $input = $('#missing-products-search');
		var filter = $input.val().toLowerCase();
		var $table = $('#missing-products-table');
		var $rows = $table.find('.missing-product-row');
		var visibleCount = 0;

		$rows.each(function() {
			var $row = $(this);
			var sku = $row.data('sku') || '';
			var title = $row.data('title') || '';

			if (sku.indexOf(filter) > -1 || title.indexOf(filter) > -1) {
				$row.show();
				visibleCount++;
			} else {
				$row.hide();
			}
		});

		var $countElement = $('#filtered-count');
		if ($countElement.length) {
			$countElement.text(visibleCount);
		}
	};

	/**
	 * Sort missing products table by column.
	 *
	 * @param {number} columnIndex Column index (1-based).
	 * @param {string} dataType Data type ('string' or 'number').
	 */
	window.sortTable = function(columnIndex, dataType) {
		var $table = $('#missing-products-table');
		var $tbody = $table.find('tbody');
		var $rows = $tbody.find('.missing-product-row').toArray();

		// Determine sort direction
		var currentSortColumn = $table.data('sort-column') || -1;
		var currentSortDirection = $table.data('sort-direction') || 'asc';

		if (currentSortColumn === columnIndex) {
			currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
		} else {
			currentSortColumn = columnIndex;
			currentSortDirection = 'asc';
		}

		// Store sort state
		$table.data('sort-column', currentSortColumn);
		$table.data('sort-direction', currentSortDirection);

		// Update sort indicators
		var $headers = $table.find('th');
		$headers.each(function(i) {
			var $indicator = $(this).find('.sort-indicator');
			if ($indicator.length) {
				if (i === columnIndex) {
					$indicator.text(currentSortDirection === 'asc' ? ' ↑' : ' ↓');
				} else {
					$indicator.text(' ⇅');
				}
			}
		});

		// Sort rows
		$rows.sort(function(a, b) {
			var $a = $(a);
			var $b = $(b);
			var aValue, bValue;

			if (columnIndex === 1) {
				// SKU column
				aValue = $a.find('td:eq(1)').text().trim();
				bValue = $b.find('td:eq(1)').text().trim();
			} else if (columnIndex === 2) {
				// Title column
				aValue = $a.find('td:eq(2)').text().trim();
				bValue = $b.find('td:eq(2)').text().trim();
			} else if (columnIndex === 3) {
				// Price column
				var aText = $a.find('td:eq(3)').text().trim();
				var bText = $b.find('td:eq(3)').text().trim();
				aValue = parseFloat(aText.replace(/[^0-9.,]/g, '').replace(',', '.')) || 0;
				bValue = parseFloat(bText.replace(/[^0-9.,]/g, '').replace(',', '.')) || 0;
			} else {
				return 0;
			}

			if (dataType === 'number') {
				return currentSortDirection === 'asc' ? aValue - bValue : bValue - aValue;
			} else {
				if (aValue < bValue) {
					return currentSortDirection === 'asc' ? -1 : 1;
				}
				if (aValue > bValue) {
					return currentSortDirection === 'asc' ? 1 : -1;
				}
				return 0;
			}
		});

		// Re-append sorted rows
		$rows.forEach(function(row) {
			$tbody.append(row);
		});
	};

	/**
	 * Handle Step 1 form submission.
	 *
	 * @param {Event} event Form submit event.
	 */
	window.handleStep1Save = function(event) {
		var $form = $('#step1-form');
		if ($form.length) {
			var $input = $('<input>').attr({
				type: 'hidden',
				name: 'step1_saved',
				value: '1'
			});
			$form.append($input);
		}
	};

	/**
	 * Handle Step 2 download via AJAX chunks.
	 *
	 * @param {Event} event Form submit event.
	 */
	window.handleStep2Download = function(event) {
		event.preventDefault();

		var $form = $('#step2-form');
		if (!$form.length) {
			return;
		}

		var $button = $form.find('button[type="submit"]');
		if ($button.length) {
			$button.prop('disabled', true).text('⏳ Starting download...');
		}

		// Remove existing progress container
		$('#download-progress-container').remove();

		// Create progress container
		var $progressContainer = $('<div>', {
			id: 'download-progress-container',
			css: {
				'margin-top': '20px',
				'padding': '15px',
				'background': '#fff',
				'border': '1px solid #c3c4c7',
				'box-shadow': '0 1px 1px rgba(0,0,0,.04)'
			}
		}).html(
			'<h3 style="margin-top: 0;">Download Progress</h3>' +
			'<div id="b2b-progress" style="margin-bottom: 20px;"></div>' +
			'<div id="retail-progress" style="margin-bottom: 20px;"></div>' +
			'<div class="f-bearpaw-progress-bar"><div class="f-bearpaw-progress-fill" id="overall-progress-bar" style="width: 0%;"></div></div>' +
			'<p id="overall-progress-text" style="margin-top: 10px; color: #646970;">Initializing...</p>'
		);

		$form.after($progressContainer);

		// Get nonce from localized data
		var nonce = downloadNonce || $form.find('input[name="_wpnonce"]').val() || $('input[name="_wpnonce"]').first().val();

		if (!nonce) {
			alert('Security token missing. Please refresh the page and try again.');
			if ($button.length) {
				$button.prop('disabled', false).text('📥 Download All Products');
			}
			return;
		}

		// Clear progress via AJAX first
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'f_bearpaw_clear_progress',
				nonce: nonce,
				source: ''
			}
		}).always(function() {
			// Start downloading both sources sequentially
			downloadSource('B2B', function() {
				downloadSource('Retail', function() {
					// Both downloads completed
					if ($button.length) {
						$button.text('✅ Download Completed');
					}
					$('#overall-progress-text').text('✅ All downloads completed! Page will reload in 3 seconds...');
					setTimeout(function() {
						window.location.href = step3Url;
					}, 3000);
				});
			});
		});
	};

	/**
	 * Download a source in chunks.
	 *
	 * @param {string} source Source name ('B2B' or 'Retail').
	 * @param {Function} callback Callback when done.
	 */
	function downloadSource(source, callback) {
		var $progressDiv = $('#' + source.toLowerCase() + '-progress');
		$progressDiv.html(
			'<p><strong>' + source + ':</strong> <span id="' + source.toLowerCase() + '-status">Starting...</span></p>' +
			'<div class="f-bearpaw-progress-bar"><div class="f-bearpaw-progress-fill" id="' + source.toLowerCase() + '-progress-bar" style="width: 0%;"></div></div>' +
			'<p id="' + source.toLowerCase() + '-progress-text" style="font-size: 12px; color: #646970; margin-top: 5px;">Preparing...</p>'
		);

		var startPage = 1;
		var totalProducts = 0;
		var totalPages = 0;

		function downloadChunk() {
			var $statusEl = $('#' + source.toLowerCase() + '-status');
			var $progressBar = $('#' + source.toLowerCase() + '-progress-bar');
			var $progressText = $('#' + source.toLowerCase() + '-progress-text');
			var $overallProgressBar = $('#overall-progress-bar');
			var $overallProgressText = $('#overall-progress-text');

			var nonce = downloadNonce || $('input[name="_wpnonce"]').first().val();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'f_bearpaw_download_chunk',
					nonce: nonce,
					source: source,
					start_page: startPage
				},
				dataType: 'json',
				success: function(response) {
					if (response.success && response.data) {
						var data = response.data;
						totalProducts = data.total_products || 0;
						totalPages = data.total_pages_downloaded || 0;

						// Update source-specific progress
						var estimatedTotalPages = data.has_more ? totalPages + 10 : totalPages;
						var sourceProgress = estimatedTotalPages > 0 ? Math.min(90, (totalPages / estimatedTotalPages) * 100) : 50;
						$progressBar.css('width', sourceProgress + '%');

						if (data.has_more) {
							$statusEl.text('Downloading... ' + totalPages + ' pages, ' + totalProducts.toLocaleString() + ' products');
							$progressText.text('Pages downloaded: ' + totalPages + ' | Products: ' + totalProducts.toLocaleString() + ' | Loading more...');
						} else {
							$statusEl.text('✅ Completed: ' + totalPages + ' pages, ' + totalProducts.toLocaleString() + ' products');
							$progressText.text('Pages downloaded: ' + totalPages + ' | Products: ' + totalProducts.toLocaleString() + ' | Finalizing...');
						}

						// Update overall progress
						var overallProgress = 0;
						if (source === 'B2B') {
							overallProgress = data.has_more ? Math.min(45, sourceProgress * 0.5) : 50;
						} else {
							overallProgress = data.has_more ? Math.min(95, 50 + (sourceProgress * 0.5)) : 100;
						}
						$overallProgressBar.css('width', overallProgress + '%');

						if (data.has_more) {
							startPage = data.current_page;
							setTimeout(downloadChunk, 500);
						} else {
							$progressBar.css('width', '100%');
							finalizeDownload(source, callback);
						}
					} else {
						// Error occurred
						var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
						$statusEl.text('❌ Error: ' + errorMsg);
						$progressText.text('Error occurred. Please try again.');
						$progressBar.css('background', '#d63638');
						$overallProgressText.text('❌ Download failed for ' + source);
						if (callback) callback();
					}
				},
				error: function(xhr, status, error) {
					var errorMsg = error;
					if (xhr.responseText) {
						try {
							var errorResponse = JSON.parse(xhr.responseText);
							if (errorResponse.data && errorResponse.data.message) {
								errorMsg = errorResponse.data.message;
							}
						} catch (e) {
							if (xhr.responseText.indexOf('<') !== -1) {
								errorMsg = 'Server returned HTML instead of JSON. This usually means a PHP error occurred. Please check server logs.';
							}
						}
					}

					$statusEl.text('❌ Error: ' + errorMsg);
					$progressText.text('AJAX error occurred: ' + status + ' - ' + errorMsg);
					$overallProgressText.text('❌ Download failed for ' + source + ': ' + errorMsg);
					if (callback) callback();
				}
			});
		}

		downloadChunk();
	}

	/**
	 * Finalize download for a source.
	 *
	 * @param {string} source Source name.
	 * @param {Function} callback Callback when done.
	 */
	function finalizeDownload(source, callback) {
		var nonce = downloadNonce || $('input[name="_wpnonce"]').first().val();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'f_bearpaw_finalize_download',
				nonce: nonce,
				source: source
			},
			dataType: 'json',
			success: function(response) {
				if (response.success && response.data) {
					var $progressText = $('#' + source.toLowerCase() + '-progress-text');
					if ($progressText.length) {
						$progressText.text('✅ Saved: ' + response.data.products_count.toLocaleString() + ' products to file');
					}
				}
				if (callback) callback();
			},
			error: function() {
				if (callback) callback();
			}
		});
	}

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		// Auto-open accordion based on URL parameter
		var urlParams = new URLSearchParams(window.location.search);
		var openStep = urlParams.get('open_step');
		if (openStep) {
			toggleAccordion(parseInt(openStep, 10));
		}

		// Bind search input
		$('#missing-products-search').on('keyup', filterMissingProducts);

		// Bind sort headers
		$('#missing-products-table th[onclick]').each(function() {
			$(this).css('cursor', 'pointer');
		});
	});

})(jQuery);