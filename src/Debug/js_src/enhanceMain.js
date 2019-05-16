/**
 * Add primary Ui elements
 */

import $ from "jquery";
import * as drawer from "./drawer.js";
import * as filter from "./filter.js";
import * as optionsMenu from "./optionsDropdown.js";
import * as sidebar from "./sidebar.js";
import {cookieGet,cookieRemove,cookieSet} from "./http.js";

var config;
var $root;

export function init($debugRoot, conf) {
	config = conf.config;
	$root = $debugRoot;
	$root.find(".debug-menu-bar").append($('<div />', {class:"pull-right"}));
	addChannelToggles();
	addExpandAll();
	addNoti($("body"));
	// addPersistOption();
	enhanceErrorSummary();
	drawer.init($root, conf);
	filter.init($root);
	sidebar.init($root, conf);
	optionsMenu.init($root, conf);
	addErrorIcons();
	$root.find(".loading").hide();
	$root.addClass("enhanced");
}

function addChannelToggles() {
	var channels = $root.data("channels"),
		$toggles,
		$ul = buildChannelList(channels, $root.data("channelRoot"));
	$toggles = $("<fieldset />", {
			"class": "channels",
		})
		.append('<legend>Channels</legend>')
		.append($ul);
	if ($ul.html().length) {
		$root.find(".debug-body").prepend($toggles);
	}
}

function addErrorIcons() {
	var counts = {
		error : $root.find(".m_error[data-channel=phpError]").length,
		warn : $root.find(".m_warn[data-channel=phpError]").length
	}
	var $icon;
	var $icons = $("<span>", {class: "debug-error-counts"});
	// var $badge = $("<span>", {class: "badge"});
	if (counts.error) {
		$icon = $(config.iconsMethods[".m_error"]).removeClass("fa-lg").addClass("text-error");
		// $root.find(".debug-pull-tab").append($icon);
		$icons.append($icon).append($("<span>", {
			class: "badge",
			html: counts.error
		}));
	}
	if (counts.warn) {
		$icon = $(config.iconsMethods[".m_warn"]).removeClass("fa-lg").addClass("text-warn");
		// $root.find(".debug-pull-tab").append($icon);
		$icons.append($icon).append($("<span>", {
			class: "badge",
			html: counts.warn
		}));
	}
	$root.find(".debug-pull-tab").append($icons[0].outerHTML);
	$root.find(".debug-menu-bar .pull-right").prepend($icons);
}

function addExpandAll() {
	var $expandAll = $("<button>", {
			"class": "expand-all",
		}).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups');
	// this is currently invoked before entries are enhance / empty class not yet added
	if ($root.find(".m_group:not(.empty)").length > 1) {
		$root.find(".debug-log-summary").before($expandAll);
	}
	$root.on("click", ".expand-all", function(){
		$(this).closest(".debug").find(".group-header").not(".expanded").each(function() {
			$(this).debugEnhance('expand');
		});
		return false;
	});
}

function addNoti($root) {
	$root.append('<div class="debug-noti-wrap">' +
			'<div class="debug-noti-table">' +
				'<div class="debug-noti"></div>' +
			'</div>' +
		'</div>');
}

/*
function addPersistOption() {
	var $node;
	if (config.debugKey) {
		$node = $('<label class="debug-cookie" title="Add/remove debug cookie"><input type="checkbox"> Keep debug on</label>');
		if (cookieGet("debug") === options.debugKey) {
			$node.find("input").prop("checked", true);
		}
		$("input", $node).on("change", function() {
			var checked = $(this).is(":checked");
			if (checked) {
				cookieSet("debug", options.debugKey, 7);
			} else {
				cookieRemove("debug");
			}
		});
		$root.find(".debug-menu-bar").eq(0).prepend($node);
	}
}
*/

export function buildChannelList(channels, channelRoot, checkedChannels, prepend) {
	var $ul = $('<ul class="list-unstyled">'),
		$li,
		channel,
		$label;
	// checkedChannels = checkedChannels || [];
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
		$label = $('<label>', {
			"class": "toggle active",
		}).append($("<input>", {
			checked: checkedChannels
				? checkedChannels.indexOf(prepend + channel) > -1
				: true,
			"data-is-root": channel == channelRoot,
			"data-toggle": "channel",
			type: "checkbox",
			value: prepend + channel
		})).append(" " + channel);
		$li.append($label);
		if (Object.keys(channels[channel]).length) {
			$li.append(buildChannelList(channels[channel], channelRoot, checkedChannels, prepend + channel + "."));
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
	$errorSummary.find("h3:first-child").prepend(config.iconsMethods[".m_error"]);
	$errorSummary.find("li[class*=error-]").each(function() {
		var category = $(this).attr("class").replace("error-", ""),
			html = $(this).html(),
			htmlReplace = '<li><label>' +
				'<input type="checkbox" checked data-toggle="error" data-count="'+$(this).data("count")+'" value="' + category + '" /> ' +
				html +
				'</label></li>';
		$(this).replaceWith(htmlReplace);
	});
	$errorSummary.find(".m_trace").debugEnhance();
}
