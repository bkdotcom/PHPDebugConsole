/**
 * Filter entries
 */

import $ from "jquery";

var $root;
var channels = [];
var tests = [
	function ($node) {
		var channel = $node.data("channel");
		return channels.indexOf(channel) >= 0;
	}
];

export function init($debugRoot) {
	$root = $debugRoot;

	$root.on("change", "input[type=checkbox]", function() {
		var isChecked = $(this).is(":checked"),
			$nested = $(this).closest("label").next("ul").find("> li > .toggle > input");
		if ($(this).data("toggle") == "error") {
			// filtered separately
			return;
		}

		$nested.prop("checked", isChecked);
		channels = [];
		$root.find("input[data-toggle=channel]:checked").each(function(){
			channels.push($(this).val());
			if ($(this).data("isRoot")) {
				channels.push(undefined);
			}
		});

		applyFilter();
	});

	$root.on("change", "input[data-toggle=error]", function() {
		var errorClass = $(this).val(),
			isChecked = $(this).is(":checked"),
			selector = ".group-body ." + errorClass;
		$root.find(selector).toggleClass("filter-hidden", !isChecked);
		// trigger collapse to potentially update group icon
		$root.find(".m_error, .m_warn").parents(".m_group").find(".group-body")
			.trigger("debug.collapsed.group");
	});
}

export function addTest(func) {
	tests.push(func);
}

function applyFilter() {
	// console.warn('applyFilters');
	$root.find("> .debug-body .m_alert, .group-body > *").each(function(){
		var $node = $(this),
			show = true,
			i = 0;
		if ($node.data("channel") == "phpError") {
			// php Errors are filtered separately
			return;
		}
		for (i in tests) {
			show = tests[i]($node);
			if (!show) {
				break;
			}
		}
		$node.toggleClass("filter-hidden", !show);
	});
}
