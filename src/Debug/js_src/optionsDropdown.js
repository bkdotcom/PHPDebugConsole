import $ from "jquery";
import {cookieGet,cookieRemove,cookieSet} from "./http.js";

var $root, config;

var KEYCODE_ESC = 27;

export function init($debugRoot) {
	$root = $debugRoot;
	config = $root.data("config");

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
			cookieSet("debug", config.get("debugKey"), 7);
		} else {
			cookieRemove("debug");
		}
	}).prop("checked", config.get("debugKey") && cookieGet("debug") === config.get("debugKey"));
	if (!config.get("debugKey")) {
		$("input[name=debugCookie]").prop("disabled", true)
			.closest("label").addClass("disabled");
	}

	$("input[name=persistDrawer]").on("change", function(){
		var isChecked = $(this).is(":checked");
		// options.persistDrawer = isChecked;
		config.set({
			persistDrawer: isChecked,
			openDrawer: isChecked,
			openSidebar: true
		});
	}).prop("checked", config.get("persistDrawer"));

	$("input[name=linkFiles]").on("change", function(){
		var isChecked = $(this).prop("checked"),
			$formGroup = $("#linkFilesTemplate").closest(".form-group");
		isChecked
			? $formGroup.slideDown()
			: $formGroup.slideUp();
		config.set("linkFiles", isChecked);
		$("input[name=linkFilesTemplate]").trigger("change");
	}).prop("checked", config.get("linkFiles")).trigger("change");

	$("input[name=linkFilesTemplate]").on("change", function(){
		var val = $(this).val();
		config.set("linkFilesTemplate", val);
		$debugRoot.trigger("config.debug.updated", "linkFilesTemplate");
	}).val(config.get("linkFilesTemplate"));
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
				<label><input type="checkbox" name="linkFiles" /> Create file links</label>\
				<div class="form-group">\
					<label for="linkFilesTemplate">Link Template</label>\
					<input name="linkFilesTemplate" id="linkFilesTemplate" />\
				</div>\
				<hr class="dropdown-divider" />\
				<a href="http://www.bradkent.com/php/debug" target="_blank">Documentation</a>\
			</div>\
		</div>');
	if (!config.get("drawer")) {
		$menuBar.find("input[name=persistDrawer]").closest("label").remove();
	}
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
