import $ from "jquery";
import {addTest as addFilterTest, addPreFilter} from "./filter.js";

var config;
var methods;	// method filters
var $root;
var initialized = false;

export function init($debugRoot) {
	config = $debugRoot.data("config");
	$root = $debugRoot;

	// console.warn('sidebar.init');

	if (config.get("sidebar")) {
		addMarkup($root);
	}

	if (config.get("persistDrawer") && !config.get("openSidebar")) {
		close($root);
	}

	$root.on("click", ".close[data-dismiss=alert]", function() {
		// setTimeout -> new thread -> executed after event bubbled
		var $debug = $(this).closest(".debug");
		setTimeout(function(){
			if ($debug.find(".m_alert").length) {
				$debug.find(".debug-sidebar input[data-toggle=method][value=alert]").parent().addClass("disabled");
			}
		});
	});

	$root.on("click", ".sidebar-toggle", function() {
		var $debug = $(this).closest(".debug"),
			isVis = $debug.find(".debug-sidebar").is(".show");
		if (!isVis) {
			open($debug);
		} else {
			close($debug);
		}
	});

	$root.on("change", ".debug-sidebar input[type=checkbox]", function(e) {
		var $input = $(this),
			$toggle = $input.closest(".toggle"),
			$nested = $toggle.next("ul").find(".toggle"),
			isActive = $input.is(":checked"),
			$errorSummary = $(".m_alert.error-summary.have-fatal");
		$toggle.toggleClass("active", isActive);
		$nested.toggleClass("active", isActive);
		if ($input.val() == "fatal") {
			$errorSummary.find(".error-fatal").toggleClass("filter-hidden", !isActive);
			$errorSummary.toggleClass("filter-hidden", $errorSummary.children().not(".filter-hidden").length == 0);
		}
	});

	if (initialized) {
		return;
	}

	addPreFilter(function($delegateRoot){
		$root = $delegateRoot;
		config = $root.data("config") || $("body").data("config");
		methods = [];
		$root.find("input[data-toggle=method]:checked").each(function(){
			methods.push($(this).val());
		});
	});

	addFilterTest(function($node){
		var method = $node[0].className.match(/\bm_(\S+)\b/)[1];
		if (!config.get("sidebar")) {
			return true;
		}
		if (method == "group" && $node.find("> .group-body")[0].className.match(/level-(error|info|warn)/)) {
			method = $node.find("> .group-body")[0].className.match(/level-(error|info|warn)/)[1];
			$node.toggleClass("filter-hidden-body", methods.indexOf(method) < 0);
		}
		if (["alert","error","warn","info"].indexOf(method) > -1) {
			return methods.indexOf(method) > -1;
		} else {
			return methods.indexOf("other") > -1;
		}
	});

	initialized = true;
}

export function addMarkup($node) {
	var $sidebar = $('<div class="debug-sidebar show no-transition"></div>');
	var $expAll = $node.find(".debug-body > .expand-all");
	$sidebar.html('\
		<div class="sidebar-toggle">\
			<div class="collapse">\
				<i class="fa fa-caret-left"></i>\
				<i class="fa fa-ellipsis-v"></i>\
				<i class="fa fa-caret-left"></i>\
			</div>\
			<div class="expand">\
				<i class="fa fa-caret-right"></i>\
				<i class="fa fa-ellipsis-v"></i>\
				<i class="fa fa-caret-right"></i>\
			</div>\
		</div>\
		<div class="sidebar-content">\
			<ul class="list-unstyled debug-filters">\
				<li class="php-errors">\
					<span><i class="fa fa-fw fa-lg fa-code"></i>PHP Errors</span>\
					<ul class="list-unstyled">\
					</ul>\
				</li>\
				<li class="channels">\
					<span><i class="fa fa-fw fa-lg fa-list-ul"></i>Channels</span>\
					<ul class="list-unstyled">\
					</ul>\
				</li>\
			</ul>\
			<button class="expand-all" style="display:none;"><i class="fa fa-lg fa-plus"></i> Exp All Groups</button>\
		</div>\
	');
	$node.find(".debug-body").before($sidebar);

	phpErrorToggles($node);
	moveChannelToggles($node);
	addMethodToggles($node);
	if ($expAll.length) {
		$expAll.remove();
		$sidebar.find(".expand-all").show();
	}
	setTimeout(function(){
		$sidebar.removeClass("no-transition");
	}, 500);
}

