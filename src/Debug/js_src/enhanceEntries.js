import $ from "jquery";
import * as enhanceObject from "./enhanceObject.js";
import * as tableSort from "./tableSort.js";

var options;

export function init($root, opts) {
	options = opts;
	enhanceObject.init($root, options);
	$root.on("click", ".alert-dismissible .close", function() {
		$(this).parent().remove();
	});
	$root.on("click", ".show-more-container .show-more", function() {
		var $container = $(this).closest(".show-more-container");
		$container.find(".show-more-wrapper").animate({
			height: $container.find(".t_string").height()
		},400,"swing",function() {
			$(this).css("display", "inline");
		});
		$container.find(".show-more-fade").fadeOut();
		$container.find(".show-more").hide();
		$container.find(".show-less").show();
	});
	$root.on("click", ".show-more-container .show-less", function() {
		var $container = $(this).closest(".show-more-container");
		$container.find(".show-more-wrapper")
			.css("display", "block")
			.animate({
				height: "70px"
			});
		$container.find(".show-more-fade").fadeIn();
		$container.find(".show-more").show();
		$container.find(".show-less").hide();
	});
	$root.on("debug.expand.array", function(e){
		var $node = $(e.target);
		if ($node.is(".enhanced")) {
			return;
		}
		$node.find("> .array-inner > .key-value > :last-child").each(function() {
			enhanceValue(this);
		});
	});
	$root.on("debug.expand.group", function(e){
		enhance($(e.target));
	});
	$root.on("debug.expand.object", function(e){
		var $node = $(e.target);
		if ($node.is(".enhanced")) {
			return;
		}
		$node.find("> .constant > :last-child, > .property > :last-child").each(function() {
			enhanceValue(this);
		});
		enhanceObject.enhanceInner($node);
	});
	$root.on("debug.expanded.array, debug.expanded.group, debug.expanded.object", function(e){
		var $node = $(e.target);
		if ($node.is(".enhanced")) {
			return;
		}
		enhanceStrings($node);
		$node.addClass("enhanced");
	});
}

/**
 * add font-awsome icons
 */
function addIcons($root, types) {
	if (!$.isArray(types)) {
		types = typeof types === "undefined" ?
			["misc"] :
			[types];
	}
	if ($.inArray("misc", types) >= 0) {
		$.each(options.iconsMisc, function(selector,v){
			$root.find(selector).prepend(v);
		});
	}
	if ($.inArray("methods", types) >= 0) {
		$.each(options.iconsMethods, function(selector,v){
			var $caption;
			if ($root.is(selector)) {
				if ($root.is("table")) {
					$caption = $root.find("caption");
					if (!$caption.length) {
						$caption = $("<caption></caption>");
						$root.prepend($caption);
					}
					$root = $caption;
				}
				$root.prepend(v);
				return false;
			}
		});
	}
}

/**
 * Enhance log entries
 */
export function enhance($node) {
	$node.hide();
	$node.children().each(function() {
		enhanceEntry($(this));
	});
	$node.show();
	enhanceStrings($node);
}

/**
 * Adds expand/collapse functionality to array
 * does not enhance values
 */
function enhanceArray($node) {
	// console.log("enhanceArray", $node[0]);
	var isEnhanced = $node.prev().is(".t_array-expand"),
		$expander = $('<span class="t_array-expand" data-toggle="array">' +
				'<span class="t_keyword">array</span><span class="t_punct">(</span> ' +
				'<i class="fa ' + options.iconsExpand.expand + '"></i>&middot;&middot;&middot; ' +
				'<span class="t_punct">)</span>' +
			"</span>"),
		numParents = $node.parentsUntil(".m_group", ".t_object, .t_array").length;
	if (isEnhanced) {
		return;
	}
	if ($.trim($node.find(".array-inner").html()).length < 1) {
		// empty array -> don't add expand/collapse
		$node.find("br").hide();
		$node.find(".array-inner").hide();
		return;
	}
	// add collapse link
	$node.find(".t_keyword").first().
		wrap('<span class="t_array-collapse expanded" data-toggle="array">').
		after('<span class="t_punct">(</span> <i class="fa ' + options.iconsExpand.collapse + '"></i>').
		parent().next().remove();	// remove original "("
	$node.before($expander);
	if (numParents === 0) {
		// outermost array -> leave open
		$node.debugEnhance("expand");
	} else {
		$node.find(".t_array-collapse").first().debugEnhance("collapse");
	}
}

