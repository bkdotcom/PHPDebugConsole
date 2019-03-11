/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups, arrays, & objects
 *    Add FontAwesome icons
 */

import $ from 'jquery';				// external global
import * as http from "./http.js";	// cookie & query  utils
import * as enhanceMain from "./enhanceMain.js";
import * as enhanceEntries from "./enhanceEntries.js";
import * as expandCollapse from "./expandCollapse.js";
import loadDeps from "./loadDeps.js";

// var $ = window.jQuery;	// may not be defined yet!
var listenersRegistered = false;
var options = {
	fontAwesomeCss: "//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css",
	// jQuerySrc: "//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js",
	clipboardSrc: "//cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.0/clipboard.min.js",
	classes: {
		expand : "fa-plus-square-o",
		collapse : "fa-minus-square-o",
		empty : "fa-square-o"
	},
	iconsMisc: {
		".timestamp" : '<i class="fa fa-calendar"></i>'
	},
	iconsObject: {
		"> .info.magic" :				'<i class="fa fa-fw fa-magic"></i>',
		"> .method.magic" :				'<i class="fa fa-fw fa-magic" title="magic method"></i>',
		"> .method.deprecated" :		'<i class="fa fa-fw fa-arrow-down" title="Deprecated"></i>',
		"> .property.debuginfo-value" :	'<i class="fa fa-eye" title="via __debugInfo()"></i>',
		"> .property.excluded" :		'<i class="fa fa-eye-slash" title="not included in __debugInfo"></i>',
		"> .property.private-ancestor" :'<i class="fa fa-lock" title="private ancestor"></i>',
		"> .property > .t_modifier_magic" :			'<i class="fa fa-magic" title="magic property"></i>',
		"> .property > .t_modifier_magic-read" :	'<i class="fa fa-magic" title="magic property"></i>',
		"> .property > .t_modifier_magic-write" :	'<i class="fa fa-magic" title="magic property"></i>',
		"[data-toggle=vis][data-vis=private]" :		'<i class="fa fa-user-secret"></i>',
		"[data-toggle=vis][data-vis=protected]" :	'<i class="fa fa-shield"></i>',
		"[data-toggle=vis][data-vis=excluded]" :	'<i class="fa fa-eye-slash"></i>'
	},
	// debug methods (not object methods)
	iconsMethods: {
		".group-header" :	'<i class="fa fa-lg fa-minus-square-o"></i>',
		".m_assert" :		'<i class="fa-lg"><b>&ne;</b></i>',
		".m_clear" :		'<i class="fa fa-lg fa-ban"></i>',
		".m_count" :		'<i class="fa fa-lg fa-plus-circle"></i>',
		".m_countReset" :	'<i class="fa fa-lg fa-plus-circle"></i>',
		".m_error" :		'<i class="fa fa-lg fa-times-circle"></i>',
		".m_info" :			'<i class="fa fa-lg fa-info-circle"></i>',
		".m_profile" :		'<i class="fa fa-lg fa-pie-chart"></i>',
		".m_profileEnd" :	'<i class="fa fa-lg fa-pie-chart"></i>',
		".m_time" :			'<i class="fa fa-lg fa-clock-o"></i>',
		".m_timeLog" :		'<i class="fa fa-lg fa-clock-o"></i>',
		".m_warn" :			'<i class="fa fa-lg fa-warning"></i>'
	},
	debugKey: getDebugKey()
};

if (typeof $ === 'undefined') {
	throw new TypeError('PHPDebugConsole\'s JavaScript requires jQuery.');
}

/*
	Load "optional" dependencies
*/
loadDeps([
	{
		src: options.fontAwesomeCss,
		type: 'stylesheet',
		check: function () {
			var span = document.createElement('span'),
				haveFa = false;
			function css(element, property) {
				return window.getComputedStyle(element, null).getPropertyValue(property);
			}
			span.className = 'fa';
			span.style.display = 'none';
			document.body.appendChild(span);
			haveFa = css(span, 'font-family') === 'FontAwesome';
			document.body.removeChild(span);
			return haveFa;
		}
	},
	/*
	{
		src: options.jQuerySrc,
		onLoaded: start,
		check: function () {
			return typeof window.jQuery !== "undefined";
		}
	},
	*/
	{
		src: options.clipboardSrc,
		check: function() {
			return typeof window.ClipboardJS !== "undefined";
		},
		onLoaded: function () {
			/*
				Copy strings/floats/ints to clipboard when clicking
			*/
			var clipboard = window.ClipboardJS;
			new clipboard('.debug .t_string, .debug .t_int, .debug .t_float, .debug .t_key', {
				target: function (trigger) {
					notify("Copied to clipboard");
					return trigger;
				}
			});
		}
	}
]);

$.fn.debugEnhance = function(method) {
	// console.warn("debugEnhance", this);
	var $self = this;
	if (typeof method == "object") {
		// options passed
		$.extend(options, method);
	} else if (method) {
		if (method === "addCss") {
			addCss(arguments[1]);
		} else if (method === "expand") {
			expandCollapse.expand($self);
		} else if (method === "collapse") {
			expandCollapse.collapse($self);
		} else if (method === "registerListeners") {
			registerListeners($self);
		} else if (method === "enhanceGroupHeader") {
			enhanceEntries.enhance($self);
		}
		return;
	}
	this.each(function() {
		var $self = $(this);
		if ($self.hasClass("enhanced")) {
			return;
		}
		if ($self.hasClass("debug")) {
			console.warn("enhancing debug");
			enhanceMain.init($self, options);
			enhanceEntries.init($self, options);
			expandCollapse.init($self, options);

			registerListeners($self);

			// only enhance root log entries
			// enhance collapsed/hidden entries when expanded

			enhanceEntries.enhance($self.find("> .debug-header, > .debug-content"));
		} else {
			// console.log("enhancing node");
			enhanceEntries.enhanceEntry($self);
		}
	});
	return this;
};

$(function() {
	var $debug = $(".debug");
	if ($debug.length) {
		$debug.debugEnhance();
	} else {
		// addCss();
		// registerListeners($("body"));
	}
	// addNoti($("body"));
});

function getDebugKey() {
	var key = null,
		queryParams = http.queryDecode(),
		cookieValue = http.cookieGet("debug");
	if (typeof queryParams.debug !== "undefined") {
		key = queryParams.debug;
	} else if (cookieValue) {
		key = cookieValue;
	}
	return key;
}

function registerListeners($root) {
	if (listenersRegistered) {
		return;
	}
	$("body").on("animationend", ".debug-noti", function () {
		$(this).removeClass("animate").closest(".debug-noti-wrap").hide();
	});
	listenersRegistered = true;
}

function notify(html) {
	$(".debug-noti").html(html).addClass("animate").closest(".debug-noti-wrap").show();
}
