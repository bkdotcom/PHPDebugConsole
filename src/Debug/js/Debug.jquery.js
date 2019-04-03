(function ($) {
	'use strict';

	$ = $ && $.hasOwnProperty('default') ? $['default'] : $;

	function cookieGet(name) {
		var nameEQ = name + "=",
			ca = document.cookie.split(";"),
			c = null,
			i = 0;
		for ( i = 0; i < ca.length; i += 1 ) {
			c = ca[i];
			while (c.charAt(0) === " ") {
				c = c.substring(1, c.length);
			}
			if (c.indexOf(nameEQ) === 0) {
				return c.substring(nameEQ.length, c.length);
			}
		}
		return null;
	}

	function cookieRemove(name) {
		cookieSave(name, "", -1);
	}

	function cookieSave(name, value, days) {
		// console.log("cookieSave", name, value, days);
		var expires = "",
			date = new Date();
		if ( days ) {
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toGMTString();
		}
		document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
	}

	function lsGet(key) {
		return JSON.parse(window.localStorage.getItem(key));
	}

	function lsSet(key, val) {
		window.localStorage.setItem(key, JSON.stringify(val));
	}

	function queryDecode(qs) {
		var params = {},
			tokens,
			re = /[?&]?([^&=]+)=?([^&]*)/g;
		if (qs === undefined) {
			qs = document.location.search;
		}
		qs = qs.split("+").join(" ");	// replace + with " "
		while (true) {
			tokens = re.exec(qs);
			if (!tokens) {
				break;
			}
			params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
		}
		return params;
	}

	var $root, options, origH, origPageY;

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

	function init($debugRoot, opts) {
		// console.warn('drawer.init', $debugRoot[0]);
		$root = $debugRoot;
		options = opts;
		if (!opts.drawer) {
			return;
		}

		$root.addClass("debug-drawer");

		addMarkup();

		if (options.persistDrawer && lsGet("phpDebugConsole-openDrawer")) {
			open();
		}

		$root.find(".debug-body").scrollLock();
		$root.find(".debug-resize-handle").on("mousedown", onMousedown);
		$root.find(".debug-pull-tab").on("click", open);
		$root.find(".debug-menu-bar .close").on("click", close);
	}

	function addMarkup() {
		var $menuBar = $(".debug-menu-bar");
		// var $body = $('<div class="debug-body"></div>');
		$menuBar.before('\
		<div class="debug-pull-tab" title="Open PHPDebugConsole"><i class="fa fa-bug"></i> PHP</div>\
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
		setHeight(); // makes sure height within min/max
		$("body").css("marginBottom", ($root.height() + 8) + "px");
		$(window).on("resize", setHeight);
		if (options.persistDrawer) {
			lsSet("phpDebugConsole-openDrawer", true);
		}
	}

	function close() {
		$root.removeClass("debug-drawer-open");
		$("body").css("marginBottom", "");
		$(window).off("resize", setHeight);
		if (options.persistDrawer) {
			lsSet("phpDebugConsole-openDrawer", false);
		}
	}

	function onMousemove(e) {
		var h = origH + (origPageY - e.pageY);
		setHeight(h, true);
	}

	function onMousedown(e) {
		if (!$(this).closest(".debug-drawer").is(".debug-drawer-open")) {
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
			if (!height && options.persistDrawer) {
				height = lsGet("phpDebugConsole-height");
			}
			if (!height) {
				height = 100;
			}
		}
		height = Math.min(height, maxH);
		height = Math.max(height, minH);
		$body.css("height", height);
		if (viaUser && options.persistDrawer) {
			lsSet("phpDebugConsole-height", height);
		}
	}

	/**
	 * Filter entries
	 */

	var channels = [];
	var tests = [
		function ($node) {
			var channel = $node.data("channel");
			return channels.indexOf(channel) > -1;
		}
	];
	var preFilterCallbacks = [
		function ($root) {
			var $checkboxes = $root.find("input[data-toggle=channel]");
			channels = $checkboxes.length
				? []
				: [undefined];
			$checkboxes.filter(":checked").each(function(){
				channels.push($(this).val());
				if ($(this).data("isRoot")) {
					channels.push(undefined);
				}
			});
		}
	];

	function init$1($delegateNode) {

		$delegateNode.on("change", "input[type=checkbox]", function() {
			var $this = $(this),
				isChecked = $this.is(":checked"),
				$nested = $this.closest("label").next("ul").find("input");
			if ($this.data("toggle") == "error") {
				// filtered separately
				return;
			}
			$nested.prop("checked", isChecked);
			applyFilter($this.closest(".debug"));
		});

		$delegateNode.on("change", "input[data-toggle=error]", function() {
			var $this = $(this),
				$root = $this.closest(".debug"),
				errorClass = $this.val(),
				isChecked = $this.is(":checked"),
				selector = ".group-body ." + errorClass;
			$root.find(selector).toggleClass("filter-hidden", !isChecked);
			// trigger collapse to potentially update group icon
			$root.find(".m_error, .m_warn").parents(".m_group").find(".group-body")
				.trigger("debug.collapsed.group");
		});
	}

	function addTest(func) {
		tests.push(func);
	}

	function addPreFilter(func) {
		preFilterCallbacks.push(func);
	}

	function applyFilter($root) {
		var i;
		for (i in preFilterCallbacks) {
			preFilterCallbacks[i]($root);
		}
		$root.find("> .debug-body .m_alert, .group-body > *").each(function(){
			var $node = $(this),
				show = true;
			if ($node.data("channel") == "phpError") {
				// php Errors are filtered separately
				return;
			}
			for (i in tests) {
				show = tests[i]($node);
				if (!show) {
					break;
				}
			}
			$node.toggleClass("filter-hidden", !show);
		});
	}

	var $root$1, options$1;

	var KEYCODE_ESC = 27;

	function init$2($debugRoot, opts) {
		$root$1 = $debugRoot;
		options$1 = opts;

		addDropdown();

		$("#debug-options-toggle").on("click", function(e){
			var isVis = $(".debug-options").is(".show");
			if (!isVis) {
				open$1();
			} else {
				close$1();
			}
			e.stopPropagation();
		});

		$("input[name=debugCookie]").on("change", function(){
			var isChecked = $(this).is(":checked");
			if (isChecked) {
				cookieSave("debug", options$1.debugKey, 7);
			} else {
				cookieRemove("debug");
			}
		}).prop("checked", options$1.debugKey && cookieGet("debug") === options$1.debugKey);
		if (!options$1.debugKey) {
			$("input[name=debugCookie]").prop("disabled", true)
				.closest("label").addClass("disabled");
		}

		$("input[name=persistDrawer]").on("change", function(){
			var isChecked = $(this).is(":checked");
			options$1.persistDrawer = isChecked;
			lsSet("phpDebugConsole-persistDrawer", isChecked);
		}).prop("checked", options$1.persistDrawer);
	}

	function addDropdown() {
		var $menuBar = $root$1.find(".debug-menu-bar");
		$menuBar.find(".pull-right").prepend('<button id="debug-options-toggle" type="button" data-toggle="debug-options" aria-label="Options" aria-haspopup="true" aria-expanded="false">\
			<i class="fa fa-ellipsis-v fa-fw"></i>\
		</button>');
		$menuBar.append('<div class="debug-options" aria-labelledby="debug-options-toggle">\
			<div class="debug-options-body">\
				<label><input type="checkbox" name="debugCookie" /> Debug Cookie</label>\
				<label><input type="checkbox" name="persistDrawer" /> Keep Open/Closed</label>\
				<hr class="dropdown-divider" />\
				<a href="http://www.bradkent.com/php/debug" target="_blank">Documentation</a>\
			</div>\
		</div>');
	}

	function onBodyClick(e) {
		if ($root$1.find(".debug-options").find(e.target).length === 0) {
			// we clicked outside the dropdown
			close$1();
		}
	}

	function onBodyKeyup(e) {
		if (e.keyCode === KEYCODE_ESC) {
			close$1();
		}
	}

	function open$1() {
		$root$1.find(".debug-options").addClass("show");
		$("body").on("click", onBodyClick);
		$("body").on("keyup", onBodyKeyup);
	}

	function close$1() {
		$root$1.find(".debug-options").removeClass("show");
		$("body").off("click", onBodyClick);
		$("body").off("keyup", onBodyKeyup);
	}

	var options$2;
	var methods;	// method filters
	var $root$2;

	function init$3($debugRoot, opts) {
		$root$2 = $debugRoot;
		options$2 = opts;

		if (!opts.sidebar) {
			return;
		}

		addMarkup$1();

		if (options$2.persistDrawer && !lsGet("phpDebugConsole-openSidebar")) {
			close$2();
		}

		addPreFilter(function($root){
			methods = [];
			$root.find("input[data-toggle=method]:checked").each(function(){
				methods.push($(this).val());
			});
		});

		addTest(function($node){
			var method = $node[0].className.match(/\bm_(\S+)\b/)[1];
			if (["alert","error","warn","info"].indexOf(method) > -1) {
				return methods.indexOf(method) > -1;
			} else {
				return methods.indexOf("other") > -1;
			}
		});

		$root$2.on("click", ".close[data-dismiss=alert]", function() {
			// setTimeout -> new thread -> executed after event bubbled
			setTimeout(function(){
				if ($root$2.find(".m_alert").length) {
					$root$2.find(".debug-sidebar input[data-toggle=method][value=alert]").parent().addClass("disabled");
				}
			});
		});

		$root$2.find(".sidebar-toggle").on("click", function() {
			var isVis = $(".debug-sidebar").is(".show");
			if (!isVis) {
				open$2();
			} else {
				close$2();
			}
		});

		$root$2.find(".debug-sidebar input[type=checkbox]").on("change", function(e) {
			var $input = $(this),
				$toggle = $input.closest(".toggle"),
				$nested = $toggle.next("ul").find(".toggle"),
				isActive = $input.is(":checked");
			$toggle.toggleClass("active", isActive);
			$nested.toggleClass("active", isActive);
			if ($input.val() == "error-fatal") {
				$(".m_alert.error-summary").toggle(!isActive);
			}
		});
	}

	function addMarkup$1() {
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
		</ul>\
	');
		$root$2.find(".debug-body").before($sidebar);

		phpErrorToggles();
		moveChannelToggles();
		addMethodToggles();
		moveExpandAll();

		setTimeout(function(){
			$sidebar.removeClass("no-transition");
		}, 500);
	}

	function addMethodToggles() {
		var $filters = $root$2.find(".debug-filters"),
			$entries = $root$2.find("> .debug-body .m_alert, .group-body > *"),
			val,
			labels = {
				alert: '<i class="fa fa-fw fa-lg fa-bullhorn"></i> Alerts',
				error: '<i class="fa fa-fw fa-lg fa-times-circle"></i> Error',
				warn: '<i class="fa fa-fw fa-lg fa-warning"></i> Warning',
				info: '<i class="fa fa-fw fa-lg fa-info-circle"></i> Info',
				other: '<i class="fa fa-fw fa-lg fa-sticky-note-o"></i> Other'
			},
			haveEntry;
		for (val in labels) {
			haveEntry = val == "other"
				? $entries.not(".m_alert, .m_error, .m_warn, .m_info").length > 0
				: $entries.filter(".m_"+val).length > 0;
			$filters.append(
				$("<li>").append(
					$('<label class="toggle active disabled" />').toggleClass("disabled", !haveEntry).append(
						$("<input />", {
							type: "checkbox",
							checked: true,
							"data-toggle": "method",
							value: val
						})
					).append(
						labels[val]
					)
				)
			);
		}

	}

	/**
	 * grab the .debug-body toggles and move them to sidebar
	 */
	function moveChannelToggles() {
		var $togglesSrc = $root$2.find(".debug-body .channels > ul > li"),
			$togglesDest = $root$2.find(".debug-sidebar .channels ul");
		$togglesSrc.find("label").addClass("toggle active");
		$togglesDest.append($togglesSrc);
		if ($togglesDest.children().length === 0) {
			$togglesDest.parent().hide();
		}
		$root$2.find(".debug-body .channels").remove();
	}

	/**
	 * Grab the .debug-body "Expand All" and move it to sidebar
	 */
	function moveExpandAll() {
		var $btn = $root$2.find(".debug-body > .expand-all"),
			html = $btn.html();
		if ($btn.length) {
			$btn.html(html.replace('Expand', 'Exp'));
			$btn.appendTo($root$2.find(".debug-sidebar"));
		}
	}

	/**
	 * Grab the error toggles from .debug-body's error-summary move to sidebar
	 */
	function phpErrorToggles() {
		var $togglesUl = $root$2.find(".debug-sidebar .php-errors ul"),
			$errorSummary = $root$2.find(".m_alert.error-summary"),
			haveFatal = $root$2.find(".m_error.error-fatal").length > 0;
		if (haveFatal) {
			$togglesUl.append('<li class="toggle active"><label>\
			<input type="checkbox" checked data-toggle="error" value="error-fatal" />fatal <span class="badge">1</span>\
			</label></li>');
		}
		$errorSummary.find("label").each(function(){
			var $li = $(this).parent().addClass("toggle active"),
				$checkbox = $(this).find("input"),
				val = $checkbox.val().replace("error-", ""),
				html = "<label>" + $checkbox[0].outerHTML + val + ' <span class="badge">' + $checkbox.data("count") + "</span></label>";
			$li.html(html);
			$togglesUl.append($li);
		});
		if ($togglesUl.children().length === 0) {
			$togglesUl.parent().hide();
		}
		if (!haveFatal) {
			$errorSummary.remove();
		} else {
			$errorSummary.find("h3").eq(1).remove();
		}
	}

	function open$2() {
		$(".debug-sidebar").addClass("show");
		lsSet("phpDebugConsole-openSidebar", true);
	}

	function close$2() {
		$(".debug-sidebar").removeClass("show");
		lsSet("phpDebugConsole-openSidebar", false);
	}

	/**
	 * Add primary Ui elements
	 */

	var options$3;
	var $root$3;

	function init$4($debugRoot, opts) {
		options$3 = opts;
		$root$3 = $debugRoot;
		addChannelToggles();
		addExpandAll();
		addNoti($("body"));
		addPersistOption();
		enhanceErrorSummary();
		init($root$3, opts);
		init$1($root$3);
		init$3($root$3, opts);
		init$2($root$3, opts);
		$root$3.find(".loading").hide();
		$root$3.addClass("enhanced");
	}

	function addChannelToggles() {
		var channels = $root$3.data("channels"),
			$toggles,
			$ul = buildChannelList(channels, "", $root$3.data("channelRoot"));
		$toggles = $("<fieldset />", {
				class: "channels",
			})
			.append('<legend>Channels</legend>')
			.append($ul);
		if ($ul.html().length) {
			$root$3.find(".debug-body").prepend($toggles);
		}
	}

	function addExpandAll() {
		var $expandAll = $("<button>", {
			class: "expand-all"
			}).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups');
		// this is currently invoked before entries are enhance / empty class not yet added
		if ($root$3.find(".m_group:not(.empty)").length) {
			$expandAll.on("click", function() {
				$(this).closest(".debug").find(".group-header").not(".expanded").each(function() {
					$(this).debugEnhance('expand');
				});
				return false;
			});
			$root$3.find(".debug-log-summary").before($expandAll);
		}
	}

	function addNoti($root) {
		$root.append('<div class="debug-noti-wrap">' +
				'<div class="debug-noti-table">' +
					'<div class="debug-noti"></div>' +
				'</div>' +
			'</div>');
	}

	function addPersistOption() {
		var $node;
		if (options$3.debugKey) {
			$node = $('<label class="debug-cookie" title="Add/remove debug cookie"><input type="checkbox"> Keep debug on</label>');
			if (cookieGet("debug") === options$3.debugKey) {
				$node.find("input").prop("checked", true);
			}
			$("input", $node).on("change", function() {
				var checked = $(this).is(":checked");
				if (checked) {
					cookieSave("debug", options$3.debugKey, 7);
				} else {
					cookieRemove("debug");
				}
			});
			$root$3.find(".debug-menu-bar").eq(0).prepend($node);
		}
	}

	function buildChannelList(channels, prepend, channelRoot) {
		var $ul = $('<ul class="list-unstyled">'),
			$li,
			channel,
			$label;
		prepend = prepend || "";
		if ($.isArray(channels)) {
			channels = channelsToTree(channels);
		}
		for (channel in channels) {
			if (channel === "phpError") {
				// phpError is a special channel
				continue;
			}
			$li = $("<li>");
			$label = $('<label>').append($("<input>", {
				checked: true,
				"data-is-root": channel == channelRoot,
				"data-toggle": "channel",
				type: "checkbox",
				value: prepend + channel
			})).append(" " + channel);
			$li.append($label);
			if (Object.keys(channels[channel]).length) {
				$li.append(buildChannelList(channels[channel], prepend + channel + "."));
			}
			$ul.append($li);
		}
		return $ul;
	}

	function channelsToTree(channels) {
		var channelTree = {},
			ref,
			i, i2,
			path;
		for (i = 0; i < channels.length; i++) {
			ref = channelTree;
			path = channels[i].split('.');
			for (i2 = 0; i2 < path.length; i2++) {
				if (!ref[ path[i2] ]) {
					ref[ path[i2] ] = {};
				}
				ref = ref[ path[i2] ];
			}
		}
		return channelTree;
	}

	function enhanceErrorSummary() {
		var $errorSummary = $root$3.find(".m_alert.error-summary");
		$errorSummary.find("h3:first-child").prepend(options$3.iconsMethods[".m_error"]);
		$errorSummary.find("li[class*=error-]").each(function() {
			var classAttr = $(this).attr("class"),
				html = $(this).html(),
				htmlReplace = '<li><label>' +
					'<input type="checkbox" checked data-toggle="error" data-count="'+$(this).data("count")+'" value="' + classAttr + '" /> ' +
					html +
					'</label></li>';
			$(this).replaceWith(htmlReplace);
		});
	}

	var options$4;

	function init$5($delegateNode, opts) {
		options$4 = opts;
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
		$.each(options$4.iconsObject, function(selector, v){
			$node.find(selector).prepend(v);
		});
		$node.find("> .property > .fa:first-child, > .property > span:first-child > .fa").
			addClass("fa-fw");
	}

	/**
	 * Adds toggle icon & hides target
	 * Minimal DOM manipulation -> apply to all descendants
	 */
	function enhance($node) {
		$node.find("> .t_classname").each(function() {
			var $toggle = $(this),
				$target = $toggle.next();
			if ($target.is(".t_recursion, .excluded")) {
				$toggle.addClass("empty");
				return;
			}
			$toggle.append(' <i class="fa ' + options$4.iconsExpand.expand + '"></i>');
			$toggle.attr("data-toggle", "object");
			$target.hide();
		});
	}

	function enhanceInner($node) {
		var $wrapper = $node.parent(),
			hasProtected = $node.children(".protected").not(".magic, .magic-read, .magic-write").length > 0,
			hasPrivate = $node.children(".private").not(".magic, .magic-read, .magic-write").length > 0,
			hasExcluded = $node.children(".excluded").hide().length > 0,
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
							'<i class="fa fa-eye-slash"></i> ' + iface + "</span>";
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
		if (hasProtected) {
			visToggles += ' <span class="'+toggleClass+'" data-toggle="vis" data-vis="protected">' + toggleVerb + " protected</span>";
		}
		if (hasPrivate) {
			visToggles += ' <span class="'+toggleClass+'" data-toggle="vis" data-vis="private">' + toggleVerb + " private</span>";
		}
		if (hasExcluded) {
			visToggles += ' <span class="'+toggleClass+'" data-toggle="vis" data-vis="excluded">' + toggleVerb + " excluded</span>";
		}
		$node.prepend('<span class="vis-toggles">' + visToggles + "</span>");
		addIcons($node);
		$node.find("> .property.forceShow").show().find("> .t_array-expand").each(function() {
			$(this).debugEnhance('expand');
		});
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
		var $toggle = $(toggle),
			vis = $toggle.data("vis"),
			$objInner = $toggle.closest(".object-inner"),
			$toggles = $objInner.find("[data-toggle=vis][data-vis="+vis+"]");
		if ($toggle.is(".toggle-off")) {
			// show for this and all descendants
			$toggles.
				html($toggle.html().replace("show ", "hide ")).
				addClass("toggle-on").
				removeClass("toggle-off");
			$objInner.find("> ."+vis).show();
		} else {
			// hide for this and all descendants
			$toggles.
				html($toggle.html().replace("hide ", "show ")).
				addClass("toggle-off").
				removeClass("toggle-on");
			$objInner.find("> ."+vis).hide();
		}
	}

	/**
	 * Add sortability to given table
	 */
	function makeSortable(table) {

		var	$table = $(table),
			$head = $table.find("> thead");
		if (!$table.is("table.sortable")) {
			return $table;
		}
		$table.addClass("table-sort");
		$head.on("click", "th", function() {
			var $th = $(this),
				$cells = $(this).closest("tr").children(),
				i = $cells.index($th),
				curDir = $th.is(".sort-asc") ? "asc" : "desc",
				newDir = curDir === "desc" ? "asc" : "desc";
			$cells.removeClass("sort-asc sort-desc");
			$th.addClass("sort-"+newDir);
			if (!$th.find(".sort-arrows").length) {
				// this th needs the arrow markup
				$cells.find(".sort-arrows").remove();
				$th.append('<span class="fa fa-stack sort-arrows pull-right">' +
						'<i class="fa fa-caret-up" aria-hidden="true"></i>' +
						'<i class="fa fa-caret-down" aria-hidden="true"></i>' +
					"</span>");
			}
			sortTable($table[0], i, newDir);
		});
		return $table;
	}

	/**
	 * Sort table
	 *
	 * @param obj table dom element
	 * @param int col   column index
	 * @param str dir   (asc) or desc
	 */
	function sortTable(table, col, dir) {
		var body = table.tBodies[0],
			rows = body.rows,
			i,
			floatRe = /^([+\-]?(?:0|[1-9]\d*)(?:\.\d*)?)(?:[eE]([+\-]?\d+))?$/,
			collator = typeof Intl.Collator === "function"
				? new Intl.Collator([], {
					numeric: true,
					sensitivity: "base"
				})
				: false;
		dir = dir === "desc" ? -1 : 1;
		rows = Array.prototype.slice.call(rows, 0), // Converts HTMLCollection to Array
		rows = rows.sort(function (trA, trB) {
			var a = trA.cells[col].textContent.trim(),
				b = trB.cells[col].textContent.trim(),
				afloat = a.match(floatRe),
				bfloat = b.match(floatRe);
			if (afloat && afloat[2]) {
				// sci notation
				a = Number.parseFloat(a).toFixed(6);
			}
			if (bfloat && bfloat[2]) {
				// sci notation
				b = Number.parseFloat(b).toFixed(6);
			}
			if (afloat && bfloat) {
				if (a < b) {
					return -1;
				}
				if (a > b) {
					return 1;
				}
				return 0;
			}
			return collator
				? dir * collator.compare(a, b)
				: dir * a.localeCompare(b);	// not a natural sort
		});
		for (i = 0; i < rows.length; ++i) {
			body.appendChild(rows[i]); // append each row in order (which moves)
		}
	}

	var options$5;

	function init$6($root, opts) {
		options$5 = opts;
		init$5($root, options$5);
		$root.on("click", ".close[data-dismiss=alert]", function() {
			$(this).parent().remove();
		});
		$root.on("click", ".show-more-container .show-more", function() {
			var $container = $(this).closest(".show-more-container");
			$container.find(".show-more-wrapper").animate({
				height: $container.find(".t_string").height()
			},400,"swing",function() {
				$(this).css("display", "inline");
			});
			$container.find(".show-more-fade").fadeOut();
			$container.find(".show-more").hide();
			$container.find(".show-less").show();
		});
		$root.on("click", ".show-more-container .show-less", function() {
			var $container = $(this).closest(".show-more-container");
			$container.find(".show-more-wrapper")
				.css("display", "block")
				.animate({
					height: "70px"
				});
			$container.find(".show-more-fade").fadeIn();
			$container.find(".show-more").show();
			$container.find(".show-less").hide();
		});
		$root.on("debug.expand.array", function(e){
			var $node = $(e.target);
			if ($node.is(".enhanced")) {
				return;
			}
			$node.find("> .array-inner > .key-value > :last-child").each(function() {
				enhanceValue(this);
			});
		});
		$root.on("debug.expand.group", function(e){
			enhanceEntries($(e.target));
		});
		$root.on("debug.expand.object", function(e){
			var $node = $(e.target);
			if ($node.is(".enhanced")) {
				return;
			}
			$node.find("> .constant > :last-child, > .property > :last-child").each(function() {
				enhanceValue(this);
			});
			enhanceInner($node);
		});
		$root.on("debug.expanded.array, debug.expanded.group, debug.expanded.object", function(e){
			var $node = $(e.target);
			if ($node.is(".enhanced")) {
				return;
			}
			enhanceStrings($node);
			$node.addClass("enhanced");
		});
	}

	/**
	 * add font-awsome icons
	 */
	function addIcons$1($root, types) {
		if (!$.isArray(types)) {
			types = typeof types === "undefined" ?
				["misc"] :
				[types];
		}
		if ($.inArray("misc", types) >= 0) {
			$.each(options$5.iconsMisc, function(selector,v){
				$root.find(selector).prepend(v);
			});
		}
		if ($.inArray("methods", types) >= 0) {
			$.each(options$5.iconsMethods, function(selector,v){
				var $caption;
				if ($root.is(selector)) {
					if ($root.is(".m_profileEnd") && $root.find("> table").length) {
						$caption = $root.find("> table > caption");
						if (!$caption.length) {
							$caption = $("<caption>");
							$root.find("> table").prepend($caption);
						}
						$root = $caption;
					}
					$root.prepend(v);
					return false;	// break
				}
			});
		}
	}

	/**
	 * Enhance log entries
	 */
	function enhanceEntries($node) {
		$node.hide();
		$node.children().each(function() {
			enhanceEntry($(this));
		});
		$node.show();
		enhanceStrings($node);
	}

	/**
	 * Adds expand/collapse functionality to array
	 * does not enhance values
	 */
	function enhanceArray($node) {
		// console.log("enhanceArray", $node[0]);
		var isEnhanced = $node.prev().is(".t_array-expand"),
			$expander = $('<span class="t_array-expand" data-toggle="array">' +
					'<span class="t_keyword">array</span><span class="t_punct">(</span> ' +
					'<i class="fa ' + options$5.iconsExpand.expand + '"></i>&middot;&middot;&middot; ' +
					'<span class="t_punct">)</span>' +
				"</span>"),
			numParents = $node.parentsUntil(".m_group", ".t_object, .t_array").length;
		if (isEnhanced) {
			return;
		}
		if ($.trim($node.find(".array-inner").html()).length < 1) {
			// empty array -> don't add expand/collapse
			$node.find("br").hide();
			$node.find(".array-inner").hide();
			return;
		}
		// add collapse link
		$node.find(".t_keyword").first().
			wrap('<span class="t_array-collapse expanded" data-toggle="array">').
			after('<span class="t_punct">(</span> <i class="fa ' + options$5.iconsExpand.collapse + '"></i>').
			parent().next().remove();	// remove original "("
		$node.before($expander);
		if (numParents === 0) {
			// outermost array -> leave open
			$node.debugEnhance("expand");
		} else {
			$node.find(".t_array-collapse").first().debugEnhance("collapse");
		}
	}

	/**
	 * Enhance a single log entry
	 * we don't enhance strings by default (add showmore).. needs to be visible to calc height
	 */
	function enhanceEntry($entry, inclStrings) {
		// console.log("enhanceEntry", $entry.attr("class"));
		if ($entry.is(".enhanced")) {
			return;
		}
		if ($entry.is(".m_group")) {
			// minimal enhancement... just adds data-toggle attr and hides target
			// target will not be enhanced until expanded
			enhanceGroup($entry.find("> .group-header"));
		/*
		} else if ($entry.is(".m_groupSummary")) {
			// groupSummary has no toggle.. and is uncollapsed -> enhance
			enhanceEntries($entry);
		*/
		} else {
			// regular log-type entry
			$entry.children().each(function() {
				enhanceValue(this);
			});
			addIcons$1($entry, ["methods", "misc"]);
		}
		if (inclStrings) {
			enhanceStrings($entry);
		}
		$entry.addClass("enhanced");
	}

	function enhanceGroup($toggle) {
		var $group = $toggle.parent(),
			$target = $toggle.next();
		addIcons$1($toggle, ["methods"]);
		$toggle.attr("data-toggle", "group");
		$.each(["level-error","level-info","level-warn"], function(i, val){
			var $icon;
			if ($toggle.hasClass(val)) {
				$icon = $toggle.children("i").eq(0);
				$toggle.wrapInner('<span class="'+val+'"></span>');
				$toggle.prepend($icon); // move icon
			}
		});
		$toggle.removeClass("collapsed level-error level-info level-warn"); // collapsed class is never used
		if ($.trim($target.html()).length < 1) {
			$group.addClass("empty");
		}
		if ($toggle.is(".expanded") || $target.find(".m_error, .m_warn").not(".filter-hidden").length) {
			$toggle.debugEnhance("expand");
		} else {
			$toggle.debugEnhance("collapse", true);
		}
	}

	function enhanceStrings($root) {
		$root.find(".t_string:not(.enhanced):visible").each(function() {
			var $this = $(this),
				$container,
				$stringWrap,
				height = $this.height(),
				diff = height - 70;
			if (diff > 35) {
				$stringWrap = $this.wrap('<div class="show-more-wrapper"></div>').parent();
				$stringWrap.append('<div class="show-more-fade"></div>');
				$container = $stringWrap.wrap('<div class="show-more-container"></div>').parent();
				$container.append('<button type="button" class="show-more"><i class="fa fa-caret-down"></i> More</button>');
				$container.append('<button type="button" class="show-less" style="display:none;"><i class="fa fa-caret-up"></i> Less</button>');
			}
			$this.addClass("enhanced");
		});
	}

	function enhanceValue(node) {
		var $node = $(node);
		if ($node.is(".t_array")) {
			enhanceArray($node);
		} else if ($node.is(".t_object")) {
			enhance($node);
		} else if ($node.is("table")) {
			makeSortable($node);
		} else if ($node.is(".timestamp")) {
			var $i = $node.find("i"),
				text = $node.text(),
				$span = $("<span>"+text+"</span>");
			if ($node.is(".t_string")) {
				$span.addClass("t_string numeric");
			} else if ($node.is(".t_int")) {
				$span.addClass("t_int");
			} else {
				$span.addClass("t_float");
			}
			if ($node.is(".no-pseudo")) {
				$span.addClass("no-pseudo");
			}
			$node.removeClass("t_float t_int t_string numeric no-pseudo");
			$node.html($i).append($span);
		}
	}

	/**
	 * handle expanding/collapsing arrays, groups, & objects
	 */

	var options$6;

	function init$7($delegateNode, opts) {
		options$6 = opts;
		$delegateNode.on("click", "[data-toggle=array]", function() {
			toggle(this);
			return false;
		});
		$delegateNode.on("click", "[data-toggle=group]", function() {
			toggle(this);
			return false;
		});
		$delegateNode.on("click", "[data-toggle=object]", function() {
			toggle(this);
			return false;
		});
		$delegateNode.on("debug.collapsed.group", function(e){
			// console.warn('debug.collapsed.group');
			groupErrorIconUpdate($(e.target).prev());
		});
	}

	/**
	 * Collapse an array, group, or object
	 *
	 * @param jQueryObj $toggle   the toggle node
	 * @param immediate immediate no annimation
	 *
	 * @return void
	 */
	function collapse($toggle, immediate) {
		var $target = $toggle.next(),
			$groupEndValue,
			what = "array",
			icon = options$6.iconsExpand.expand;
		if ($toggle.is("[data-toggle=array]")) {
			// show and use the "expand it" toggle as reference toggle
			$toggle = $toggle.closest(".t_array").prev().show();
			$target = $toggle.next();
			$target.hide();
		} else {
			if ($toggle.is("[data-toggle=group]")) {
				$groupEndValue = $target.find("> .m_groupEndValue > :last-child");
				if ($groupEndValue.length && $toggle.find(".group-label").last().nextAll().length == 0) {
					$toggle.find(".group-label").last().after('<span class="t_operator"> : </span>' + $groupEndValue[0].outerHTML);
				}
				what = "group";
			} else {
				what = "object";
			}
			$toggle.removeClass("expanded");
			if (immediate) {
				$target.hide();
				iconUpdate($toggle, icon);
			} else {
				$target.slideUp("fast", function() {
					iconUpdate($toggle, icon);
				});
			}
		}
		$target.trigger("debug.collapsed." + what);
	}

	function expand($toggleOrTarget) {
		var isToggle = $toggleOrTarget.is("[data-toggle]"),
			$toggle = isToggle
				? $toggleOrTarget
				: $toggleOrTarget.prev(),
			$target = isToggle
				? $toggleOrTarget.next()
				: $toggleOrTarget,
			what = "array";
		if ($toggle.is("[data-toggle=group]")) {
			what = "group";
		} else if ($toggle.is("[data-toggle=object]")) {
			what = "object";
		}
		// trigger while still hidden!
		//    no redraws
		$target.trigger('debug.expand.' + what);
		if (what === "array") {
			// hide the toggle..  there is a different toggle in the expanded version
			$toggle.hide();
			$target.show();
			$target.trigger('debug.expanded.' + what);
		} else {
			$target.slideDown("fast", function() {
				var $groupEndValue = $target.find("> .m_groupEndValue");
				$toggle.addClass("expanded");
				iconUpdate($toggle, options$6.iconsExpand.collapse);
				if ($groupEndValue.length) {
					// remove value from label
					$toggle.find(".group-label").last().nextAll().remove();
				}
				$target.trigger('debug.expanded.' + what);
			});
		}
	}

	function groupErrorIconGet($container) {
		var icon = "";
		if ($container.find(".m_error").not(".filter-hidden").length) {
			icon = options$6.iconsMethods[".m_error"];
		} else if ($container.find(".m_warn").not(".filter-hidden").length) {
			icon = options$6.iconsMethods[".m_warn"];
		}
		return icon;
	}

	function groupErrorIconUpdate($toggle) {
		var selector = ".fa-times-circle, .fa-warning",
			$group = $toggle.parent(),
			$target = $toggle.next(),
			icon = groupErrorIconGet($target),
			isExpanded = $toggle.is(".expanded");
		$group.removeClass("empty");	// "empty" class just affects cursor
		if (icon) {
			if ($toggle.find(selector).length) {
				$toggle.find(selector).replaceWith(icon);
			} else {
				$toggle.append(icon);
			}
			iconUpdate($toggle, isExpanded
				? options$6.iconsExpand.collapse
				: options$6.iconsExpand.expand
			);
		} else {
			$toggle.find(selector).remove();
			if ($target.children().not(".m_warn, .m_error").length < 1) {
				// group only contains errors & they're now hidden
				$group.addClass("empty");
				iconUpdate($toggle, options$6.iconsExpand.empty);
			}
		}
	}

	function iconUpdate($toggle, classNameNew) {
		var $icon = $toggle.children("i").eq(0);
		if ($toggle.is(".group-header") && $toggle.parent().is(".empty")) {
			classNameNew = options$6.iconsExpand.empty;
		}
		$.each(options$6.iconsExpand, function(i, className) {
			$icon.toggleClass(className, className === classNameNew);
		});
	}

	function toggle(toggle) {
		var $toggle = $(toggle);
		if ($toggle.is(".group-header") && $toggle.parent().is(".empty")) {
			return;
		}
		if ($toggle.is(".expanded")) {
			collapse($toggle);
		} else {
			expand($toggle);
		}
	}

	function loadDeps(deps) {
		var checkInterval,
			intervalCounter = 1;
		deps.reverse();
		if (document.getElementsByTagName("body")[0].childElementCount == 1) {
			// output only contains debug
			// don't wait for interval to begin
			loadDepsDoer(deps);
		} else {
			loadDepsDoer(deps, true);
		}
		checkInterval = setInterval(function() {
			loadDepsDoer(deps, intervalCounter === 10);
			if (deps.length === 0) {
				clearInterval(checkInterval);
			} else if (intervalCounter === 20) {
				clearInterval(checkInterval);
			}
			intervalCounter++;
		}, 500);
	}

	function addScript(src) {
		var firstScript = document.getElementsByTagName("script")[0],
			jsNode = document.createElement("script");
		jsNode.src = src;
		firstScript.parentNode.insertBefore(jsNode, firstScript);
	}

	function addStylesheet(src) {
		var link = document.createElement("link");
		link.type = "text/css";
		link.rel = "stylesheet";
		link.href = src;
		document.head.appendChild(link);
	}

	function loadDepsDoer(deps, checkOnly) {
		var dep,
			type,
			i;
		for (i = deps.length - 1; i >= 0; i--) {
			dep = deps[i];
			if (dep.check()) {
				// dependency exists
				if (dep.onLoaded) {
					dep.onLoaded();
				}
				deps.splice(i, 1);	// remove it
				continue;
			}
			if (dep.status != "loading" && !checkOnly) {
				dep.status = "loading";
				type = dep.type || 'script';
				if (type == 'script') {
					addScript(dep.src);
				} else if (type == 'stylesheet') {
					addStylesheet(dep.src);
				}
			}
		}
	}

	/**
	 * Enhance debug output
	 *    Add expand/collapse functionality to groups, arrays, & objects
	 *    Add FontAwesome icons
	 */

	// var $ = window.jQuery;	// may not be defined yet!
	var listenersRegistered = false;
	var optionsDefault = {
		fontAwesomeCss: "//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css",
		// jQuerySrc: "//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js",
		clipboardSrc: "//cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.0/clipboard.min.js",
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
		debugKey: getDebugKey(),
		drawer: false,
		persistDrawer: lsGet("phpDebugConsole-persistDrawer")
	};

	if (typeof $ === 'undefined') {
		throw new TypeError('PHPDebugConsole\'s JavaScript requires jQuery.');
	}

	/*
		Load "optional" dependencies
	*/
	loadDeps([
		{
			src: optionsDefault.fontAwesomeCss,
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
			src: optionsDefault.clipboardSrc,
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
		// console.warn("debugEnhance", method, this);
		var $self = this,
			options = {};
		if (method === "addCss") {
			addCss(arguments[1]);
		} else if (method === "buildChannelList") {
			return buildChannelList(arguments[1], "", arguments[2]);
		} else if (method === "collapse") {
			collapse($self);
		} else if (method === "expand") {
			expand($self);
		} else if (method === "init") {
			options = $self.eq(0).data("options") || {};
			options = $.extend({}, optionsDefault, options);
			init$4($self, options);
			init$6($self, options);
			init$7($self, options);
			registerListeners($self);
		} else if (method == "setOptions") {
			if (typeof arguments[1] == "object") {
				options = $self.data("options") || {};
				$.extend(options, arguments[1]);
				$self.data("options", options);
	 		}
		} else {
			this.each(function() {
				var $self = $(this);
				if ($self.is(".enhanced")) {
					return;
				}
				if ($self.is(".group-body")) {
					enhanceEntries($self);
				} else {
					// log entry assumed
					enhanceEntry($self, true);
				}
			});
		}
		return this;
	};

	$(function() {
		$(".debug").each(function(){
			$(this).debugEnhance("init");
			$(this).find(".debug-log-summary, .debug-log").debugEnhance();
		});
	});

	function getDebugKey() {
		var key = null,
			queryParams = queryDecode(),
			cookieValue = cookieGet("debug");
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

}(window.jQuery));
