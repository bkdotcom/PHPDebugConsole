/**
 * Add primary Ui elements
 */

import $ from "jquery";
import * as drawer from "./drawer.js";
import * as http from "./http.js";

var options;

export function init($root, opts) {
	options = opts;
	addCss($root.selector);
	enhanceErrorSummary($root);
	addExpandAll($root);
	addNoti($("body"));
	addPersistOption($root);
	drawer.init();
	$root.find(".channels").show();
	$root.find(".loading").hide();
	$root.addClass("enhanced");

	$root.on("change", "input[data-toggle=channel]", function() {
		var channel = $(this).val(),
			$nodes = $(this).data("isRoot")
				? $root.find(".m_group > *").not(".m_group").filter(function() {
						var nodeChannel = $(this).data("channel");
						return  nodeChannel === channel || nodeChannel === undefined;
					})
				: $root.find('.m_group > [data-channel="'+channel+'"]').not(".m_group");
		$nodes.toggleClass("hidden-channel", !$(this).is(":checked"));
	});

	$root.on("change", "input[data-toggle=error]", function() {
		var className = $(this).val(),
			selector = ".debug-log-summary ." + className +", .debug-log ."+className;
		$root.find(selector).toggleClass("hidden-error", !$(this).is(":checked"));
		// update icon for all groups having nested error
		// groups containing only hidden errors will loose +/-
		/*
		$root.find(".m_error, .m_warn").parents(".m_group").prev(".group-header").each(function() {
			expandCollapse.groupErrorIconChange($(this));
		});
		*/
		$root.find(".m_error, .m_warn").parents(".m_group").trigger("debug.collapsed.group");
	});
}

/**
 * Adds CSS to head of page
 */
