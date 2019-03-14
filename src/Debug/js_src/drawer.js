import $ from "jquery";

var $debug, options, origH, origPageY;

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
					return false
				};
			if (!isUp && -d > sh-h-st) {
				// Scrolling down, but this will take us past the bottom.
				$this.scrollTop(sh);
				return prevent()
			} else if (isUp && d > st) {
				// Scrolling up, but this will take us past the top.
				$this.scrollTop(0);
				return prevent();
			}
		})
		: this.off("DOMMouseScroll mousewheel wheel");
};

export function init(opts) {
	options = opts;
	if (!opts.drawer) {
		return;
	}
	$debug = $(".debug").addClass("debug-drawer");
	addDrawerMarkup();
	$(".debug-body").scrollLock();
	$(window).on("resize", function(){
		// console.log('window resize', $(window).height());
		setHeight();
	});
	$(".debug-resize-handle").on("mousedown", function(e) {
		if (!$(this).closest(".debug-drawer").is(".debug-drawer-open")) {
			// drawer isn't open / ignore resize
			return;
		}
		origH = $debug.find(".debug-body").height();
		origPageY = e.pageY;
		$("html").addClass("debug-resizing");
		$debug.parents()
			.on("mousemove", mousemove)
			.on("mouseup", mouseup);
		e.preventDefault();
	});
	$(".debug-pull-tab").on("click", function(){
		var $drawer = $(this).closest(".debug-drawer"),
			$body = $drawer.find(".debug-body");
		$drawer.addClass("debug-drawer-open");
		setHeight(); // makes sure height within min/max
		$("body").css("marginBottom", ($debug.height() + 8) + "px");
	});
	$(".debug-drawer .debug-menu-bar .close").on("click", function(){
		$(".debug-drawer").removeClass("debug-drawer-open");
		$("body").css("marginBottom", "");
	});
}

function addDrawerMarkup() {
	var $menuBar = $(".debug-menu-bar");
	// var $body = $('<div class="debug-body"></div>');
	$menuBar.before('<div class="debug-pull-tab">\
			<i class="fa fa-bug"></i> PHP\
		</div>\
		<div class="debug-resize-handle"></div>');
	$menuBar.html('<a href="http://www.bradkent.com/php/debug" target="_blank"><i class="fa fa-bug"></i> PHPDebugConsole</a>\
		<button type="button" class="close" data-dismiss="debug-drawer" aria-label="Close">\
			<span aria-hidden="true">&times;</span>\
		</button>');
	// var $move = $(".debug-menu-bar").nextAll();
	// $(".debug-menu-bar").after($body);
	// $move.appendTo($body);
}

function mousemove(e) {
	var h = origH + (origPageY - e.pageY);
	setHeight(h);
};

function mouseup() {
	$("html").removeClass("debug-resizing");
	$debug.parents()
		.off("mousemove", mousemove)
		.off("mouseup", mouseup);
	$("body").css("marginBottom", ($debug.height() + 8) + "px");
};

function setHeight(height) {
	var $body = $debug.find(".debug-body"),
		menuH = $debug.find(".debug-menu-bar").outerHeight(),
		minH = 20,
		// inacurate if document.doctype is null : $(window).height()
		//    aka document.documentElement.clientHeight
		maxH = window.innerHeight - menuH - 50;
	if (!height) {
		// no height passed -> use last or 100
		height = parseInt($body[0].style.height, 10) || 100
	}
	height = Math.min(height, maxH);
	height = Math.max(height, minH);
	$body.css("height", height);
	// localStorage.setItem('phpdebugbar-height', height);
	// this.recomputeBottomOffset();
};