export function close($node) {
	$node.find(".debug-sidebar")
		.removeClass("show")
		.attr("style", "")
		.trigger("close.debug.sidebar");
	config.set("openSidebar", false);
}

export function open($node) {
	$node.find(".debug-sidebar")
		.addClass("show")
		.trigger("open.debug.sidebar");
	config.set("openSidebar", true);
}

function addMethodToggles($node) {
	var $filters = $node.find(".debug-filters"),
		$entries = $node.find("> .debug-body .m_alert, .group-body > *"),
		val,
		labels = {
			alert: '<i class="fa fa-fw fa-lg fa-bullhorn"></i>Alerts',
			error: '<i class="fa fa-fw fa-lg fa-times-circle"></i>Error',
			warn: '<i class="fa fa-fw fa-lg fa-warning"></i>Warning',
			info: '<i class="fa fa-fw fa-lg fa-info-circle"></i>Info',
			other: '<i class="fa fa-fw fa-lg fa-sticky-note-o"></i>Other'
		},
		haveEntry;
	for (val in labels) {
		haveEntry = val == "other"
			? $entries.not(".m_alert, .m_error, .m_warn, .m_info").length > 0
			: $entries.filter(".m_"+val).not("[data-channel=phpError]").length > 0;
		$filters.append(
			$('<li />').append(
				$('<label class="toggle active" />').toggleClass("disabled", !haveEntry).append(
					$("<input />", {
						type: "checkbox",
						checked: true,
						"data-toggle": "method",
						value: val
					})
				).append(
					$("<span>").append(
						labels[val]
					)
				)
			)
		);
	}
}

/**
 * grab the .debug-body toggles and move them to sidebar
 */
function moveChannelToggles($node) {
	var $togglesSrc = $node.find(".debug-body .channels > ul > li"),
		$togglesDest = $node.find(".debug-sidebar .channels ul");
	$togglesDest.append($togglesSrc);
	if ($togglesDest.children().length === 0) {
		$togglesDest.parent().hide();
	}
	$node.find(".debug-body .channels").remove();
}

/**
 * Grab the error toggles from .debug-body's error-summary move to sidebar
 */
function phpErrorToggles($node) {
	var $togglesUl = $node.find(".debug-sidebar .php-errors ul"),
		$errorSummary = $node.closest(".debug").find(".m_alert.error-summary"),
		haveFatal = $errorSummary.hasClass("have-fatal");
	if (haveFatal) {
		$togglesUl.append('<li><label class="toggle active">\
			<input type="checkbox" checked data-toggle="error" value="fatal" />fatal <span class="badge">1</span>\
			</label></li>');
	}
	$errorSummary.find("label").each(function(){
		var $li = $(this).parent(),
			$checkbox = $(this).find("input"),
			val = $checkbox.val();
		$togglesUl.append(
			$("<li>").append(
				$('<label class="toggle active">').html(
					$checkbox[0].outerHTML + val + ' <span class="badge">' + $checkbox.data("count") + "</span>"
				)
			)
		);
		$li.remove();
	});
	$errorSummary.find("ul").filter(function(){
		return $(this).children().length === 0;
	}).remove();
	if ($togglesUl.children().length === 0) {
		$togglesUl.parent().hide();
	}
	if (!haveFatal) {
		$errorSummary.remove();
	} else {
		$errorSummary.find("h3").eq(1).remove();
	}
}
