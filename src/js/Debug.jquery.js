/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups and arrays
 *    Add FontAwesome icons
 */

(function($) {

	var fontAwesomeCss = "//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css",
		jQuerySrc = "//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js",
		classes = {
			expand : "fa-plus-square-o",
			collapse : "fa-minus-square-o",
			empty : "fa-square-o"
		},
		iconsMisc = {
			// ".expand-all" :		'<i class="fa fa-lg fa-plus"></i>',
			".timestamp" :			'<i class="fa fa-calendar"></i>'
		},
		iconsObject = {
			".debug-value" :		'<i class="fa fa-eye" title="via __debugInfo()"></i>',
			".toggle-protected" :	'<i class="fa fa-shield"></i>',
			".toggle-private" :		'<i class="fa fa-user-secret"></i>'
		},
		iconsMethods = {
			".group-header" :		'<i class="fa fa-lg ' + classes.collapse + '"></i>',
			".m_assert" :			'<i class="fa-lg"><b>&ne;</b></i>',
			".m_count" :			'<i class="fa fa-lg fa-plus-circle"></i>',
			".m_error" :			'<i class="fa fa-lg fa-times-circle"></i>',
			".m_info" :				'<i class="fa fa-lg fa-info-circle"></i>',
			".m_warn" :				'<i class="fa fa-lg fa-warning"></i>',
			".m_time" :				'<i class="fa fa-lg fa-clock-o"></i>'
		},
		intervalCounter = 0,
		checkInterval,
		loading = false,
		debugKey = getDebugKey();

	if ( !$ ) {
		console.warn("jQuery not yet defined");
		if (document.getElementsByTagName('body')[0].childElementCount == 1) {
			// output only contains debug
			loadScript(jQuerySrc);
		}
		checkInterval = setInterval(function() {
			intervalCounter++;
			if (window.jQuery) {
				clearInterval(checkInterval);
				$ = window.jQuery;
				init();
			} else if (intervalCounter === 10 && !loading) {
				loadScript(jQuerySrc);
			} else if (intervalCounter === 20) {
				clearInterval(checkInterval);
			}
		}, 500);
		return;
	}

	init();

	function init() {

		$.fn.debugEnhance = function(method) {
			var $self = this;
			if (method) {
				if (method === "addCss") {
					addCss(arguments[1]);
				} else if (method === "expand") {
					expand($self);
				} else if (method === "collapse") {
					collapse($self);
				} else if (method === "registerListeners") {
					registerListeners($self);
				}
				return;
			}
			this.each(function(){
				var $self = $(this);
				if ($self.hasClass("enhanced")) {
					return;
				}
				if ($self.hasClass("debug")) {
					console.warn('enhancing debug');
					$self.addClass("enhanced");
					addCss(".debug");
					addPersistOption($self);
					addExpandAll($self);
					enhanceSummary($self);
					registerListeners($self);
					// only enhance root log entries
					// enhance collapsed/hidden entries when expanded
					enhanceEntries($self.find("> .debug-content"));
					// expandErrors($self); // handled by enhanceGroupHeader()
					$self.find(".loading").hide();
				} else {
					enhanceEntry($self);
				}
			});
			return this;
		};

		$(function() {
			$("<link/>", { rel: "stylesheet", href: fontAwesomeCss }).appendTo("head");
			$(".debug").debugEnhance();
		});
	}

	/**
	 * add font-awsome icons
	 *
	 * @todo find a way to add icons "on demand" / only where visible
	 */
	function addIcons($root, types) {
		if (!$.isArray(types)) {
			types = typeof types === "undefined" ?
				["misc"] :
				[types];
		}
		if ($.inArray("misc", types) >= 0) {
			$.each(iconsMisc, function(selector,v){
				$root.find(selector).each(function(){
					$(this).prepend(v);
				});
			});
		}
		if ($.inArray("object", types) >= 0) {
			$.each(iconsObject, function(selector,v){
				$root.find(selector).each(function(){
					$(this).prepend(v);
				});
			});
		}
		if ($.inArray("methods", types) >= 0) {
			$.each(iconsMethods, function(selector,v){
				if ($root.is(selector)) {
					$root.prepend(v);
					return false;
				}
			});
		}
	}

	function groupErrorIconChange($toggle) {
		var selector = ".fa-times-circle, .fa-warning",
			$target= $toggle.next(),
			icon = groupErrorIconGet($target),
			isExpanded = $toggle.hasClass(".expanded");
		$toggle.removeClass("empty");
		if (icon) {
			if ($toggle.find(selector).length) {
				$toggle.find(selector).replaceWith(icon);
			} else {
				$toggle.append(icon);
			}
			if (!isExpanded) {
				toggleIconChange($toggle, classes.expand);
			}
		} else {
			$toggle.find(selector).remove();
			if ($target.children().not(".m_warn, .m_error").length < 1) {
				// group only contains errors & they're now hidden
				$toggle.addClass("empty");
				toggleIconChange($toggle, classes.empty);
			}
		}
	}

	function groupErrorIconGet($container) {
		var icon = "";
		if ($container.find(".m_error").not(".hide").length) {
			icon = iconsMethods[".m_error"];
		} else if ($container.find(".m_warn").not(".hide").length) {
			icon = iconsMethods[".m_warn"];
		}
		return icon;
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
		if ($toggle.is("[data-toggle=array]")) {
			// show and use the "expand it" toggle as reference toggle
			$toggle = $toggle.closest(".t_array").prev().show();
			$toggle.next().hide();
		} else {
			if ($toggle.is("[data-toggle=group]")) {
				groupErrorIconChange($toggle);
			}
			$toggle.removeClass("expanded");
			if (immediate) {
				$toggle.next().hide();
				toggleIconChange($toggle, classes.expand);
			} else {
				$toggle.next().slideUp("fast", function() {
					toggleIconChange($toggle, classes.expand);
				});
			}
		}
	}

	function enhanceArrays($root) {
		// console.info("enhanceArrays", $root);
		var isDirect = $root.hasClass("t_array");
		$root.find(".t_array").each( function() {
			var $self = $(this),
				$expander = $('<span class="t_array-expand" data-toggle="array">' +
						'<span class="t_keyword">Array</span><span class="t_punct">(</span>' +
						'<i class="fa ' + classes.expand + '"></i>&middot;&middot;&middot;' +
						'<span class="t_punct">)</span>' +
					"</span>"),
				$parents = $self.parentsUntil($root, ".t_object, .t_array"),
				isHidden = isDirect ?
					$parents.length > 0 :
					$parents.length > 1;
			if (isHidden || $self.hasClass("enhanced")) {
				return;
			}
			// console.info("enhancing array", $self);
			$self.addClass("enhanced");
			if ( $.trim( $self.find(".array-inner").html() ).length < 1 ) {
				// empty array -> don't add expand/collapse
				$self.find("br").hide();
				$self.find(".array-inner").hide();
				return;
			}
			// add collapse link
			$self.find(".t_keyword").first().
				wrap('<span class="t_array-collapse expanded" data-toggle="array">').
				after('<span class="t_punct">(</span> <i class="fa ' + classes.collapse + '"></i>').
				parent().next().remove();	// remove original "("
			if ( !isDirect && $self.parents(".t_array, .t_object").length < 1 ) {
				// outermost array -> leave open
				$self.before($expander.hide());
			} else {
				$self.hide().before($expander);
			}
		});
	}

	function enhanceEntry($root) {
		console.log("enhanceEntry", $root);
		if ($root.hasClass("group-header")) {
			// minimal enhancement... just adds data-toggle attr and hides target
			// target will not be enhanced until expanded
			addIcons($root, ["methods"]);
			enhanceGroupHeader($root);
		} else if ($root.hasClass("m_group")) {
			/*
				group contents will get enhanced when expanded
			*/
			console.warn("enhanceEntry() m_group?!?!");
		} else {
			// console.log("enhance", $root);
			$root.addClass("enhanced");
			enhanceArrays($root);
			enhanceObjects($root);
			addIcons($root, ["misc","methods"]);
		}
	}

	function enhanceEntries($root) {
		// console.log("enhanceEntries", $root);
		$root.hide();
		$root.children().not(".m_group").each(function(){
			enhanceEntry($(this));
		});
		$root.show();
	}

	function enhanceGroupHeader($toggle) {
		var $target = $toggle.next();
		// console.warn('enhanceGroupHeader');
		$toggle.attr("data-toggle", "group");
		$toggle.removeClass("collapsed"); // collapsed class is never used
		if ($.trim($target.html()).length < 1) {
			$toggle.addClass("empty");
			toggleIconChange($toggle, classes.empty);
			return;
		}
		if ($toggle.hasClass("expanded") || $target.find(".m_error, .m_warn").not(".hide").length) {
			expand($toggle);
		} else {
			collapse($toggle, true);
		}
	}

	/**
	 * Adds toggle icon & hides target
	 * Minimal DOM manipulation -> apply to all descendants
	 */
	function enhanceObjects($root) {
		$root.find(".t_object-class").each( function() {
			var $toggle = $(this),
				$target = $toggle.next();
			if ($toggle.hasClass("enhanced")) {
				return;
			}
			$toggle.addClass("enhanced");
			if ($target.is(".t_recursion, .excluded")) {
				$toggle.addClass("empty");
				return;
			}
			$toggle.append(' <i class="fa ' + classes.expand + '"></i>');
			$toggle.attr("data-toggle", "object");
			$target.hide();
		});
	}

	function enhanceObjectInner($inner)
	{
		// console.info("enhanceObjectInner", $inner);
		var $wrapper = $inner.parent(),
			hasProtected = $inner.children(".visibility-protected").length > 0,
			hasPrivate = $inner.children(".visibility-private").length > 0,
			accessible = $wrapper.data("accessible"),
			toggleClass = accessible === "public" ?
				"toggle-off" :
				"toggle-on",
			toggleVerb = accessible === "public" ?
				"show" :
				"hide",
			visToggles = "",
			hiddenInterfaces = [];
		if ($inner.find(".method[data-implements]").hide().length) {
			// linkify visibility
			$inner.find(".method[data-implements]").each( function() {
				var iface = $(this).data("implements");
				if (hiddenInterfaces.indexOf(iface) < 0) {
					hiddenInterfaces.push(iface);
				}
			});
			$.each(hiddenInterfaces, function(i, iface) {
				$inner.find(".interface").each(function(){
					var html = '<span class="toggle-off" data-toggle="interface" data-interface="'+iface+'" title="toggle methods">' +
							'<i class="fa fa-eye-slash"></i> ' + iface + "</span>";
					if ($(this).text() == iface) {
						$(this).html(html);
					}
				});
			});
		}
		if (accessible === "public") {
			$wrapper.find(".visibility-private, .visibility-protected").hide();
		}
		if (hasProtected) {
			visToggles += ' <span class="toggle-protected '+toggleClass+'">' + toggleVerb + " protected</span>";
		}
		if (hasPrivate) {
			visToggles += ' <span class="toggle-private '+toggleClass+'">' + toggleVerb + " private</span>";
		}
		$inner.prepend('<span class="vis-toggles">' + visToggles + "</span>");
		enhanceArrays($inner);
		addIcons($inner, ["object"]);
	}

	function enhanceSummary($root) {
		console.log("enhanceSummary");
		$root.find(".alert [class*=error-]").each( function() {
			var html = $(this).html(),
				htmlNew = '<label><input type="checkbox" checked data-toggle="error"/> ' + html + "</label>";
			$(this).html(htmlNew);
		});
	}

	function expand($toggle) {
		var $target = $toggle.next();
		if (!$target.hasClass("enhanced")) {
			$target.addClass("enhanced");
			if ($toggle.is("[data-toggle=group]")) {
				// console.log("enhancing group");
				// should currently be hidden..
				enhanceEntries($target);
			} else if ($toggle.is("[data-toggle=object]")) {
				// console.log("enhancing object");
				enhanceObjectInner($target);
			} else {
				// console.log("enhancing array", $toggle);
				// enhanceArrays($target);
			}
		}
		if ($toggle.is("[data-toggle=array]")) {
			// hide the toggle..  there is a different toggle in the expanded version
			enhanceArrays($target);
			$toggle.hide();
			$target.show();
		} else {
			$target.slideDown("fast", function(){
				$toggle.addClass("expanded");
				toggleIconChange($toggle, classes.collapse);
			});
		}
	}

	/*
	function expandErrors($root) {
		$root.find(".m_error, .m_warn").not(".hide").parents(".m_group").prev().not(".expanded").debugEnhance("expand");
	}
	*/

	function toggleCollapse(toggle) {
		var $toggle = $(toggle);
		if ($toggle.hasClass("empty")) {
			return;
		}
		if ($toggle.hasClass("expanded")) {
			$toggle.debugEnhance("collapse");
		} else {
			$toggle.debugEnhance("expand");
		}
	}

	function toggleIconChange($toggle, classNameNew) {
		var $icon = $toggle.children("i").eq(0);
		$icon.addClass(classNameNew);
		$.each(classes, function(i,className) {
			if (className !== classNameNew) {
				$icon.removeClass(className);
			}
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
		var vis = $(toggle).hasClass("toggle-protected") ? "protected" : "private",
			$toggles = $(toggle).closest(".t_object").find(".toggle-"+vis),
			$icon = $(toggle).find("i"),
			iconTag = $icon.length ? $icon[0].outerHTML : "";
		if ($(toggle).hasClass("toggle-off")) {
			// show for this and all descendants
			$toggles.
				html(iconTag + "hide "+vis).
				addClass("toggle-on").
				removeClass("toggle-off");
			$(toggle).closest(".t_object").find(".visibility-"+vis).show();
		} else {
			// hide for this and all descendants
			$toggles.
				html(iconTag + "show "+vis).
				addClass("toggle-off").
				removeClass("toggle-on");
			$(toggle).closest(".t_object").find(".visibility-"+vis).hide();
		}
	}

	/*
		Initialization type Functions
	*/

	/**
	 * Adds CSS to head of page
	 */
	function addCss(scope) {
		var css = ""+
			".debug .debug-cookie { color: #666; }" +
			//".debug .debug-cookie input { vertical-align:sub; }" +
			".debug i.fa, .debug .m_assert i { margin-right:.33em; }" +
			".debug i.fa-plus-circle { opacity:0.42; }" +
			".debug i.fa-calendar { font-size:1.1em; }" +
			".debug i.fa-eye { color:#00529b; font-size:1.1em; border-bottom:0; }" +
			".debug i.fa-eye[title] { border-bottom:inherit; }" +
			".debug i.fa-lg { font-size:1.33em; }" +
			".debug .group-header.expanded i.fa-warning, .debug .group-header.expanded i.fa-times-circle { display:none; }" +
			".debug .group-header i.fa-warning { color:#cdcb06; margin-left:.33em}" +		// warning
			".debug .group-header i.fa-times-circle { color:#D8000C; margin-left:.33em;}" +	// error
			".debug a.expand-all { font-size:1.25em; color:inherit; text-decoration:none; }" +
			// ".debug .t_array-collapse," +
				// ".debug .t_array-expand," +
				// ".debug .group-header," +
				// ".debug .t_object-class," +
			".debug [data-toggle]," +
				".debug [data-toggle][title]," +	// override .debug[title]
				".debug .vis-toggles span { cursor:pointer; }" +
			".debug .group-header.empty, .debug .t_object-class.empty { cursor:auto; }" +
			".debug .vis-toggles span:hover," +
				".debug [data-toggle=interface]:hover { background-color:rgba(0,0,0,0.1); }" +
			".debug .vis-toggles .toggle-off," +
				".debug .interface .toggle-off { opacity:0.42 }" +
			//".debug .t_array-collapse i.fa, .debug .t_array-expand i.fa, .debug .t_object-class i.fa { font-size:inherit; }"+
			"";
		if ( scope ) {
			css = css.replace(new RegExp(/\.debug\s/g), scope+" ");
		}
		$("<style>" + css + "</style>").appendTo("head");
	}

	function addExpandAll($root) {
		// console.log("addExpandAll");
		var $expand_all = $("<a>", {
				"href":"#"
			}).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups').addClass("expand-all");
		if ( $root.find(".group-header").length ) {
			$expand_all.on("click", function() {
				$root.find(".group-header").not(".expanded").each( function() {
					// if ( !$(this).nextAll(".m_group").eq(0).is(":visible") ) {
					// }
					toggleCollapse(this);
				});
				return false;
			});
			$root.find(".debug-content").before($expand_all);
		}
	}

	function addPersistOption($root) {
		var $node;
		if (debugKey) {
			$node = $('<label class="debug-cookie"><input type="checkbox"> Keep debug on</label>').css({"float":"right"});
			if (cookieGet("debug") === debugKey) {
				$node.find("input").prop("checked", true);
			}
			$("input", $node).on("change", function() {
				var checked = $(this).is(":checked");
				console.log("debug persist checkbox changed", checked);
				if (checked) {
					console.log("debugKey", debugKey);
					cookieSave("debug", debugKey, 7);
				} else {
					cookieRemove("debug");
				}
			});
			$root.find(".debug-header").eq(0).prepend($node);
		}
	}

	function loadScript(src) {
		var jsNode = document.createElement("script"),
			first = document.getElementsByTagName("script")[0];
		loading = true;
		jsNode.src = src;
		first.parentNode.insertBefore(jsNode, first);
	}

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
		console.warn('registerListeners');
		$root.on("click", "[data-toggle=array]", function(){
			toggleCollapse(this);
			return false;
		});
		$root.on("click", "[data-toggle=group]", function(){
			toggleCollapse(this);
			return false;
		});
		$root.on("click", "[data-toggle=object]", function(){
			toggleCollapse(this);
			return false;
		});
		$root.on("click", ".toggle-protected, .toggle-private", function(){
			toggleObjectVis(this);
			return false;
		});
		$root.on("click", "[data-toggle=interface]", function(){
			toggleInterfaceVis(this);
			return false;
		});
		$root.on("click", ".alert-dismissible .close", function(){
			$(this).parent().remove();
		});

		$root.on("change", "input[data-toggle=error]", function(){
			var className = $(this).closest("h3").attr("class");
			if ( $(this).is(":checked") ) {
				$root.find(".debug-content ." + className).show().removeClass("hide");
			} else {
				$root.find(".debug-content ." + className).hide().addClass("hide");
			}
			// update icon for all groups having nested error
			// groups containing only hidden erros will loose +/-
			$root.find(".m_error, .m_warn").parents(".m_group").prev().each(function(){
				groupErrorIconChange($(this));
			});
		});
	}

	/*
		Utils
	*/

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
		console.log("cookieSave", name, value, days);
		var expires = "",
			date = new Date();
		if ( days ) {
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toGMTString();
		}
		document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
	}

	function queryDecode(qs) {
		var params = {},
			tokens,
			re = /[?&]?([^&=]+)=?([^&]*)/g;
		if ( qs === undefined ) {
			qs = document.location.search;
		}
		qs = qs.split("+").join(" ");	// replace + with " "
		while ( true ) {
			tokens = re.exec(qs);
			if ( !tokens ) {
				break;
			}
			params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
		}
		return params;
	}

}( window.jQuery || undefined ));