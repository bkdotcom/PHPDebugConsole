import $ from "jquery";

var config;

export function init($delegateNode) {
	config = $delegateNode.data("config").get();
	$delegateNode.on("click", "[data-toggle=vis]", function() {
		toggleVis(this);
		return false;
	});
	$delegateNode.on("click", "[data-toggle=interface]", function() {
		toggleInterface(this);
		return false;
	});
}

function addIcons($node) {
	// console.warn('addIcons', $node);
	$.each(config.iconsObject, function(selector, v){
		var prepend = true,
			matches = v.match(/^([ap])\s*:(.+)$/);
		if (matches) {
			prepend = matches[1] == 'p';
			v = matches[2];
		}
		if (prepend) {
			$node.find(selector).prepend(v);
		} else {
			$node.find(selector).append(v);
		}
	});
	$node.find("> .property > .fa:first-child, > .property > span:first-child > .fa").
		addClass("fa-fw");
}

/**
 * Adds toggle icon & hides target
 * Minimal DOM manipulation -> apply to all descendants
 */
export function enhance($node) {
	$node.find("> .classname").each(function() {
		var $toggle = $(this),
			$target = $toggle.next(),
			isEnhanced = $toggle.data("toggle") == "object";
		if ($target.is(".t_recursion, .excluded")) {
			$toggle.addClass("empty");
			return;
		}
		if (isEnhanced) {
			return;
		}
		$toggle.append(' <i class="fa ' + config.iconsExpand.expand + '"></i>');
		$toggle.attr("data-toggle", "object");
		$target.hide();
	});
}

export function enhanceInner($node) {
	var $wrapper = $node.parent(),
		flags = {
			hasProtected: $node.children(".protected").not(".magic, .magic-read, .magic-write").length > 0,
			hasPrivate: $node.children(".private").not(".magic, .magic-read, .magic-write").length > 0,
			hasExcluded: $node.children(".debuginfo-excluded").hide().length > 0,
			hasInherited: $node.children(".inherited").length > 0
		},
		accessible = $wrapper.data("accessible"),
		toggleClass = accessible === "public" ?
			"toggle-off" :
			"toggle-on",
		toggleVerb = accessible === "public" ?
			"show" :
			"hide",
		visToggles = "",
		hiddenInterfaces = [];
	if ($node.is(".enhanced")) {
		return;
	}
	if ($node.find(".method[data-implements]").hide().length) {
		// linkify visibility
		$node.find(".method[data-implements]").each( function() {
			var iface = $(this).data("implements");
			if (hiddenInterfaces.indexOf(iface) < 0) {
				hiddenInterfaces.push(iface);
			}
		});
		$.each(hiddenInterfaces, function(i, iface) {
			$node.find(".interface").each(function() {
				var html = '<span class="toggle-off" data-toggle="interface" data-interface="'+iface+'" title="toggle methods">' +
						'<i class="fa fa-eye-slash"></i>' + iface + "</span>";
				if ($(this).text() == iface) {
					$(this).html(html);
				}
			});
		});
	}
	$wrapper.find(".private, .protected").
		filter(".magic, .magic-read, .magic-write").
		removeClass("private protected");
	if (accessible === "public") {
		$wrapper.find(".private, .protected").hide();
	}
	if (flags.hasProtected) {
		visToggles += ' <span class="'+toggleClass+'" data-toggle="vis" data-vis="protected">' + toggleVerb + " protected</span>";
	}
	if (flags.hasPrivate) {
		visToggles += ' <span class="'+toggleClass+'" data-toggle="vis" data-vis="private">' + toggleVerb + " private</span>";
	}
	if (flags.hasExcluded) {
		visToggles += ' <span class="toggle-off" data-toggle="vis" data-vis="debuginfo-excluded">show excluded</span>';
	}
	if (flags.hasInherited) {
		visToggles += ' <span class="toggle-on" data-toggle="vis" data-vis="inherited">hide inherited methods</span>';
	}
	$node.prepend('<span class="vis-toggles">' + visToggles + "</span>");
	addIcons($node);
	$node.find("> .property.forceShow").show().find("> .t_array-expand").each(function() {
		$(this).debugEnhance("expand");
	});
	$node.addClass("enhanced");
}

function toggleInterface(toggle) {
	var $toggle = $(toggle),
		iface = $toggle.data("interface"),
		$methods = $toggle.closest(".t_object").find("> .object-inner > dd[data-implements="+iface+"]");
	if ($toggle.is(".toggle-off")) {
		$toggle.addClass("toggle-on").removeClass("toggle-off");
		$methods.show();
	} else {
		$toggle.addClass("toggle-off").removeClass("toggle-on");
		$methods.hide();
	}
}

/**
 * Toggle visibility for private/protected properties and methods
 */
function toggleVis(toggle) {
	// console.log('toggleVis', toggle);
	var $toggle = $(toggle),
		vis = $toggle.data("vis"),
		$objInner = $toggle.closest(".object-inner"),
		$toggles = $objInner.find("[data-toggle=vis][data-vis="+vis+"]"),
		$nodes = $objInner.find("."+vis);
	if ($toggle.is(".toggle-off")) {
		// show for this and all descendants
		$toggles.
			html($toggle.html().replace("show ", "hide ")).
			addClass("toggle-on").
			removeClass("toggle-off");
		$nodes.each(function(){
			var $node = $(this),
				$objInner = $node.closest(".object-inner"),
				show = true;
			$objInner.find("> .vis-toggles [data-toggle]").each(function(){
				var $toggle = $(this),
					vis = $toggle.data("vis"),
					isOn = $toggle.is(".toggle-on");
				// if any applicable test is false, don't show it
				if (!isOn && $node.hasClass(vis)) {
					show = false;
					return false;	// break
				}
			});
			if (show) {
				$node.show();
			}
		});
	} else {
		// hide for this and all descendants
		$toggles.
			html($toggle.html().replace("hide ", "show ")).
			addClass("toggle-off").
			removeClass("toggle-on");
		$nodes.hide();
	}
}
