/**
* Dropdown toggle event handler
* This handler is used by phpBB.registerDropdown() and other functions
*/
function toggleBCDropdown(trigger, force) {

	if(!trigger) {
		// Hide all dropdown menus, because there is no trigger (meaning a time-out)
		$('#page-header .breadcrumbs .visible, #body-header .breadcrumbs .visible').find('a.dropdown-trigger').each(function(){ toggleBCDropdown($(this)); });
		return;
	}
	var $this = trigger,
		options = $this.data('dropdown-options'),
		parent = options.parent,
		dropdown = options.dropdown,
		visible = parent.hasClass(options.visibleClass);

	if (!visible) {
		// Hide other dropdown menus
		$('#page-header .breadcrumbs .visible, #body-header .breadcrumbs .visible').find('a.dropdown-trigger').each(function(){ toggleBCDropdown($(this)); });

		// Figure out direction of dropdown
		var direction = options.direction,
			verticalDirection = options.verticalDirection,
			offset = $this.offset();

		if (direction == 'auto') {
			if (($(window).width() - $this.outerWidth(true)) / 2 > offset.left) {
				direction = 'right';
			}
			else {
				direction = 'left';
			}
		}
		dropdown.toggleClass(options.leftClass, direction == 'left').toggleClass(options.rightClass, direction == 'right');

		if (verticalDirection == 'auto') {
			var height = $(window).height(),
				top = offset.top - $(window).scrollTop();

			if (top < height * 0.7) {
				verticalDirection = 'down';
			}
			else {
				verticalDirection = 'up';
			}
		}
		dropdown.toggleClass(options.upClass, verticalDirection == 'up').toggleClass(options.downClass, verticalDirection == 'down');
	}

	if(force && visible) {
		// keep it open
	} else {
		options.dropdown.toggle(300);
		parent.toggleClass(options.visibleClass, !visible);
		options.dropdown.toggleClass('dropdown-visible', !visible);
	}

	// Check dimensions when showing dropdown
	// !visible because variable shows state of dropdown before it was toggled
	if (!visible) {
		var windowWidth = $(window).width();
		var windowHeight = $(window).height();

		options.dropdown.each(function() {
			var $this = $(this);

			$this.css({
				marginLeft: 0,
				left: 0,
				//maxWidth: (windowWidth - 4) + 'px'
			});

			var offset = $this.offset().left,
				width = $this.outerWidth(true);

			if (offset < 2) {
				$this.css('left', (2 - offset) + 'px');
			}
			else if ((offset + width + 2) > windowWidth) {
				$this.css('margin-left', (windowWidth - offset - width - 2) + 'px');
			}

			$this.css({
				left: (trigger.offset().left) - 10 + 'px',
				top: (trigger.offset().top + trigger.height()) - 4 + 'px',
				//maxWidth: (windowWidth - 4) + 'px'
			});

		});

		options.dropdown.find('.dropdown-contents').each(function() {
			var $this = $(this);
			//$this.css('max-height', (windowHeight - trigger.offset().top - trigger.height()) + 'px');
		});

		var freeSpace = parent.offset().left - 4;

		if (direction == 'left') {
			options.dropdown.css('margin-left', '-' + freeSpace + 'px');
		} else {
			options.dropdown.css('margin-right', '-' + (windowWidth + freeSpace) + 'px');
		}
	}

	// Prevent event propagation
	if (arguments.length > 0) {
		try {
			var e = arguments[0];
			e.preventDefault();
			e.stopPropagation();
		}
		catch (error) { }
	}
	return false;
};


/**
* Toggle dropdown submenu
*/
zzztoggleSubmenu = function(e) {
	$(this).siblings('.dropdown-submenu').toggle();
	e.preventDefault();
}


$(document).ready(function($){
	var bcmTimer;

	$('#page-header .breadcrumbs, #body-header .breadcrumbs').children('.crumb').each(function() {
		var $this = $(this);
		var trigger = $this.find('a');
		if(!$(trigger).length) { return; }

		var href = trigger.attr('href');
		matches = href.match(/.*(index).*|.*[?&]t=([^&]+).*|.*[?&]f=([^&]+).*/);
		forum = matches[1] ? matches[1] : matches[3];
		topic = matches[2];

		var dropdown;
		if(isNaN(forum) && forum == 'index') {
			dropdown = $('#breadcrumb-menu').find('#branch-index');
		} else if (!isNaN(forum) && !topic) {
			dropdown = $('#breadcrumb-menu').find('#branch-'+forum);
		} else {
			return;
		}

		var ops = {
				parent: trigger.parent(), // Parent item to add classes to
				direction: 'auto', // Direction of dropdown menu. Possible values: auto, left, right
				verticalDirection: 'auto', // Vertical direction. Possible values: auto, up, down
				visibleClass: 'visible', // Class to add to parent item when dropdown is visible
				leftClass: 'dropdown-left', // Class to add to parent item when dropdown opens to left side
				rightClass: 'dropdown-right', // Class to add to parent item when dropdown opens to right side
				upClass: 'dropdown-up', // Class to add to parent item when dropdown opens above menu item
				downClass: 'dropdown-down', // Class to add to parent item when dropdown opens below menu item
				dropdown: dropdown,
				crumb: forum,
			};

		//ops.parent.addClass('dropdown-container');
		trigger.addClass('dropdown-trigger');
		trigger.data('dropdown-options', ops);
	
		//$('.dropdown-toggle-submenu', ops.parent).click(phpbb.toggleSubmenu);

		$(trigger).on("mouseenter",function() {
			clearTimeout(bcmTimer);
			toggleBCDropdown(trigger, true);
			$(phpbb.dropdownHandles).each(phpbb.toggleDropdown);
		})
	});


	$('.breadcrumbs, #breadcrumb-menu').on("mouseenter", function() {
		clearTimeout(bcmTimer);
	});	
	$('.breadcrumbs, #breadcrumb-menu').on("mouseleave", function() {
		bcmTimer = setTimeout(function() {
			clearTimeout(bcmTimer);
			toggleBCDropdown(false);
		}, 700);
	});

});