function addCss(scope) {
	var css = "" +
			".debug .error-fatal:before { padding-left: 1.25em; }" +
			".debug .error-fatal i.fa-times-circle { position:absolute; top:.7em; }" +
			".debug .debug-cookie { color:#666; }" +
			".debug .hidden-channel, .debug .hidden-error { display:none !important; }" +
			".debug i.fa, .debug .m_assert i { margin-right:.33em; }" +
			".debug .m_assert > i { position:relative; top:-.20em; }" +
			".debug i.fa-plus-circle { opacity:0.42; }" +
			".debug i.fa-calendar { font-size:1.1em; }" +
			".debug i.fa-eye { color:#00529b; font-size:1.1em; border-bottom:0; }" +
			".debug i.fa-magic { color: orange; }" +
			".debug .excluded > i.fa-eye-slash { color:#f39; }" +
			".debug i.fa-lg { font-size:1.33em; }" +
			".debug .group-header.expanded i.fa-warning, .debug .group-header.expanded i.fa-times-circle { display:none; }" +
			".debug .group-header i.fa-warning { color:#cdcb06; margin-left:.33em}" +		// warning
			".debug .group-header i.fa-times-circle { color:#D8000C; margin-left:.33em;}" +	// error
			".debug a.expand-all { font-size:1.25em; color:inherit; text-decoration:none; display:block; clear:left; }" +
			".debug .group-header.hidden-channel + .m_group," +
			"	.debug *:not(.group-header) + .m_group {" +
			"		margin-left: 0;" +
			"		border-left: 0;" +
			"		padding-left: 0;" +
			"		display: block !important;" +
			"	}" +
			".debug [data-toggle]," +
			"	.debug [data-toggle][title]," +	// override .debug[title]
			"	.debug .vis-toggles span { cursor:pointer; }" +
			".debug .group-header.empty, .debug .t_classname.empty { cursor:auto; }" +
			".debug .vis-toggles span:hover," +
			"	.debug [data-toggle=interface]:hover { background-color:rgba(0,0,0,0.1); }" +
			".debug .vis-toggles .toggle-off," +
			"	.debug .interface .toggle-off { opacity:0.42 }" +
			".debug .show-more-container {display: inline;}" +
			".debug .show-more-wrapper {display:block; position:relative; height:70px; overflow:hidden;}" +
			".debug .show-more-fade {" +
			"	position:absolute;" +
			"	bottom: -1px;" +
			"	width:100%; height:55px;" +
			"	background-image: linear-gradient(to bottom, transparent, white);" +
			"	pointer-events: none;" +
			"}" +
			".debug .level-error .show-more-fade, .debug .m_error .show-more-fade { background-image: linear-gradient(to bottom, transparent, #FFBABA); }" +
			".debug .level-info .show-more-fade, .debug .m_info .show-more-fade { background-image: linear-gradient(to bottom, transparent, #BDE5F8); }" +
			".debug .level-warn .show-more-fade, .debug .m_warn .show-more-fade { background-image: linear-gradient(to bottom, transparent, #FEEFB3); }" +
			".debug [title]:hover .show-more-fade { background-image: linear-gradient(to bottom, transparent, #c9c9c9); }" +
			".debug .show-more, .debug .show-less {" +
			"   display: table;" +
			"	box-shadow: 1px 1px 0px 0px rgba(0,0,0, 0.20);" +
			"	border: 1px solid rgba(0,0,0, 0.20);" +
			"	border-radius: 2px;" +
			"	background-color: #EEE;" +
			"}" +
			".debug-noti-wrap {" +
			"	position: fixed;" +
			"	display: none;" +
			"	top: 0;" +
			"	width: 100%;" +
			"	height: 100%;" +
			"	pointer-events: none;" +
			"}" +
			".debug-noti-wrap .debug-noti {" +
			"	display: table-cell;" +
			"	text-align: center;" +
			"	vertical-align: bottom;" +
			"	font-size: 30px;" +
			"	transform-origin: 50% 100%;" +
			"}" +
			".debug-noti-table {display:table; width:100%; height:100%}" +
			".debug-noti.animate {" +
			"	animation-duration: 1s;" +
			"	animation-name: expandAndFade;" +
			"	animation-timing-function: ease-in;" +
			"}" +
			"@keyframes expandAndFade {" +
			"	from {" +
			"		opacity: .9;" +
			"		transform: scale(.9, .94);" +
			"	}" +
			"	to {" +
			"		opacity: 0;" +
			"		transform: scale(1, 1);" +
			"	}" +
			"}" +
			"",
		id = 'debug_javascript_style';
	if (scope) {
		css = css.replace(new RegExp(/\.debug\s/g), scope+" ");
		id += scope.replace(/\W/g, "_");
	}
	if ($("head").find("#"+id).length === 0) {
		$('<style id="' + id + '">' + css + '</style>').appendTo("head");
	}
}

function enhanceErrorSummary($root) {
	var $errorSummary = $root.find(".alert.error-summary");
	$errorSummary.find("h3:first-child").prepend(options.iconsMethods[".m_error"]);
	$errorSummary.find("li[class*=error-]").each(function() {
		var classAttr = $(this).attr("class"),
			html = $(this).html(),
			htmlNew = '<label>' +
				'<input type="checkbox" checked data-toggle="error" value="' + classAttr + '" /> ' +
				html +
				'</label>';
		$(this).html(htmlNew).removeAttr("class");
	});
}

function addExpandAll($root) {
	var $expandAll = $("<a>", {
			"href":"#"
		}).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups').addClass("expand-all");
	if ( $root.find(".group-header").length ) {
		$expandAll.on("click", function() {
			$root.find(".group-header").not(".expanded").each(function() {
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

function addPersistOption($root) {
	var $node;
	if (options.debugKey) {
		$node = $('<label class="debug-cookie"><input type="checkbox"> Keep debug on</label>').css({"float":"right"});
		if (http.cookieGet("debug") === options.debugKey) {
			$node.find("input").prop("checked", true);
		}
		$("input", $node).on("change", function() {
			var checked = $(this).is(":checked");
			console.log("debug persist checkbox changed", checked);
			if (checked) {
				console.log("debugKey", options.debugKey);
				http.cookieSave("debug", options.debugKey, 7);
			} else {
				http.cookieRemove("debug");
			}
		});
		$root.find(".debug-bar").eq(0).prepend($node);
	}
}
