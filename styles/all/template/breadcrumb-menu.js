/**
* Breadcrumb Menu event handler
*/
function toggleBCDropdown(trigger, show)
{
	if(!trigger)
	{
		// Hide all dropdown menus, because there is no trigger (meaning a time-out)
		$('.breadcrumbs .visible').find('a.dropdown-trigger').each(function(){ toggleBCDropdown($(this), false); });
		return;
	}

	var $trigger = trigger,
		options = $trigger.data('dropdown-options'),
		parent = options.parent,
		visible = parent.hasClass(options.visibleClass),
		crumb = options.crumb;
		$container = $('#breadcrumb-menu'),
		pointer = '<div class="pointer"><div class="pointer-inner"></div></div>';

	if(show && !visible)
	{
		// Hide all other dropdown menus
		$('.breadcrumbs .visible').find('a.dropdown-trigger').each(function(){ toggleBCDropdown($(this), false); });

		// A new crumb has been triggered, so make a new drop-down
		$container.append('<div id="crumb-menu-' + crumb + '" class="dropdown hidden"></div>');
		var $menu = $('#breadcrumb-menu #crumb-menu-' + crumb);
		var $source = $('#breadcrumb-menu #crumb-' + crumb);
		var dropdown_contents = '<ul class="dropdown-contents">' + $source.html() + '</ul>';
		$menu.html(pointer + dropdown_contents);


		// Figure out direction of dropdown
		var windowWidth = $(window).width();
		var windowHeight = $(window).height();

		var direction = options.direction,
			verticalDirection = options.verticalDirection,
			t_offset = $trigger.offset();

		if (direction == 'auto')
		{
			if ((windowWidth - $trigger.outerWidth(true)) / 2 > t_offset.left) {
				direction = 'right';
			} else {
				direction = 'left';
			}
		}
		$menu.toggleClass(options.leftClass, direction == 'left').toggleClass(options.rightClass, direction == 'right');

		if (verticalDirection == 'auto')
		{
			if ((t_offset.top - $(window).scrollTop()) < windowHeight * 0.7) {
				verticalDirection = 'down';
			} else {
				verticalDirection = 'up';
			}
		}
		$menu.toggleClass(options.upClass, verticalDirection == 'up').toggleClass(options.downClass, verticalDirection == 'down');


		// Show the menu
		parent.toggleClass(options.visibleClass, true);
		$menu.show(300);
		$menu.toggleClass('dropdown-visible', true);


		// Position the menu
		if (verticalDirection == 'up')
		{
			$menu.css({
				marginLeft: 0,
				left: t_offset.left + 'px',
				top: t_offset.top - $menu.outerHeight() + 'px',
			});
		} else {
			$menu.css({
				marginLeft: 0,
				left: t_offset.left + 'px',
				top: t_offset.top + trigger.height() + 'px',
			});
		}

		var m_offset = $menu.offset().left,
			width = $menu.outerWidth(true); // probably won't work during animation

		if (m_offset < 2) {
			$menu.css('left', (2 - m_offset) + 'px');
		}
		else if ((m_offset + width + 2) > windowWidth) {
			$menu.css('margin-left', (windowWidth - m_offset - width - 2) + 'px');
		}

		//$menu.css('max-height', (windowHeight - t_offset.top - trigger.height()) + 'px');

		// TODO: check if this actually works
		var freeSpace = parent.offset().left - 4;

		if (direction == 'left') {
			$menu.css('margin-left', '-' + freeSpace + 'px');
		} else {
			$menu.css('margin-right', '-' + (windowWidth + freeSpace) + 'px');
		}


		// Controls for the sub-menus
		var bcmSubTimer;
		var $lastHover = new Object();

		$menu.find('li.children').each(function()
		{
			$(this).on({
				mouseenter: function(e) {
					if($(this).index($lastHover) >= 0 || $(this).find($lastHover).length) {
						// try to determine if a user has accidentally just moved outside the menu
					} else {
						// the mouse element is different from last time, so we close all the old ones before opening a new one
						$(this).parent().find('li.visible').toggleClass("visible", false).children('.dropdown-contents').stop(true).hide(200);
					}
					
					// determine if the subforum should go up or down
					if(($(this).offset().top - $(window).scrollTop()) < windowHeight * 0.6) {
						$(this).toggleClass("visible", true).children('.dropdown-contents').delay(200).show(200);
					} else {
						$(this).toggleClass("visible", true).children('.dropdown-contents').css({top: "auto", bottom: 0}).delay(200).show(200);
					}

					$lastHover = $(this);
					clearTimeout(bcmSubTimer);
				},
				mouseleave: function(e) {
					$this = $(this);
					bcmSubTimer = setTimeout(function() {
						clearTimeout(bcmSubTimer);
						$this.parent().find('li.visible').toggleClass("visible", false).children('.dropdown-contents').stop(true).hide(200);
					}, 500);
				}
			});
		});

	} else if(show && visible) {
		// Keep it open, do nothing
	} else {
		// Hide & destroy a menu
		var $menu = $('#breadcrumb-menu #crumb-menu-' + crumb);

		parent.toggleClass(options.visibleClass, false);
		$menu.toggleClass('dropdown-visible', false);
		$menu.stop(true);
		$menu.hide(300, function(){
			$menu.remove();
		});
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

$(document).ready(function($)
{
	var bcmTimer;

	$('.breadcrumbs').children('.crumb').each(function()
	{
		var $this = $(this);
		var trigger = $this.find('a');
		
		// if a crumb doesn't have a link, do nothing
		if(!$(trigger).length) { return; }

		// remove those annoying title tooltips
		$(trigger).removeAttr('title');
		
		// find the useful params of the link href
		var href = trigger.attr('href');
		var matches = href.match(/.*(index).*|.*[?&]t=([^&]+).*|.*[?&]f=([^&]+).*/);
		forum_id = matches ? (matches[1] ? matches[1] : matches[3]) : false;
		topic_id = matches ? matches[2] : false;

		// find the corresponding branch
		var dropdown;
		if(isNaN(forum_id) && forum_id == 'index') {
			crumb = forum_id;
		} else if (forum_id && !isNaN(forum_id) && !topic_id) {
			crumb = forum_id;
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
				crumb: forum_id,
			};

		// assign data to the trigger element
		trigger.addClass('dropdown-trigger');
		trigger.data('dropdown-options', ops);

		$(trigger).on("mouseenter",function()
		{
			clearTimeout(bcmTimer);
			toggleBCDropdown(trigger, true);
			$(phpbb.dropdownHandles).each(phpbb.toggleDropdown);
		})
	});

	// assign listeners to determine if the user has moved away
	$('.breadcrumbs, #breadcrumb-menu').on("mouseenter", function() {
		clearTimeout(bcmTimer);
	});	
	$('.breadcrumbs, #breadcrumb-menu').on("mouseleave", function() {
		bcmTimer = setTimeout(function() {
			clearTimeout(bcmTimer);
			toggleBCDropdown(false, false);
		}, 700);
	});

});