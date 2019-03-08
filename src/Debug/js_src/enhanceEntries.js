import $ from "jquery";
import * as enhanceObject from "./enhanceObject.js";
import * as expandCollapse from "./expandCollapse.js";

var options;

export function init($root, opts) {
	options = opts;
	enhanceObject.init($root, options);
	$root.on("click", ".alert-dismissible .close", function() {
		$(this).parent().remove();
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
	if ($.inArray("object", types) >= 0) {
		$.each(options.iconsObject, function(selector,v){
			$root.find(selector).prepend(v);
		});
		$root.find("> .property > .fa:first-child, > .property > span:first-child > .fa").addClass("fa-fw");
	}
	if ($.inArray("methods", types) >= 0) {
		$.each(options.iconsMethods, function(selector,v){
			var $caption;
			if ($root.is(selector)) {
				if ($root.is("table")) {
					$caption = $root.find('caption');
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
 * Adds expand/collapse functionality to array
 */
function enhanceArray($node) {
	// console.log('enhanceArray', $node[0]);
	var isEnhanced = $node.prev().hasClass("t_array-expand"),
		$expander = $('<span class="t_array-expand" data-toggle="array">' +
				'<span class="t_keyword">array</span><span class="t_punct">(</span> ' +
				'<i class="fa ' + options.classes.expand + '"></i>&middot;&middot;&middot; ' +
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
		after('<span class="t_punct">(</span> <i class="fa ' + options.classes.collapse + '"></i>').
		parent().next().remove();	// remove original "("
	$node.before($expander);
	if (numParents === 0) {
		// outermost array -> leave open
		expand($node);
	} else {
		// $node.hide();
		collapse($node.find(".t_array-collapse").first());
	}
}

export function enhance($node, inclStrings) {
	// console.log("enhanceEntries.enhance", $node);
	if (typeof inclStrings == "undefined") {
		inclStrings = true;
	}
	$node.hide();
	// don't enhance groups... they'll get enhanced when expanded
	$node.children().not(".m_group").each(function() {
		enhanceEntry($(this), false);
	});
	$node.show();
	if (inclStrings) {
		enhanceStrings($node);
	}
}

export function enhanceEntry($entry, inclStrings) {
	// console.log("enhanceEntry", $entry);
	if ($entry.hasClass("enhanced")) {
		return;
	}
	if ($entry.hasClass("m_group")) {
		return;
	}
	if (typeof inclStrings == "undefined") {
		inclStrings = true;
	}
	if ($entry.hasClass("group-header")) {
		// minimal enhancement... just adds data-toggle attr and hides target
		// target will not be enhanced until expanded
		addIcons($entry, ["methods"]);
		enhanceGroupHeader($entry);
		$entry.addClass("enhanced");
	} else if ($entry.hasClass("m_groupSummary")) {
		// groupSummary has no toggle.. and is uncollapsed -> enhance
		enhance($entry);
	} else {
		// regular log-type entry
		$entry.children().each(function() {
			enhanceValue(this);
		});
		enhanceMisc($entry);
		addIcons($entry, ["misc","methods"]);
		$entry.addClass("enhanced");
		if (inclStrings) {
			enhanceStrings($entry);
		}
	}
}

function enhanceGroupHeader($toggle) {
	var $target = $toggle.next();
	// console.warn("enhanceGroupHeader", $toggle.text(), $toggle.attr("class"));
	$toggle.attr("data-toggle", "group");
	$.each(["level-error","level-info","level-warn"], function(i, val){
		var $i;
		if ($toggle.hasClass(val)) {
			$i = $toggle.children('i').eq(0);
			$toggle.wrapInner('<span class="'+val+'"></span>');
			$toggle.prepend($i); // move icon
		}
	});
	$toggle.removeClass("collapsed level-error level-info level-warn"); // collapsed class is never used
	if ($.trim($target.html()).length < 1) {
		// console.log("adding empty class");
		$toggle.addClass("empty");
		expandCollapse.iconChange($toggle, options.classes.empty);
		return;
	}
	if ($toggle.hasClass("expanded") || $target.find(".m_error, .m_warn").not(".hidden-error").length) {
		expandCollapse.expand($toggle);
	} else {
		expandCollapse.collapse($toggle, true);
	}
}

function enhanceMisc($root) {
	$root.find(".timestamp").each(function() {
		var $this = $(this),
			$i = $this.find("i"),
			text = $this.text(),
			$span = $("<span>"+text+"</span>");
		if ($this.hasClass("t_string")) {
			$span.addClass("t_string numeric");
		} else if ($this.hasClass("t_int")) {
			$span.addClass("t_int");
		} else {
			$span.addClass("t_float");
		}
		if ($this.hasClass("no-pseudo")) {
			$span.addClass("no-pseudo");
		}
		$this.removeClass("t_float t_int t_string numeric no-pseudo");
		$this.html($i).append($span);
	});
}

export function enhanceStrings($root) {
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
	if ($node.hasClass("t_array")) {
		enhanceArray($node);
	} else if ($node.hasClass("t_object")) {
		enhanceObject.enhance($node);
	} else if ($node.is("table")) {
		tableSort.makeSortable($node);
	}
}
