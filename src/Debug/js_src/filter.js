/**
 * Filter entries
 */

import $ from "jquery";

var channels = [];
var tests = [
	function ($node) {
		var channel = $node.data("channel");
		return channels.indexOf(channel) > -1;
	}
];
var preFilterCallbacks = [
	function ($root) {
		var $checkboxes = $root.find("input[data-toggle=channel]");
		channels = $checkboxes.length
			? []
			: [undefined];
		$checkboxes.filter(":checked").each(function(){
			channels.push($(this).val());
			if ($(this).data("isRoot")) {
				channels.push(undefined);
			}
		});
	}
];

export function init($delegateNode) {

	$delegateNode.on("change", "input[type=checkbox]", function() {
		var $this = $(this),
			isChecked = $this.is(":checked"),
			$nested = $this.closest("label").next("ul").find("input");
		if ($this.data("toggle") == "error") {
			// filtered separately
			return;
		}
		$nested.prop("checked", isChecked);
		applyFilter($this.closest(".debug"));
	});

	$delegateNode.on("change", "input[data-toggle=error]", function() {
		var $this = $(this),
			$root = $this.closest(".debug"),
			errorClass = $this.val(),
			isChecked = $this.is(":checked"),
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

export function addPreFilter(func) {
	preFilterCallbacks.push(func);
}

function applyFilter($root) {
	var i;
	for (i in preFilterCallbacks) {
		preFilterCallbacks[i]($root);
	}
	$root.find("> .debug-body .m_alert, .group-body > *").each(function(){
		var $node = $(this),
			show = true;
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
	/*
		Collapsed groups may get filter-hidden..
		this may result in exposing entries in that group that have yet to be enhanced
	*/
	$root.find(".m_group.filter-hidden > .group-header:not(.expanded) + .group-body > li:not(.filter-hidden):not(.enhanced)").each(function(){
		$(this).debugEnhance();
	});
}

