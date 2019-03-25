/**
 * handle expanding/collapsing arrays, groups, & objects
 */

import $ from "jquery";

var options;

export function init($root, opts) {
	options = opts;
	$root.on("click", "[data-toggle=array]", function() {
		toggle(this);
		return false;
	});
	$root.on("click", "[data-toggle=group]", function() {
		toggle(this);
		return false;
	});
	$root.on("click", "[data-toggle=object]", function() {
		toggle(this);
		return false;
	});
	$root.on("debug.collapsed.group", function(e){
		// console.warn('debug.collapsed.group');
		groupErrorIconUpdate($(e.target).prev());
	});
}

/**
 * Collapse an array, group, or object
 *
 * @param jQueryObj $toggle   the toggle node
 * @param immediate immediate no annimation
 *
 * @return void
 */
export function collapse($toggle, immediate) {
	var $target = $toggle.next(),
		$groupEndValue,
		what = "array",
		icon = options.iconsExpand.expand;
	if ($toggle.is("[data-toggle=array]")) {
		// show and use the "expand it" toggle as reference toggle
		$toggle = $toggle.closest(".t_array").prev().show();
		$target = $toggle.next();
		$target.hide();
	} else {
		if ($toggle.is("[data-toggle=group]")) {
			$groupEndValue = $target.find("> .m_groupEndValue > :last-child");
			if ($groupEndValue.length && $toggle.find(".group-label").last().nextAll().length == 0) {
				$toggle.find(".group-label").last().after('<span class="t_operator"> : </span>' + $groupEndValue[0].outerHTML);
			}
			what = "group";
		} else {
			what = "object";
		}
		$toggle.removeClass("expanded");
		if (immediate) {
			$target.hide();
			iconUpdate($toggle, icon);
		} else {
			$target.slideUp("fast", function() {
				iconUpdate($toggle, icon);
			});
		}
	}
	$target.trigger("debug.collapsed." + what);
}

export function expand($toggleOrTarget) {
	var isToggle = $toggleOrTarget.is("[data-toggle]"),
		$toggle = isToggle
			? $toggleOrTarget
			: $toggleOrTarget.prev(),
		$target = isToggle
			? $toggleOrTarget.next()
			: $toggleOrTarget,
		what = "array";
	if ($toggle.is("[data-toggle=group]")) {
		what = "group";
	} else if ($toggle.is("[data-toggle=object]")) {
		what = "object";
	}
	// trigger while still hidden!
	//    no redraws
	$target.trigger('debug.expand.' + what);
	if (what === "array") {
		// hide the toggle..  there is a different toggle in the expanded version
		$toggle.hide();
		$target.show();
		$target.trigger('debug.expanded.' + what);
	} else {
		$target.slideDown("fast", function() {
			var $groupEndValue = $target.find("> .m_groupEndValue");
			$toggle.addClass("expanded");
			iconUpdate($toggle, options.iconsExpand.collapse);
			if ($groupEndValue.length) {
				// remove value from label
				$toggle.find(".group-label").last().nextAll().remove();
			}
			$target.trigger('debug.expanded.' + what);
		});
	}
}

function groupErrorIconGet($container) {
	var icon = "";
	if ($container.find(".m_error").not(".filter-hidden").length) {
		icon = options.iconsMethods[".m_error"];
	} else if ($container.find(".m_warn").not(".filter-hidden").length) {
		icon = options.iconsMethods[".m_warn"];
	}
	return icon;
}

function groupErrorIconUpdate($toggle) {
	var selector = ".fa-times-circle, .fa-warning",
		$group = $toggle.parent(),
		$target = $toggle.next(),
		icon = groupErrorIconGet($target),
		isExpanded = $toggle.is(".expanded");
	$group.removeClass("empty");	// "empty" class just affects cursor
	if (icon) {
		if ($toggle.find(selector).length) {
			$toggle.find(selector).replaceWith(icon);
		} else {
			$toggle.append(icon);
		}
		iconUpdate($toggle, isExpanded
			? options.iconsExpand.collapse
			: options.iconsExpand.expand
		);
	} else {
		$toggle.find(selector).remove();
		if ($target.children().not(".m_warn, .m_error").length < 1) {
			// group only contains errors & they're now hidden
			$group.addClass("empty");
			iconUpdate($toggle, options.iconsExpand.empty);
		}
	}
}

function iconUpdate($toggle, classNameNew) {
	var $icon = $toggle.children("i").eq(0);
	if ($toggle.is(".group-header") && $toggle.parent().is(".empty")) {
		classNameNew = options.iconsExpand.empty;
	}
	$.each(options.iconsExpand, function(i, className) {
		$icon.toggleClass(className, className === classNameNew);
	});
}

export function toggle(toggle) {
	var $toggle = $(toggle);
	if ($toggle.is(".group-header") && $toggle.parent().is(".empty")) {
		return;
	}
	if ($toggle.is(".expanded")) {
		collapse($toggle);
	} else {
		expand($toggle);
	}
}
