/**
 * handle expanding/collapsing arrays, groups, & objects
 */

import $ from "jquery";
import * as enhanceEntries from "./enhanceEntries.js";

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
		$groupEndValue;
	if ($toggle.is("[data-toggle=array]")) {
		// show and use the "expand it" toggle as reference toggle
		$toggle = $toggle.closest(".t_array").prev().show();
		$target = $toggle.next();
		$target.hide();
	} else {
		if ($toggle.is("[data-toggle=group]")) {
			$groupEndValue = $target.find("> .m_groupEndValue > :last-child");
			groupErrorIconChange($toggle);
			if ($groupEndValue.length && $toggle.find(".group-label").last().nextAll().length == 0) {
				$toggle.find(".group-label").last().after('<span class="t_operator"> : </span>' + $groupEndValue[0].outerHTML);
			}
		}
		$toggle.removeClass("expanded");
		if (immediate) {
			$target.hide();
			iconChange($toggle, options.classes.expand);
		} else {
			$target.slideUp("fast", function() {
				iconChange($toggle, options.classes.expand);
			});
		}
	}
}

export function expand($toggleOrTarget) {
	// console.warn('expand', $toggleOrTarget[0]);
	var isToggle = $toggleOrTarget.is("[data-toggle]"),
		$toggle = isToggle
			? $toggleOrTarget
			: $toggleOrTarget.prev(),
		$target = isToggle
			? $toggleOrTarget.next()
			: $toggleOrTarget,
		isEnhanced = $target.hasClass("enhanced");
	if (!isEnhanced) {
		if ($toggle.is("[data-toggle=group]")) {
			enhanceEntries.enhance($target);
		} else if ($toggle.is("[data-toggle=object]")) {
			onExpandObject($target);
		} else {
			onExpandArray($target);
		}
	}
	if ($toggle.is("[data-toggle=array]")) {
		// hide the toggle..  there is a different toggle in the expanded version
		$toggle.hide();
		$target.show();
		if (!isEnhanced) {
			enhanceEntries.enhanceStrings($target);
		}
	} else {
		$target.slideDown("fast", function() {
			var $groupEndValue = $target.find("> .m_groupEndValue");
			$toggle.addClass("expanded");
			iconChange($toggle, options.classes.collapse);
			if ($groupEndValue.length) {
				// remove value from label
				$toggle.find(".group-label").last().nextAll().remove();
			}
			if (!isEnhanced) {
				enhanceEntries.enhanceStrings($target);
			}
		});
	}
}

export function groupErrorIconChange($toggle) {
	var selector = ".fa-times-circle, .fa-warning",
		$target= $toggle.next(),
		icon = groupErrorIconGet($target),
		isExpanded = $toggle.hasClass(".expanded");
	$toggle.removeClass("empty");
	if (icon) {
		if ($toggle.find(selector).length) {
			$toggle.find(selector).replaceWith(icon);
		} else {
			$toggle.append(icon);
		}
		if (!isExpanded) {
			iconChange($toggle, options.classes.expand);
		}
	} else {
		$toggle.find(selector).remove();
		if ($target.children().not(".m_warn, .m_error").length < 1) {
			// group only contains errors & they're now hidden
			$toggle.addClass("empty");
			iconChange($toggle, options.classes.empty);
		}
	}
}

export function iconChange($toggle, classNameNew) {
	// console.log("toggleIconChange", $toggle.text(), classNameNew);
	var $icon = $toggle.children("i").eq(0);
	$icon.addClass(classNameNew);
	$.each(options.classes, function(i,className) {
		if (className !== classNameNew) {
			$icon.removeClass(className);
		}
	});
}

function groupErrorIconGet($container) {
	var icon = "";
	if ($container.find(".m_error").not(".hidden-error").length) {
		icon = options.iconsMethods[".m_error"];
	} else if ($container.find(".m_warn").not(".hidden-error").length) {
		icon = options.iconsMethods[".m_warn"];
	}
	return icon;
}

function onExpandArray($node) {
	if ($node.hasClass("enhanced")) {
		return;
	}
	$node.addClass("enhanced");
	$node.find("> .array-inner > .key-value > :last-child").each(function() {
		enhanceValue(this);
	});
}

function onExpandObject($node) {
	var $wrapper = $node.parent(),
		hasProtected = $node.children(".protected").not(".magic, .magic-read, .magic-write").length > 0,
		hasPrivate = $node.children(".private").not(".magic, .magic-read, .magic-write").length > 0,
		hasExcluded = $node.children(".excluded").hide().length > 0,
		accessible = $wrapper.data("accessible"),
		toggleClass = accessible === "public" ?
			"toggle-off" :
			"toggle-on",
		toggleVerb = accessible === "public" ?
			"show" :
			"hide",
		visToggles = "",
		hiddenInterfaces = [];
	if ($node.hasClass("enhanced")) {
		return;
	}
	$node.addClass("enhanced");
	$node.find("> .constant > :last-child, > .property > :last-child").each(function() {
		enhanceValue(this);
	});
	if ($node.find(".method[data-implements]").hide().length) {
		// linkify visibility
		$node.find(".method[data-implements]").each( function() {
			var iface = $(this).data("implements");
			if (hiddenInterfaces.indexOf(iface) < 0) {
				hiddenInterfaces.push(iface);
			}
		});
		$.each(hiddenInterfaces, function(i, iface) {
			$node.find(".interface").each(function() {
				var html = '<span class="toggle-off" data-toggle="interface" data-interface="'+iface+'" title="toggle methods">' +
						'<i class="fa fa-eye-slash"></i> ' + iface + "</span>";
				if ($(this).text() == iface) {
					$(this).html(html);
				}
			});
		});
	}
	$wrapper.find(".private, .protected").
		filter(".magic, .magic-read, .magic-write").
		removeClass("private protected");
	if (accessible === "public") {
		$wrapper.find(".private, .protected").hide();
	}
	if (hasProtected) {
		visToggles += ' <span class="'+toggleClass+'" data-toggle="vis" data-vis="protected">' + toggleVerb + " protected</span>";
	}
	if (hasPrivate) {
		visToggles += ' <span class="'+toggleClass+'" data-toggle="vis" data-vis="private">' + toggleVerb + " private</span>";
	}
	if (hasExcluded) {
		visToggles += ' <span class="'+toggleClass+'" data-toggle="vis" data-vis="excluded">' + toggleVerb + " excluded</span>";
	}
	$node.prepend('<span class="vis-toggles">' + visToggles + "</span>");
	addIcons($node, ["object"]);
	$node.find("> .property.forceShow").show().find("> .t_array-expand").each(function() {
		expand($(this));
	});
}

export function toggle(toggle) {
	var $toggle = $(toggle);
	if ($toggle.hasClass("empty")) {
		return;
	}
	if ($toggle.hasClass("expanded")) {
		$toggle.debugEnhance("collapse");
	} else {
		$toggle.debugEnhance("expand");
	}
}
