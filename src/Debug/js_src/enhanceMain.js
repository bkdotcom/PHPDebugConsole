/**
 * Add primary Ui elements
 */

import $ from "jquery";
import * as drawer from "./drawer.js";
import * as filter from "./filter.js";
import * as optionsMenu from "./optionsDropdown.js";
import * as sidebar from "./sidebar.js";
import * as http from "./http.js";

var options;
var $root;

export function init($debugRoot, opts) {
	options = opts;
	$root = $debugRoot;
	addChannelToggles();
	addExpandAll();
	addNoti($("body"));
	addPersistOption();
	enhanceErrorSummary();
	drawer.init($root, opts);
	filter.init($root);
	sidebar.init($root, opts);
	optionsMenu.init($root, opts);
	$root.find(".loading").hide();
	$root.addClass("enhanced");
}

function addChannelToggles() {
	var channels = $root.data("channels"),
		$toggles,
		$ul = buildChannelList(channels, "", $root.data("channelRoot"));
	$toggles = $("<fieldset />", {
			class: "channels",
		})
		.append('<legend>Channels</legend>')
		.append($ul);
	if ($ul.html().length) {
		$root.find(".debug-body").prepend($toggles);
	}
}

function addExpandAll() {
	var $expandAll = $("<button>", {
		class: "expand-all"
		}).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups');
	// this is currently invoked before entries are enhance / empty class not yet added
	if ($root.find(".m_group:not(.empty)").length) {
		$expandAll.on("click", function() {
			$(this).closest(".debug").find(".group-header").not(".expanded").each(function() {
				$(this).debugEnhance('expand');
			});
			return false;
		});
		$root.find(".debug-log-summary").before($expandAll);
	}
}

function addNoti($root) {
	$root.append('<div class="debug-noti-wrap">' +
			'<div class="debug-noti-table">' +
				'<div class="debug-noti"></div>' +
			'</div>' +
		'</div>');
}

function addPersistOption() {
	var $node;
	if (options.debugKey) {
		$node = $('<label class="debug-cookie" title="Add/remove debug cookie"><input type="checkbox"> Keep debug on</label>');
		if (http.cookieGet("debug") === options.debugKey) {
			$node.find("input").prop("checked", true);
		}
		$("input", $node).on("change", function() {
			var checked = $(this).is(":checked");
			if (checked) {
				http.cookieSave("debug", options.debugKey, 7);
			} else {
				http.cookieRemove("debug");
			}
		});
		$root.find(".debug-menu-bar").eq(0).prepend($node);
	}
}

export function buildChannelList(channels, prepend, channelRoot) {
	var $ul = $('<ul class="list-unstyled">'),
		$div,
		$li,
		channel,
		$label;
	prepend = prepend || "";
	if ($.isArray(channels)) {
		channels = channelsToTree(channels);
	}
	for (channel in channels) {
		if (channel === "phpError") {
			// phpError is a special channel
			continue;
		}
		$li = $("<li>");
		$label = $('<label>').append($("<input>", {
			checked: true,
			"data-is-root": channel == channelRoot,
			"data-toggle": "channel",
			type: "checkbox",
			value: prepend + channel
		})).append(" " + channel);
		$li.append($label);
		if (Object.keys(channels[channel]).length) {
			$li.append(buildChannelList(channels[channel], prepend + channel + "."));
		}
		$ul.append($li);
	}
	return $ul;
}

function channelsToTree(channels) {
	var channelTree = {},
		ref,
		i, i2,
		path;
	for (i = 0; i < channels.length; i++) {
		ref = channelTree;
		path = channels[i].split('.');
		for (i2 = 0; i2 < path.length; i2++) {
			if (!ref[ path[i2] ]) {
				ref[ path[i2] ] = {};
			}
			ref = ref[ path[i2] ];
		}
	}
	return channelTree;
}

function enhanceErrorSummary() {
	var $errorSummary = $root.find(".m_alert.error-summary");
	$errorSummary.find("h3:first-child").prepend(options.iconsMethods[".m_error"]);
	$errorSummary.find("li[class*=error-]").each(function() {
		var classAttr = $(this).attr("class"),
			html = $(this).html(),
			htmlReplace = '<li><label>' +
				'<input type="checkbox" checked data-toggle="error" data-count="'+$(this).data("count")+'" value="' + classAttr + '" /> ' +
				html +
				'</label></li>';
		$(this).replaceWith(htmlReplace);
	});
}
