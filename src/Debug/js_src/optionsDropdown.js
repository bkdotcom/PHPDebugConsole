import $ from "jquery";
import * as http from "./http.js";

var $root, options;

var KEYCODE_ESC = 27;

export function init($debugRoot, opts) {
	$root = $debugRoot;
	options = opts;

	addDropdown();

	$("#debug-options-toggle").on("click", function(e){
		var isVis = $(".debug-options").is(".show");
		if (!isVis) {
			open();
		} else {
			close();
		}
		e.stopPropagation();
	});

	$("input[name=debugCookie]").on("change", function(){
		var isChecked = $(this).is(":checked");
		if (isChecked) {
			http.cookieSave("debug", options.debugKey, 7);
		} else {
			http.cookieRemove("debug");
		}
	}).prop("checked", options.debugKey && http.cookieGet("debug") === options.debugKey);
	if (!options.debugKey) {
		$("input[name=debugCookie]").prop("disabled", true)
			.closest("label").addClass("disabled");
	}

	$("input[name=persistDrawer]").on("change", function(){
		var isChecked = $(this).is(":checked");
		options.persistDrawer = isChecked;
		http.lsSet("phpDebugConsole-persistDrawer", isChecked);
	}).prop("checked", options.persistDrawer);
}

function addDropdown() {
	var $menuBar = $root.find(".debug-menu-bar");
	$menuBar.find(".pull-right").prepend('<button id="debug-options-toggle" type="button" data-toggle="debug-options" aria-label="Options" aria-haspopup="true" aria-expanded="false">\
			<i class="fa fa-ellipsis-v fa-fw"></i>\
		</button>')
	$menuBar.append('<div class="debug-options" aria-labelledby="debug-options-toggle">\
			<div class="debug-options-body">\
				<label><input type="checkbox" name="debugCookie" /> Debug Cookie</label>\
				<label><input type="checkbox" name="persistDrawer" /> Keep Open/Closed</label>\
				<hr class="dropdown-divider" />\
				<a href="http://www.bradkent.com/php/debug" target="_blank">Documentation</a>\
			</div>\
		</div>');
}

function onBodyClick(e) {
	if ($root.find(".debug-options").find(e.target).length === 0) {
		// we clicked outside the dropdown
		close();
	}
}

function onBodyKeyup(e) {
	if (e.keyCode === KEYCODE_ESC) {
		close();
	}
}

function open() {
	$root.find(".debug-options").addClass("show");
	$("body").on("click", onBodyClick);
	$("body").on("keyup", onBodyKeyup);
}

function close() {
	$root.find(".debug-options").removeClass("show");
	$("body").off("click", onBodyClick);
	$("body").off("keyup", onBodyKeyup);
}
