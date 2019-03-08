import $ from "jquery";

var options;

export function init($root, opts) {
	$root.on("click", "[data-toggle=vis]", function() {
		toggleObjectVis(this);
		return false;
	});
	$root.on("click", "[data-toggle=interface]", function() {
		toggleInterfaceVis(this);
		return false;
	});
}

/**
 * Adds toggle icon & hides target
 * Minimal DOM manipulation -> apply to all descendants
 */
export function enhance($node) {
	$node.find("> .t_classname").each(function() {
		var $toggle = $(this),
			$target = $toggle.next();
		if ($target.is(".t_recursion, .excluded")) {
			$toggle.addClass("empty");
			return;
		}
		$toggle.append(' <i class="fa ' + options.classes.expand + '"></i>');
		$toggle.attr("data-toggle", "object");
		$target.hide();
	});
}

function toggleInterfaceVis(toggle) {
	var $toggle = $(toggle),
		iface = $(toggle).data("interface"),
		$methods = $(toggle).closest(".t_object").find("> .object-inner > dd[data-implements="+iface+"]");
	if ($(toggle).hasClass("toggle-off")) {
		$toggle.addClass("toggle-on").removeClass("toggle-off");
		$methods.show();
	} else {
		$toggle.addClass("toggle-off").removeClass("toggle-on");
		$methods.hide();
	}
}

function toggleObjectVis(toggle) {
	var vis = $(toggle).data("toggle"),
		$toggles = $(toggle).closest(".t_object").find("[data-toggle=vis][data-vis="+vis+"]");
	if ($(toggle).hasClass("toggle-off")) {
		// show for this and all descendants
		$toggles.
			html($(toggle).html().replace("show ", "hide ")).
			addClass("toggle-on").
			removeClass("toggle-off");
		$(toggle).closest(".object-inner").find("> ."+vis).show();
	} else {
		// hide for this and all descendants
		$toggles.
			html($(toggle).html().replace("hide ", "show ")).
			addClass("toggle-off").
			removeClass("toggle-on");
	$(toggle).closest(".object-inner").find("> ."+vis).hide();
	}
}
