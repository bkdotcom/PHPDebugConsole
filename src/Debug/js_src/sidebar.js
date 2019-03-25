import $ from "jquery";
import {lsGet,lsSet} from "./http.js";
import {addTest as addFilterTest} from "./filter.js";

var options;
var methods;	// method filters
var $root;

export function init($debugRoot, opts) {
	$root = $debugRoot;
	options = opts;
	if (!opts.sidebar) {
		return;
	}
	addMarkup();
	phpErrorToggles();
	channelToggles();
	moveExpandAll();
	setMethods();

	if (options.persistDrawer && !lsGet("phpDebugConsole-openSidebar")) {
		close();
	}

	addFilterTest(function($node){
		var method = $node[0].className.match(/\bm_(\S+)\b/)[1];
		if (["alert","error","warn","info"].indexOf(method) > -1) {
			return methods.indexOf(method) > -1;
		} else {
			return methods.indexOf("other") > -1;
		}
	});

	$root.find(".sidebar-toggle").on("click", function() {
		var isVis = $(".debug-sidebar").is(".show");
		if (!isVis) {
			open();
		} else {
			close();
		}
	});

	$root.find(".debug-sidebar input[type=checkbox]").on("change", function(e) {
		var $input = $(this),
			isActive = $input.is(":checked"),
			$nested = $input.closest(".toggle").next("ul").find("> li > .toggle");
		$input.closest(".toggle").toggleClass("active", isActive);
		$nested.toggleClass("active", isActive);
		if ($input.val() == "error-fatal") {
			$(".m_alert.error-summary").toggle(!isActive);
		}
		if ($input.is("[data-toggle=method]")) {
			setMethods();
		}
	});
}

function addMarkup() {
	var $sidebar = $('<div class="debug-sidebar show no-transition"></div>');
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
		<ul class="list-unstyled debug-filters">\
			<li class="php-errors">\
				<span><i class="fa fa-fw fa-lg fa-code"></i> PHP Errors</span>\
				<ul class="list-unstyled">\
				</ul>\
			</li>\
			<li class="channels">\
				<span><i class="fa fa-fw fa-lg fa-list-ul"></i> Channels</span>\
				<ul class="list-unstyled">\
				</ul>\
			</li>\
			<li><label class="toggle active"><input type="checkbox" checked data-toggle="method" value="alert"><i class="fa fa-fw fa-lg fa-bullhorn"></i> Alerts</label></li>\
			<li><label class="toggle active"><input type="checkbox" checked data-toggle="method" value="error"><i class="fa fa-fw fa-lg fa-times-circle"></i> Error</label></li>\
			<li><label class="toggle active"><input type="checkbox" checked data-toggle="method" value="warn"><i class="fa fa-fw fa-lg fa-warning"></i> warning</label></li>\
			<li><label class="toggle active"><input type="checkbox" checked data-toggle="method" value="info"><i class="fa fa-fw fa-lg fa-info-circle"></i> Info</label></li>\
			<li><label class="toggle active"><input type="checkbox" checked data-toggle="method" value="other"><i class="fa fa-fw fa-lg fa-sticky-note-o"></i> Other</label></li>\
		</ul>\
	');
	$(".debug-body").before($sidebar);
	setTimeout(function(){
		$sidebar.removeClass("no-transition");
	}, 500);
}

function channelToggles() {
	var $togglesDest = $(".debug-sidebar .channels ul"),
		$toggles = $(".debug-body .channels > ul > li");
	$toggles.find("label").addClass("toggle active");
	$togglesDest.append($toggles);
	if ($togglesDest.children().length === 0) {
		$togglesDest.parent().hide();
	}
	$(".debug-body .channels").remove();
}

function moveExpandAll() {
	var $btn = $(".debug-body > .expand-all"),
		html = $btn.html();
	$btn.html(html.replace('Expand', 'Exp'));
	$btn.appendTo($(".debug-sidebar"));
}

function phpErrorToggles() {
	var $togglesUl = $(".debug-sidebar .php-errors ul"),
		haveFatal = $(".m_error.error-fatal").length > 0;
	if (haveFatal) {
		$togglesUl.append('<li class="toggle active"><label>\
			<input type="checkbox" checked data-toggle="error" value="error-fatal" />fatal <span class="badge">1</span>\
			</label></li>');
	}
	$(".m_alert.error-summary label").each(function(){
		var $li = $(this).parent().addClass("toggle active"),
			$checkbox = $(this).find("input"),
			val = $checkbox.val().replace('error-', ''),
			html = '<label>' + $checkbox[0].outerHTML + val + ' <span class="badge">' + $(this).find("input").data("count") + '</span></label>';
		$li.html(html);
		$togglesUl.append($li);
	});
	if ($togglesUl.children().length === 0) {
		$togglesUl.parent().hide();
	}
	if (!haveFatal) {
		$(".m_alert.error-summary").remove();
	} else {
		$(".m_alert.error-summary").find('h3').eq(1).remove();
	}
}

function open() {
	$(".debug-sidebar").addClass("show");
	lsSet("phpDebugConsole-openSidebar", true);
}

function close() {
	$(".debug-sidebar").removeClass("show");
	lsSet("phpDebugConsole-openSidebar", false);
}

function setMethods() {
	methods = [];
	$root.find("input[data-toggle=method]:checked").each(function(){
		methods.push($(this).val());
	});
}