/**
 * Enhance a single log entry
 */
export function enhanceEntry($entry, inclStrings) {
	// console.log("enhanceEntry", $entry.attr("class"));
	if ($entry.is(".enhanced")) {
		return;
	}
	if ($entry.is(".m_group")) {
		// minimal enhancement... just adds data-toggle attr and hides target
		// target will not be enhanced until expanded
		enhanceGroup($entry.find("> .group-header"));
	/*
	} else if ($entry.is(".m_groupSummary")) {
		// groupSummary has no toggle.. and is uncollapsed -> enhance
		enhance($entry);
	*/
	} else {
		// regular log-type entry
		$entry.children().each(function() {
			enhanceValue(this);
		});
		addIcons($entry, ["methods", "misc"]);
	}
	if (inclStrings) {
		enhanceStrings($entry);
	}
	$entry.addClass("enhanced");
}

function enhanceGroup($toggle) {
	var $group = $toggle.parent(),
		$target = $toggle.next();
	addIcons($toggle, ["methods"]);
	$toggle.attr("data-toggle", "group");
	$.each(["level-error","level-info","level-warn"], function(i, val){
		var $icon;
		if ($toggle.hasClass(val)) {
			$icon = $toggle.children("i").eq(0);
			$toggle.wrapInner('<span class="'+val+'"></span>');
			$toggle.prepend($icon); // move icon
		}
	});
	$toggle.removeClass("collapsed level-error level-info level-warn"); // collapsed class is never used
	if ($.trim($target.html()).length < 1) {
		$group.addClass("empty");
	}
	if ($toggle.is(".expanded") || $target.find(".m_error, .m_warn").not(".filter-hidden").length) {
		$toggle.debugEnhance("expand");
	} else {
		$toggle.debugEnhance("collapse", true);
	}
}

function enhanceStrings($root) {
	$root.find(".t_string:not(.enhanced):visible").each(function() {
		var $this = $(this),
			$container,
			$stringWrap,
			height = $this.height(),
			diff = height - 70;
		if (diff > 35) {
			$stringWrap = $this.wrap('<div class="show-more-wrapper"></div>').parent();
			$stringWrap.append('<div class="show-more-fade"></div>');
			$container = $stringWrap.wrap('<div class="show-more-container"></div>').parent();
			$container.append('<button type="button" class="show-more"><i class="fa fa-caret-down"></i> More</button>');
			$container.append('<button type="button" class="show-less" style="display:none;"><i class="fa fa-caret-up"></i> Less</button>');
		}
		$this.addClass("enhanced");
	});
}

function enhanceValue(node) {
	var $node = $(node);
	if ($node.is(".t_array")) {
		enhanceArray($node);
	} else if ($node.is(".t_object")) {
		enhanceObject.enhance($node);
	} else if ($node.is("table")) {
		tableSort.makeSortable($node);
	} else if ($node.is(".timestamp")) {
		var $i = $node.find("i"),
			text = $node.text(),
			$span = $("<span>"+text+"</span>");
		if ($node.is(".t_string")) {
			$span.addClass("t_string numeric");
		} else if ($node.is(".t_int")) {
			$span.addClass("t_int");
		} else {
			$span.addClass("t_float");
		}
		if ($node.is(".no-pseudo")) {
			$span.addClass("no-pseudo");
		}
		$node.removeClass("t_float t_int t_string numeric no-pseudo");
		$node.html($i).append($span);
	}
}
