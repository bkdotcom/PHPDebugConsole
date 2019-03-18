import $ from "jquery";
import {lsGet,lsSet} from "./http.js";

var options;

export function init(opts) {
	options = opts;
	if (!opts.sidebar) {
		return;
	}
	addMarkup();
	errorToggles();
	channelToggles();

	if (options.persistDrawer && !lsGet("phpDebugConsole-openSidebar")) {
		close();
	}

	$(".debug .sidebar-toggle").on("click", function() {
		var isVis = $(".debug-sidebar").is(".show");
		if (!isVis) {
			open();
		} else {
			close();
		}
	});

	$(".debug .debug-sidebar .toggle").on("click", function(e) {
		if ($(e.target).is("input")) {
			// clicking on label also fires a click even on input
			return;
		}
		var isActive = $(this).is(".active");
		$(this).toggleClass("active", !isActive);
	});
}

function addMarkup() {
	$(".debug-body").before('<div class="debug-sidebar show">\
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
		<ul class="list-unstyled">\
			<li><i class="fa fa-fw fa-lg fa-code"></i> PHP Errors\
				<ul class="list-unstyled php-errors">\
					<!--\
					<li class="toggle active">deprecated <span class="badge">1</span></li>\
					<li class="toggle active">notice <span class="badge">3</span></li>\
					-->\
				</ul>\
			</li>\
			<li><i class="fa fa-fw fa-lg fa-list-ul"></i> Channels\
				<ul class="list-unstyled channels">\
					<!--\
					<li class="toggle active">General</li>\
					<li class="toggle active">mySql</li>\
					-->\
				</ul>\
			</li>\
			<li class="toggle active"><i class="fa fa-fw fa-lg fa-bullhorn"></i> Alerts</li>\
			<li class="toggle active"><i class="fa fa-fw fa-lg fa-times-circle"></i> Error</li>\
			<li class="toggle active"><i class="fa fa-fw fa-lg fa-warning"></i> warning</li>\
			<li class="toggle active"><i class="fa fa-fw fa-lg fa-info-circle"></i> Info</li>\
			<li class="toggle active"><i class="fa fa-fw fa-lg fa-sticky-note-o"></i> Other</li>\
		</ul>\
		</div>');
}

function channelToggles() {
	var $toggles = $(".debug-sidebar .channels")
	$(".debug-body .channels li").each(function(){
		var $this = $(this).addClass("toggle active");
		$toggles.append(this);
	});
	$(".debug-body .channels").remove();
}

function errorToggles() {
	var $phpErrors = $(".debug-sidebar .php-errors")
	if ($(".m_error.error-fatal").length) {
		$phpErrors.append('<li class="toggle active"><label>fatal <span class="badge">1</span></label>');
	}
	$(".alert.error-summary label").each(function(){
		var $li = $(this).parent().addClass("toggle active");
		var html = $li.html();
		html = html.replace(/: (\d+)/, ' <span class="badge">$1</span>');
		$li.html(html);
		$phpErrors.append($li);
	});
	// $(".alert.error-summary").remove();
}

function open() {
	$(".debug-sidebar").addClass("show");
	lsSet("phpDebugConsole-openSidebar", true);
}

function close() {
	$(".debug-sidebar").removeClass("show");
	lsSet("phpDebugConsole-openSidebar", false);
}
