import $ from "jquery";
import * as enhanceObject from "./enhanceObject.js";
import * as tableSort from "./tableSort.js";

var config,
	expandStack = [],
	strings = [];

export function init($root) {
	config = $root.data("config").get();
	enhanceObject.init($root);
	$root.on("click", ".close[data-dismiss=alert]", function() {
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
	$root.on("config.debug.updated", function(e, changedOpt){
		e.stopPropagation();
		if (changedOpt == "linkFilesTemplate") {
			updateFileLinks($root);
		}
	});
	$root.on("expand.debug.array", function(e){
		var $node = $(e.target),
			$entry = $node.closest("li[class*=m_]");
		e.stopPropagation();
		expandStack.push($node);
		$node.find("> .array-inner > .key-value > :last-child").each(function() {
			enhanceValue($entry, this);
		});
	});
	$root.on("expand.debug.group", function(e){
		var $node = $(e.target);
		e.stopPropagation();
		enhanceEntries($node);
	});
	$root.on("expand.debug.object", function(e){
		var $node = $(e.target),
			$entry = $node.closest("li[class*=m_]");
		e.stopPropagation();
		if ($node.is(".enhanced")) {
			return;
		}
		expandStack.push($node);
		$node.find("> .constant > :last-child,\
			> .property > :last-child,\
			> .method .t_string").each(function(){
				enhanceValue($entry, this)
			});
		enhanceObject.enhanceInner($node);
	});
	$root.on("expanded.debug.array expanded.debug.group expanded.debug.object", function(e){
		var i, count;
		expandStack.pop();
		if (expandStack.length == 0) {
			// now that everything relevant's been expanded we can enhanceLongString
			for (i = 0, count = strings.length; i < count; i++) {
				enhanceLongString(strings[i]);
			}
			strings = [];
		}
	});
}

/**
 * add font-awsome icons
 */
function addIcons($root) {
	var $caption, $icon, $node, selector;
	for (selector in config.iconsMisc) {
		$node = $root.find(selector);
		if ($node.length) {
			$icon = $(config.iconsMisc[selector]);
			if ($node.find("> i:first-child").hasClass($icon.attr("class"))) {
				// already have icon
				$icon = null;
				continue;
			}
			$node.prepend($icon);
			$icon = null;
		}
	};
	if ($root.data("icon")) {
		$icon = $("<i>").addClass($root.data("icon"));
	} else {
		for (selector in config.iconsMethods) {
			if ($root.is(selector)) {
				$icon = $(config.iconsMethods[selector]);
				break;
			}
		}
	}
	if ($icon) {
		if ($root.is(".m_group")) {
			// custom icon..   add to .group-label
			$root = $root.find("> .group-header .group-label").eq(0);
		} else if ($root.find("> table").length) {
			// table... we'll prepend icon to caption
			$caption = $root.find("> table > caption");
			if (!$caption.length) {
				$caption = $("<caption>");
				$root.find("> table").prepend($caption);
			}
			$root = $caption;
		}
		if ($root.find("> i:first-child").hasClass($icon.attr("class"))) {
			// already have icon
			return;
		}
		$root.prepend($icon);
	}
}

function buildFileLink(file, line) {
	var data = {
		file: file,
		line: line||1
	};
	return config.linkFilesTemplate.replace(
		/%(\w*)\b/g,
		function(m, key){
			return data.hasOwnProperty(key)
				? data[ key ]
				: "";
		}
	);
}

/**
 * Create text editor links for error, warn, & trace
 */
function createFileLinks($entry, $strings, remove) {
	var detectFiles = $entry.data("detectFiles") === true,
		dataFoundFiles = $entry.data("foundFiles") || [],
		isUpdate = false;
	if (!config.linkFiles && !remove) {
		return;
	}
	if (detectFiles === false) {
		return;
	}
	// console.info("createFileLinks", detectFiles, $entry);
	if ($entry.is(".m_trace")) {
		isUpdate = $entry.find(".file-link").length > 0;
		if (!isUpdate) {
			$entry.find("table thead tr > *:last-child").after("<th></th>");
		} else if (remove) {
			$entry.find("table tr > *:last-child").remove();
			return;
		}
		$entry.find("table tbody tr").each(function(){
			var $tr = $(this),
				$tds = $tr.find("> td"),
				$a = $('<a>', {
					class: "file-link",
					href: buildFileLink($tds.eq(0).text(), $tds.eq(1).text()),
					html: '<i class="fa fa-fw fa-external-link"></i>',
					style: "vertical-align: bottom",
					title: "Open in editor"
				});
			if (isUpdate) {
				$tr.find(".file-link").replaceWith($a);
			} else {
				$tds.last().after($("<td/>", {
					class: "text-center",
					html: $a
				}));
			}
		});
		return;
	}
	// don't remove data... link template may change
	// $entry.removeData("detectFiles foundFiles");
	if ($entry.is("[data-file]")) {
		/*
			Log entry link
		*/
		$entry.find("> .file-link").remove();
		if (!remove) {
			$entry.append($('<a>', {
				html: '<i class="fa fa-external-link"></i>',
				href: buildFileLink($entry.data("file"), $entry.data("line")),
				title: "Open in editor",
				class: "file-link lpad"
			})[0].outerHTML);
		}
		return;
	}
	if (!$strings) {
		$strings = [];
	}
	$.each($strings, function(){
		// console.log('string', $(this).text());
		var $replace,
			$string = $(this),
			attr = $string[0].attributes,
			text = $.trim($string.text()),
			matches = [];
		if ($string.closest(".m_trace").length) {
			createFileLinks($string.closest(".m_trace"));
			return false;
		}
		if ($string.data("file")) {
			// filepath specified in data attr
			matches = [null, $string.data("file"), $string.data("line") || 1];
		} else if (dataFoundFiles.indexOf(text) === 0 || $string.is(".file")) {
			matches = [null, text, 1];
		} else {
			matches = text.match(/^(\/.+\.php)(?: \(line (\d+)\))?$/) || [];
		}
		if (matches.length) {
			$replace = remove
				? $("<span>", {
						html: text,
					})
				: $('<a>', {
						html: text + ' <i class="fa fa-external-link"></i>',
						href: buildFileLink(matches[1], matches[2]),
						title: "Open in editor"
					});
			if ($string.is("td")) {
				$string.html(remove
					? text
					: $replace
				);
			} else {
				/*
					attr is not a plain object, but an array of attribute nodes
					which contain both the name and value
				*/
				$.each(attr, function(){
					var name = this.name;
					if (["html","href","title"].indexOf(name) > -1) {
						return;	// continue;
					}
					$replace.attr(name, this.value);
				});
				$string.replaceWith($replace);
			}
			if (!remove) {
				$replace.addClass("file-link");
			}
		}
	});
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
				'<i class="fa ' + config.iconsExpand.expand + '"></i>&middot;&middot;&middot; ' +
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
		after('<span class="t_punct">(</span> <i class="fa ' + config.iconsExpand.collapse + '"></i>').
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
 * Enhance log entries
 */
export function enhanceEntries($node) {
	// console.warn('enhanceEntries', $node[0]);
	var $prev = $node.prev(),
		show = !$prev.hasClass("group-header") || $prev.hasClass("expanded");
	expandStack.push($node);
	// temporarily hide when enhancing... minimize redraws
	$node.hide();
	$node.children().each(function() {
		enhanceEntry($(this));
	});
	if (show) {
		$node.show();
	}
	$node.addClass("enhanced");
	$node.trigger("expanded.debug.group");
}

/**
 * Enhance a single log entry
 * we don't enhance strings by default (add showmore).. needs to be visible to calc height
 */
export function enhanceEntry($entry) {
	if ($entry.is(".enhanced")) {
		return;
	}
	// console.log('enhanceEntry', $entry[0]);
	if ($entry.is(".m_group")) {
		enhanceGroup($entry);
	} else if ($entry.is(".m_trace")) {
		createFileLinks($entry);
		addIcons($entry);
	} else {
		// regular log-type entry
		if ($entry.data('file')) {
			if (!$entry.attr("title")) {
				$entry.attr("title", $entry.data("file") + ": line " + $entry.data("line"));
			}
			createFileLinks($entry);
		}
		$entry.children().each(function() {
			enhanceValue($entry, this);
		});
		addIcons($entry);
	}
	$entry.addClass("enhanced");
	$entry.trigger("enhanced.debug");
}

function enhanceGroup($group) {
	var $toggle = $group.find("> .group-header"),
		$target = $toggle.next();
	addIcons($group);
	addIcons($toggle);
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

function enhanceLongString($node) {
	var $container,
		$stringWrap,
		height = $node.height(),
		diff = height - 70;
	if (diff > 35) {
		$stringWrap = $node.wrap('<div class="show-more-wrapper"></div>').parent();
		$stringWrap.append('<div class="show-more-fade"></div>');
		$container = $stringWrap.wrap('<div class="show-more-container"></div>').parent();
		$container.append('<button type="button" class="show-more"><i class="fa fa-caret-down"></i> More</button>');
		$container.append('<button type="button" class="show-less" style="display:none;"><i class="fa fa-caret-up"></i> Less</button>');
	}
}

function enhanceValue($entry, node) {
	var $node = $(node);
	if ($node.is(".t_array")) {
		enhanceArray($node);
	} else if ($node.is(".t_object")) {
		enhanceObject.enhance($node);
	} else if ($node.is("table")) {
		tableSort.makeSortable($node);
	} else if ($node.is(".t_string")) {
		strings.push($node);
		createFileLinks($entry, $node);
	}
	if ($node.is(".timestamp")) {
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
		if ($node.is(".no-quotes")) {
			$span.addClass("no-quotes");
		}
		$node.removeClass("t_float t_int t_string numeric no-quotes");
		$node.html($i).append($span);
	}
}

/**
 * Linkify files if not already done or update already linked files
 */
function updateFileLinks($group) {
	var remove = !config.linkFiles || config.linkFilesTemplate.length == 0;
	$group.find("li[data-detect-files]").each(function(){
		createFileLinks($(this), $(this).find(".t_string"), remove);
	});
}
