/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups, arrays, & objects
 *    Add FontAwesome icons
 */

import $ from 'jquery';				// external global
import * as enhanceEntries from "./enhanceEntries.js";
import * as enhanceMain from "./enhanceMain.js";
import * as expandCollapse from "./expandCollapse.js";
import * as http from "./http.js";	// cookie & query utils
import * as sidebar from "./sidebar.js";
import {Config} from "./config.js";
import loadDeps from "./loadDeps.js";

var listenersRegistered = false;
var config = new Config({
	fontAwesomeCss: "//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css",
	// jQuerySrc: "//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js",
	clipboardSrc: "//cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js",
	iconsExpand: {
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
		"> .method.inherited" :			'a: <i class="fa fa-fw fa-clone" title="Inherited"></i>',
		"> .property.debuginfo-value" :	'<i class="fa fa-eye" title="via __debugInfo()"></i>',
		"> .property.debuginfo-excluded" :	'<i class="fa fa-eye-slash" title="not included in __debugInfo"></i>',
		"> .property.private-ancestor" :	'<i class="fa fa-lock" title="private ancestor"></i>',
		"> .property > .t_modifier_magic" :			'<i class="fa fa-magic" title="magic property"></i>',
		"> .property > .t_modifier_magic-read" :	'<i class="fa fa-magic" title="magic property"></i>',
		"> .property > .t_modifier_magic-write" :	'<i class="fa fa-magic" title="magic property"></i>',
		"[data-toggle=vis][data-vis=private]" :		'<i class="fa fa-user-secret"></i>',
		"[data-toggle=vis][data-vis=protected]" :	'<i class="fa fa-shield"></i>',
		"[data-toggle=vis][data-vis=debuginfo-excluded]" :	'<i class="fa fa-eye-slash"></i>',
		"[data-toggle=vis][data-vis=inherited]" :	'<i class="fa fa-clone"></i>'
	},
	// debug methods (not object methods)
	iconsMethods: {
		".group-header"	:   '<i class="fa fa-lg fa-minus-square-o"></i>',
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
		".m_trace" :		'<i class="fa fa-list"></i>',
		".m_warn" :			'<i class="fa fa-lg fa-warning"></i>'
	},
	debugKey: getDebugKey(),
	drawer: false,
	persistDrawer: false,
	linkFiles: false,
	linkFilesTemplate: "subl://open?url=file://%file&line=%line",
	useLocalStorage: true
}, "phpDebugConsole");

if (typeof $ === 'undefined') {
	throw new TypeError('PHPDebugConsole\'s JavaScript requires jQuery.');
}

// var $selfScript = $(document.CurrentScript || document.scripts[document.scripts.length -1]);

/*
	Load "optional" dependencies
*/
loadDeps([
	{
		src: config.get('fontAwesomeCss'),
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
		src: config.get('clipboardSrc'),
		check: function() {
			return typeof window.ClipboardJS !== "undefined";
		},
		onLoaded: function () {
			/*
				Copy strings/floats/ints to clipboard when clicking
			*/
			new ClipboardJS('.debug .t_string, .debug .t_int, .debug .t_float, .debug .t_key', {
				target: function (trigger) {
					var range;
					if ($(trigger).is("a")) {
						return $('<div>')[0];
					}
					if (window.getSelection().toString().length) {
						// text was being selected vs a click
						range = window.getSelection().getRangeAt(0);
						setTimeout(function(){
							// re-select
							window.getSelection().addRange(range);
						});
						return $('<div>')[0];
					}
					notify("Copied to clipboard");
					return trigger;
				}
			});
		}
	}
]);

/*
function getSelectedText() {
	var text = "";
	if (typeof window.getSelection != "undefined") {
		text = window.getSelection().toString();
	} else if (typeof document.selection != "undefined" && document.selection.type == "Text") {
		text = document.selection.createRange().text;
	}
	return text;
}
*/

$.fn.debugEnhance = function(method, arg1, arg2) {
	// console.warn("debugEnhance", method, this);
	var $self = this,
		dataOptions = {},
		lsOptions = {},	// localStorage options
		options = {};
	if (method === "sidebar") {
		if (arg1 == "add") {
			sidebar.addMarkup($self);
		} else if (arg1 == "open") {
			sidebar.open($self);
		} else if (arg1 == "close") {
			sidebar.close($self);
		}
	} else if (method === "buildChannelList") {
		return enhanceMain.buildChannelList(arg1, arg2, arguments[3]);
	} else if (method === "collapse") {
		expandCollapse.collapse($self, arg1);
	} else if (method === "expand") {
		expandCollapse.expand($self);
	} else if (method === "init") {
		var conf = new Config(config.get(), "phpDebugConsole");
		$self.data("config", conf);
		conf.set($self.eq(0).data("options") || {});
		if (typeof arg1 == "object") {
			conf.set(arg1);
		}
		enhanceEntries.init($self);
		expandCollapse.init($self);
		registerListeners($self);
		enhanceMain.init($self);
		if (!conf.get("drawer")) {
			$self.debugEnhance();
		}
	} else if (method == "setConfig") {
		if (typeof arg1 == "object") {
			config.set(arg1);
			// update logs that have already been enhanced
			$(this)
				.find(".debug-log.enhanced")
				.closest(".debug")
				.trigger("config.debug.updated", "linkFilesTemplate");
		}
	} else {
		this.each(function() {
			var $self = $(this);
			// console.log('debugEnhance', this, $self.is(".enhanced"));
			if ($self.is(".debug")) {
				// console.warn("debugEnhance() : .debug");
				$self.find(".debug-log-summary, .debug-log").show();
				$self.find(".m_alert, .debug-log-summary, .debug-log").debugEnhance();
			} else if (!$self.is(".enhanced")) {
				if ($self.is(".group-body")) {
					// console.warn("debugEnhance() : .group-body", $self);
					enhanceEntries.enhanceEntries($self);
				} else {
					// log entry assumed
					// console.warn("debugEnhance() : entry");
					enhanceEntries.enhanceEntry($self); // true
				}
			}
		});
	}
	return this;
};

$(function() {
	$(".debug").each(function(){
		$(this).debugEnhance("init");
		// $(this).find(".m_alert, .debug-log-summary, .debug-log").debugEnhance();
	});
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
