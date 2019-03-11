import $ from "jquery";

var origH, origPageY, $debug;

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
	var $body = $debug.find(".debug-drawer-body"),
		menuH = $debug.find(".debug-menu-bar").outerHeight(),
		minH = 20,
		maxH = $(window).height() - menuH - 50;
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

export function init() {
	$debug = $(".debug-drawer");
	if ($debug.length === 0) {
		return;
	}
	$(".debug-drawer-body").scrollLock();
	$(window).on("resize", function(){
		// console.log('window resize', $(window).height());
		setHeight();
	});
	$(".debug-resize-handle").on("mousedown", function(e) {
		if (!$(this).closest(".debug-drawer").is(".debug-drawer-open")) {
			// drawer isn't open / ignore resize
			return;
		}
		origH = $debug.find(".debug-drawer-body").height();
		origPageY = e.pageY;
		$("html").addClass("debug-resizing");
		$debug.parents()
			.on("mousemove", mousemove)
			.on("mouseup", mouseup);
		e.preventDefault();
	});
	$(".debug-pull-tab").on("click", function(){
		var $drawer = $(this).closest(".debug-drawer"),
			$body = $drawer.find(".debug-drawer-body");
		$drawer.addClass("debug-drawer-open");
		setHeight(); // makes sure height within min/max
		$("body").css("marginBottom", ($debug.height() + 8) + "px");
	});
	$(".debug-drawer .debug-menu-bar .close").on("click", function(){
		$(".debug-drawer").removeClass("debug-drawer-open");
		$("body").css("marginBottom", "");
	});
}
