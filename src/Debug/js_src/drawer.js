import $ from "jquery";

var $root, config, origH, origPageY;

/**
 * @see https://stackoverflow.com/questions/5802467/prevent-scrolling-of-parent-element-when-inner-element-scroll-position-reaches-t
 */
$.fn.scrollLock = function(enable){
	enable = typeof enable == "undefined"
		? true
		: enable;
	return enable
		? this.on("DOMMouseScroll mousewheel wheel",function(e){
			var $this = $(this),
				st = this.scrollTop,
				sh = this.scrollHeight,
				h = $this.innerHeight(),
				d = e.originalEvent.wheelDelta,
				isUp = d>0,
				prevent = function(){
					e.stopPropagation();
					e.preventDefault();
					e.returnValue = false;
					return false;
				};
			if (!isUp && -d > sh-h-st) {
				// Scrolling down, but this will take us past the bottom.
				$this.scrollTop(sh);
				return prevent();
			} else if (isUp && d > st) {
				// Scrolling up, but this will take us past the top.
				$this.scrollTop(0);
				return prevent();
			}
		})
		: this.off("DOMMouseScroll mousewheel wheel");
}

export function init($debugRoot) {
	$root = $debugRoot;
	config = $root.data("config");
	if (!config.get("drawer")) {
		return;
	}

	$root.addClass("debug-drawer");

	addMarkup();

	$root.find(".debug-body").scrollLock();
	$root.find(".debug-resize-handle").on("mousedown", onMousedown);
	$root.find(".debug-pull-tab").on("click", open);
	$root.find(".debug-menu-bar .close").on("click", close);

	if (config.get("persistDrawer") && config.get("openDrawer")) {
		open();
	}
}

function addMarkup() {
	var $menuBar = $(".debug-menu-bar");
	// var $body = $('<div class="debug-body"></div>');
	$menuBar.before('\
		<div class="debug-pull-tab" title="Open PHPDebugConsole"><i class="fa fa-bug"></i><i class="fa fa-spinner fa-pulse"></i> PHP</div>\
		<div class="debug-resize-handle"></div>'
	);
	$menuBar.html('<i class="fa fa-bug"></i> PHPDebugConsole\
		<div class="pull-right">\
			<button type="button" class="close" data-dismiss="debug-drawer" aria-label="Close">\
				<span aria-hidden="true">&times;</span>\
			</button>\
		</div>');
}

function open() {
	$root.addClass("debug-drawer-open");
	$root.debugEnhance();
	setHeight(); // makes sure height within min/max
	$("body").css("marginBottom", ($root.height() + 8) + "px");
	$(window).on("resize", setHeight);
	if (config.get("persistDrawer")) {
		config.set("openDrawer", true);
	}
}

function close() {
	$root.removeClass("debug-drawer-open");
	$("body").css("marginBottom", "");
	$(window).off("resize", setHeight);
	if (config.get("persistDrawer")) {
		config.set("openDrawer", false);
	}
}

function onMousemove(e) {
	var h = origH + (origPageY - e.pageY);
	setHeight(h, true);
}

function onMousedown(e) {
	if (!$(e.target).closest(".debug-drawer").is(".debug-drawer-open")) {
		// drawer isn't open / ignore resize
		return;
	}
	origH = $root.find(".debug-body").height();
	origPageY = e.pageY;
	$("html").addClass("debug-resizing");
	$root.parents()
		.on("mousemove", onMousemove)
		.on("mouseup", onMouseup);
	e.preventDefault();
}

function onMouseup() {
	$("html").removeClass("debug-resizing");
	$root.parents()
		.off("mousemove", onMousemove)
		.off("mouseup", onMouseup);
	$("body").css("marginBottom", ($root.height() + 8) + "px");
}

function setHeight(height, viaUser) {
	var $body = $root.find(".debug-body"),
		menuH = $root.find(".debug-menu-bar").outerHeight(),
		minH = 20,
		// inacurate if document.doctype is null : $(window).height()
		//    aka document.documentElement.clientHeight
		maxH = window.innerHeight - menuH - 50;
	if (!height || typeof height === "object") {
		// no height passed -> use last or 100
		height = parseInt($body[0].style.height, 10);
		if (!height && config.get("persistDrawer")) {
			height = config.get("height");
		}
		if (!height) {
			height = 100;
		}
	}
	height = Math.min(height, maxH);
	height = Math.max(height, minH);
	$body.css("height", height);
	if (viaUser && config.get("persistDrawer")) {
		config.set("height", height);
	}
}
