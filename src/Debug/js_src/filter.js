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
			$nested = $this.closest("label").next("ul").find("input"),
			$root = $this.closest(".debug");
		if ($this.data("toggle") == "error") {
			// filtered separately
			return;
		}
		$nested.prop("checked", isChecked);
		applyFilter($root);
		updateFilterStatus($root);
	});

	$delegateNode.on("change", "input[data-toggle=error]", function() {
		var $this = $(this),
			$root = $this.closest(".debug"),
			errorClass = $this.val(),
			isChecked = $this.is(":checked"),
			selector = ".group-body .error-" + errorClass;
		$root.find(selector).toggleClass("filter-hidden", !isChecked);
		// trigger collapse to potentially update group icon
		$root.find(".m_error, .m_warn").parents(".m_group").find(".group-body")
			.trigger("collapsed.debug.group");
		updateFilterStatus($root);
	});

	$delegateNode.on("channelAdded.debug", function(e) {
		var $root = $(e.target).closest(".debug");
		updateFilterStatus($root);
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
	// :not(.level-error, .level-info, .level-warn)
	$root.find("> .debug-body .m_alert, .group-body > *:not(.m_groupSummary)").each(function(){
		var $node = $(this),
			show = true,
			unhiding = false;
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
		unhiding = show && $node.is(".filter-hidden");
		$node.toggleClass("filter-hidden", !show);
		if (unhiding && $node.is(":visible")) {
			$node.debugEnhance();
		}
	});
	$root.find(".m_group.filter-hidden > .group-header:not(.expanded) + .group-body").debugEnhance();
}

function updateFilterStatus($debugRoot) {
	var haveUnchecked = $debugRoot.find(".debug-sidebar input:checkbox:not(:checked)").length > 0;
	$debugRoot.toggleClass("filter-active", haveUnchecked);
}
