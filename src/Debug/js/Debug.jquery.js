(function ($) {
	'use strict';

	$ = $ && $.hasOwnProperty('default') ? $['default'] : $;

	var config;

	function init($delegateNode) {
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
	function enhance($node) {
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

	function enhanceInner($node) {
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
		rows = Array.prototype.slice.call(rows, 0); // Converts HTMLCollection to Array
		rows = rows.sort(function (trA, trB) {
			var a = trA.cells[col].textContent.trim(),
				b = trB.cells[col].textContent.trim(),
				afloat = a.match(floatRe),
				bfloat = b.match(floatRe),
				comp = 0;
			if (afloat) {
				a = Number.parseFloat(a);
				if (afloat[2]) {
					// sci notation
					a = a.toFixed(6);
				}
			}
			if (bfloat) {
				b = Number.parseFloat(b);
				if (bfloat[2]) {
					// sci notation
					b = b.toFixed(6);
				}
			}
			if (afloat && bfloat) {
				if (a < b) {
					comp = -1;
				} else if (a > b) {
					comp = 1;
				}
				return dir * comp;
			}
			comp = collator
				? collator.compare(a, b)
				: a.localeCompare(b);	// not a natural sort
			return dir * comp;
		});
		for (i = 0; i < rows.length; ++i) {
			body.appendChild(rows[i]); // append each row in order (which moves)
		}
	}

	var config$1,
		expandStack = [],
		strings = [];

	function init$1($root) {
		config$1 = $root.data("config").get();
		init($root);
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
		$root.on("config.debug.updated", function(e, changedOpt){
			e.stopPropagation();
			if (changedOpt == "linkFilesTemplate") {
				updateFileLinks($root);
			}
		});
		$root.on("expand.debug.array", function(e){
			var $node = $(e.target),
				$entry = $node.closest("li[class*=m_]");
			e.stopPropagation();
			expandStack.push($node);
			$node.find("> .array-inner > .key-value > :last-child").each(function() {
				enhanceValue($entry, this);
			});
		});
		$root.on("expand.debug.group", function(e){
			var $node = $(e.target);
			e.stopPropagation();
			enhanceEntries($node);
		});
		$root.on("expand.debug.object", function(e){
			var $node = $(e.target),
				$entry = $node.closest("li[class*=m_]");
			e.stopPropagation();
			if ($node.is(".enhanced")) {
				return;
			}
			expandStack.push($node);
			$node.find("> .constant > :last-child,\
			> .property > :last-child,\
			> .method .t_string").each(function(){
					enhanceValue($entry, this);
				});
			enhanceInner($node);
		});
		$root.on("expanded.debug.array expanded.debug.group expanded.debug.object", function(e){
			var i, count;
			expandStack.pop();
			if (expandStack.length == 0) {
				// now that everything relevant's been expanded we can enhanceLongString
				for (i = 0, count = strings.length; i < count; i++) {
					enhanceLongString(strings[i]);
				}
				strings = [];
			}
		});
	}

	/**
	 * add font-awsome icons
	 */
	function addIcons$1($root) {
		var $caption, $icon, $node, selector;
		for (selector in config$1.iconsMisc) {
			$node = $root.find(selector);
			if ($node.length) {
				$icon = $(config$1.iconsMisc[selector]);
				if ($node.find("> i:first-child").hasClass($icon.attr("class"))) {
					// already have icon
					$icon = null;
					continue;
				}
				$node.prepend($icon);
				$icon = null;
			}
		}	if ($root.data("icon")) {
			$icon = $("<i>").addClass($root.data("icon"));
		} else {
			for (selector in config$1.iconsMethods) {
				if ($root.is(selector)) {
					$icon = $(config$1.iconsMethods[selector]);
					break;
				}
			}
		}
		if ($icon) {
			if ($root.is(".m_group")) {
				// custom icon..   add to .group-label
				$root = $root.find("> .group-header .group-label").eq(0);
			} else if ($root.find("> table").length) {
				// table... we'll prepend icon to caption
				$caption = $root.find("> table > caption");
				if (!$caption.length) {
					$caption = $("<caption>");
					$root.find("> table").prepend($caption);
				}
				$root = $caption;
			}
			if ($root.find("> i:first-child").hasClass($icon.attr("class"))) {
				// already have icon
				return;
			}
			$root.prepend($icon);
		}
	}

	function buildFileLink(file, line) {
		var data = {
			file: file,
			line: line||1
		};
		return config$1.linkFilesTemplate.replace(
			/%(\w*)\b/g,
			function(m, key){
				return data.hasOwnProperty(key)
					? data[ key ]
					: "";
			}
		);
	}

	/**
	 * Create text editor links for error, warn, & trace
	 */
	function createFileLinks($entry, $strings, remove) {
		var detectFiles = $entry.data("detectFiles") === true,
			dataFoundFiles = $entry.data("foundFiles") || [],
			isUpdate = false;
		if (!config$1.linkFiles && !remove) {
			return;
		}
		if (detectFiles === false) {
			return;
		}
		// console.info("createFileLinks", detectFiles, $entry);
		if ($entry.is(".m_trace")) {
			isUpdate = $entry.find(".file-link").length > 0;
			if (!isUpdate) {
				$entry.find("table thead tr > *:last-child").after("<th></th>");
			} else if (remove) {
				$entry.find("table tr > *:last-child").remove();
				return;
			}
			$entry.find("table tbody tr").each(function(){
				var $tr = $(this),
					$tds = $tr.find("> td"),
					$a = $('<a>', {
						class: "file-link",
						href: buildFileLink($tds.eq(0).text(), $tds.eq(1).text()),
						html: '<i class="fa fa-fw fa-external-link"></i>',
						style: "vertical-align: bottom",
						title: "Open in editor"
					});
				if (isUpdate) {
					$tr.find(".file-link").replaceWith($a);
				} else {
					$tds.last().after($("<td/>", {
						class: "text-center",
						html: $a
					}));
				}
			});
			return;
		}
		// don't remove data... link template may change
		// $entry.removeData("detectFiles foundFiles");
		if ($entry.is("[data-file]")) {
			/*
				Log entry link
			*/
			$entry.find("> .file-link").remove();
			if (!remove) {
				$entry.append($('<a>', {
					html: '<i class="fa fa-external-link"></i>',
					href: buildFileLink($entry.data("file"), $entry.data("line")),
					title: "Open in editor",
					class: "file-link lpad"
				})[0].outerHTML);
			}
			return;
		}
		if (!$strings) {
			$strings = [];
		}
		$.each($strings, function(){
			// console.log('string', $(this).text());
			var $replace,
				$string = $(this),
				attr = $string[0].attributes,
				text = $.trim($string.text()),
				matches = [];
			if ($string.closest(".m_trace").length) {
				createFileLinks($string.closest(".m_trace"));
				return false;
			}
			if ($string.data("file")) {
				// filepath specified in data attr
				matches = [null, $string.data("file"), $string.data("line") || 1];
			} else if (dataFoundFiles.indexOf(text) === 0 || $string.is(".file")) {
				matches = [null, text, 1];
			} else {
				matches = text.match(/^(\/.+\.php)(?: \(line (\d+)\))?$/) || [];
			}
			if (matches.length) {
				$replace = remove
					? $("<span>", {
							html: text,
						})
					: $('<a>', {
							html: text + ' <i class="fa fa-external-link"></i>',
							href: buildFileLink(matches[1], matches[2]),
							title: "Open in editor"
						});
				if ($string.is("td")) {
					$string.html(remove
						? text
						: $replace
					);
				} else {
					/*
						attr is not a plain object, but an array of attribute nodes
						which contain both the name and value
					*/
					$.each(attr, function(){
						var name = this.name;
						if (["html","href","title"].indexOf(name) > -1) {
							return;	// continue;
						}
						$replace.attr(name, this.value);
					});
					$string.replaceWith($replace);
				}
				if (!remove) {
					$replace.addClass("file-link");
				}
			}
		});
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
					'<i class="fa ' + config$1.iconsExpand.expand + '"></i>&middot;&middot;&middot; ' +
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
			after('<span class="t_punct">(</span> <i class="fa ' + config$1.iconsExpand.collapse + '"></i>').
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
	 * Enhance log entries
	 */
	function enhanceEntries($node) {
		// console.warn('enhanceEntries', $node[0]);
		var $prev = $node.prev(),
			show = !$prev.hasClass("group-header") || $prev.hasClass("expanded");
		expandStack.push($node);
		// temporarily hide when enhancing... minimize redraws
		$node.hide();
		$node.children().each(function() {
			enhanceEntry($(this));
		});
		if (show) {
			$node.show();
		}
		$node.addClass("enhanced");
		$node.trigger("expanded.debug.group");
	}

	/**
	 * Enhance a single log entry
	 * we don't enhance strings by default (add showmore).. needs to be visible to calc height
	 */
	function enhanceEntry($entry) {
		if ($entry.is(".enhanced")) {
			return;
		}
		// console.log('enhanceEntry', $entry[0]);
		if ($entry.is(".m_group")) {
			enhanceGroup($entry);
		} else if ($entry.is(".m_trace")) {
			createFileLinks($entry);
			addIcons$1($entry);
		} else {
			// regular log-type entry
			if ($entry.data('file')) {
				if (!$entry.attr("title")) {
					$entry.attr("title", $entry.data("file") + ": line " + $entry.data("line"));
				}
				createFileLinks($entry);
			}
			$entry.children().each(function() {
				enhanceValue($entry, this);
			});
			addIcons$1($entry);
		}
		$entry.addClass("enhanced");
		$entry.trigger("enhanced.debug");
	}

	function enhanceGroup($group) {
		var $toggle = $group.find("> .group-header"),
			$target = $toggle.next();
		addIcons$1($group);
		addIcons$1($toggle);
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

	function enhanceLongString($node) {
		var $container,
			$stringWrap,
			height = $node.height(),
			diff = height - 70;
		if (diff > 35) {
			$stringWrap = $node.wrap('<div class="show-more-wrapper"></div>').parent();
			$stringWrap.append('<div class="show-more-fade"></div>');
			$container = $stringWrap.wrap('<div class="show-more-container"></div>').parent();
			$container.append('<button type="button" class="show-more"><i class="fa fa-caret-down"></i> More</button>');
			$container.append('<button type="button" class="show-less" style="display:none;"><i class="fa fa-caret-up"></i> Less</button>');
		}
	}

	function enhanceValue($entry, node) {
		var $node = $(node);
		if ($node.is(".t_array")) {
			enhanceArray($node);
		} else if ($node.is(".t_object")) {
			enhance($node);
		} else if ($node.is("table")) {
			makeSortable($node);
		} else if ($node.is(".t_string")) {
			strings.push($node);
			createFileLinks($entry, $node);
		}
		if ($node.is(".timestamp")) {
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
			if ($node.is(".no-quotes")) {
				$span.addClass("no-quotes");
			}
			$node.removeClass("t_float t_int t_string numeric no-quotes");
			$node.html($i).append($span);
		}
	}

	/**
	 * Linkify files if not already done or update already linked files
	 */
	function updateFileLinks($group) {
		var remove = !config$1.linkFiles || config$1.linkFilesTemplate.length == 0;
		$group.find("li[data-detect-files]").each(function(){
			createFileLinks($(this), $(this).find(".t_string"), remove);
		});
	}

	var $root, config$2, origH, origPageY;

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
	};

	function init$2($debugRoot) {
		$root = $debugRoot;
		config$2 = $root.data("config");
		if (!config$2.get("drawer")) {
			return;
		}

		$root.addClass("debug-drawer");

		addMarkup();

		$root.find(".debug-body").scrollLock();
		$root.find(".debug-resize-handle").on("mousedown", onMousedown);
		$root.find(".debug-pull-tab").on("click", open);
		$root.find(".debug-menu-bar .close").on("click", close);

		if (config$2.get("persistDrawer") && config$2.get("openDrawer")) {
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
		if (config$2.get("persistDrawer")) {
			config$2.set("openDrawer", true);
		}
	}

	function close() {
		$root.removeClass("debug-drawer-open");
		$("body").css("marginBottom", "");
		$(window).off("resize", setHeight);
		if (config$2.get("persistDrawer")) {
			config$2.set("openDrawer", false);
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
			if (!height && config$2.get("persistDrawer")) {
				height = config$2.get("height");
			}
			if (!height) {
				height = 100;
			}
		}
		height = Math.min(height, maxH);
		height = Math.max(height, minH);
		$body.css("height", height);
		if (viaUser && config$2.get("persistDrawer")) {
			config$2.set("height", height);
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

	function init$3($delegateNode) {

		$delegateNode.on("change", "input[type=checkbox]", function() {
			var $this = $(this),
				isChecked = $this.is(":checked"),
				$nested = $this.closest("label").next("ul").find("input"),
				$root = $this.closest(".debug");
			if ($this.data("toggle") == "error") {
				// filtered separately
				return;
			}
			$nested.prop("checked", isChecked);
			applyFilter($root);
			updateFilterStatus($root);
		});

		$delegateNode.on("change", "input[data-toggle=error]", function() {
			var $this = $(this),
				$root = $this.closest(".debug"),
				errorClass = $this.val(),
				isChecked = $this.is(":checked"),
				selector = ".group-body .error-" + errorClass;
			$root.find(selector).toggleClass("filter-hidden", !isChecked);
			// trigger collapse to potentially update group icon
			$root.find(".m_error, .m_warn").parents(".m_group").find(".group-body")
				.trigger("collapsed.debug.group");
			updateFilterStatus($root);
		});

		$delegateNode.on("channelAdded.debug", function(e) {
			var $root = $(e.target).closest(".debug");
			updateFilterStatus($root);
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
		// :not(.level-error, .level-info, .level-warn)
		$root.find("> .debug-body .m_alert, .group-body > *:not(.m_groupSummary)").each(function(){
			var $node = $(this),
				show = true,
				unhiding = false;
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
			unhiding = show && $node.is(".filter-hidden");
			$node.toggleClass("filter-hidden", !show);
			if (unhiding && $node.is(":visible")) {
				$node.debugEnhance();
			}
		});
		$root.find(".m_group.filter-hidden > .group-header:not(.expanded) + .group-body").debugEnhance();
	}

	function updateFilterStatus($debugRoot) {
		var haveUnchecked = $debugRoot.find(".debug-sidebar input:checkbox:not(:checked)").length > 0;
		$debugRoot.toggleClass("filter-active", haveUnchecked);
	}

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
		cookieSet(name, "", -1);
	}

	function cookieSet(name, value, days) {
		// console.log("cookieSet", name, value, days);
		var expires = "",
			date = new Date();
		if ( days ) {
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toGMTString();
		}
		document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
	}

	function lsGet(key) {
		var path = key.split(".", 2);
	    var val = window.localStorage.getItem(path[0]);
	    if (typeof val !== "string" || val.length < 1) {
	        return null;
	    } else {
	        try {
	            val = JSON.parse(val);
	        } catch (e) {
	        }
	    }
		return path.length > 1
			? val[path[1]]
			: val;
	}

	function lsSet(key, val) {
		var path = key.split(".", 2);
		var lsVal;
		key = path[0];
		if (path.length > 1) {
			lsVal = lsGet(key) || {};
			lsVal[path[1]] = val;
			val = lsVal;
		}
	    if (val === null) {
	        localStorage.removeItem(key);
	        return;
	    }
	    if (typeof val !== "string") {
	        val = JSON.stringify(val);
	    }
		window.localStorage.setItem(key, val);
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

	var $root$1, config$3;

	var KEYCODE_ESC = 27;

	function init$4($debugRoot) {
		$root$1 = $debugRoot;
		config$3 = $root$1.data("config");

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
				cookieSet("debug", config$3.get("debugKey"), 7);
			} else {
				cookieRemove("debug");
			}
		}).prop("checked", config$3.get("debugKey") && cookieGet("debug") === config$3.get("debugKey"));
		if (!config$3.get("debugKey")) {
			$("input[name=debugCookie]").prop("disabled", true)
				.closest("label").addClass("disabled");
		}

		$("input[name=persistDrawer]").on("change", function(){
			var isChecked = $(this).is(":checked");
			// options.persistDrawer = isChecked;
			config$3.set({
				persistDrawer: isChecked,
				openDrawer: isChecked,
				openSidebar: true
			});
		}).prop("checked", config$3.get("persistDrawer"));

		$("input[name=linkFiles]").on("change", function(){
			var isChecked = $(this).prop("checked"),
				$formGroup = $("#linkFilesTemplate").closest(".form-group");
			isChecked
				? $formGroup.slideDown()
				: $formGroup.slideUp();
			config$3.set("linkFiles", isChecked);
			$("input[name=linkFilesTemplate]").trigger("change");
		}).prop("checked", config$3.get("linkFiles")).trigger("change");

		$("input[name=linkFilesTemplate]").on("change", function(){
			var val = $(this).val();
			config$3.set("linkFilesTemplate", val);
			$debugRoot.trigger("config.debug.updated", "linkFilesTemplate");
		}).val(config$3.get("linkFilesTemplate"));
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
				<label><input type="checkbox" name="linkFiles" /> Create file links</label>\
				<div class="form-group">\
					<label for="linkFilesTemplate">Link Template</label>\
					<input name="linkFilesTemplate" id="linkFilesTemplate" />\
				</div>\
				<hr class="dropdown-divider" />\
				<a href="http://www.bradkent.com/php/debug" target="_blank">Documentation</a>\
			</div>\
		</div>');
		if (!config$3.get("drawer")) {
			$menuBar.find("input[name=persistDrawer]").closest("label").remove();
		}
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

	var config$4;
	var methods;	// method filters
	var $root$2;
	var initialized = false;

	function init$5($debugRoot) {
		config$4 = $debugRoot.data("config");
		$root$2 = $debugRoot;

		// console.warn('sidebar.init');

		if (config$4.get("sidebar")) {
			addMarkup$1($root$2);
		}

		if (config$4.get("persistDrawer") && !config$4.get("openSidebar")) {
			close$2($root$2);
		}

		$root$2.on("click", ".close[data-dismiss=alert]", function() {
			// setTimeout -> new thread -> executed after event bubbled
			var $debug = $(this).closest(".debug");
			setTimeout(function(){
				if ($debug.find(".m_alert").length) {
					$debug.find(".debug-sidebar input[data-toggle=method][value=alert]").parent().addClass("disabled");
				}
			});
		});

		$root$2.on("click", ".sidebar-toggle", function() {
			var $debug = $(this).closest(".debug"),
				isVis = $debug.find(".debug-sidebar").is(".show");
			if (!isVis) {
				open$2($debug);
			} else {
				close$2($debug);
			}
		});

		$root$2.on("change", ".debug-sidebar input[type=checkbox]", function(e) {
			var $input = $(this),
				$toggle = $input.closest(".toggle"),
				$nested = $toggle.next("ul").find(".toggle"),
				isActive = $input.is(":checked"),
				$errorSummary = $(".m_alert.error-summary.have-fatal");
			$toggle.toggleClass("active", isActive);
			$nested.toggleClass("active", isActive);
			if ($input.val() == "fatal") {
				$errorSummary.find(".error-fatal").toggleClass("filter-hidden", !isActive);
				$errorSummary.toggleClass("filter-hidden", $errorSummary.children().not(".filter-hidden").length == 0);
			}
		});

		if (initialized) {
			return;
		}

		addPreFilter(function($delegateRoot){
			$root$2 = $delegateRoot;
			config$4 = $root$2.data("config") || $("body").data("config");
			methods = [];
			$root$2.find("input[data-toggle=method]:checked").each(function(){
				methods.push($(this).val());
			});
		});

		addTest(function($node){
			var method = $node[0].className.match(/\bm_(\S+)\b/)[1];
			if (!config$4.get("sidebar")) {
				return true;
			}
			if (method == "group" && $node.find("> .group-body")[0].className.match(/level-(error|info|warn)/)) {
				method = $node.find("> .group-body")[0].className.match(/level-(error|info|warn)/)[1];
				$node.toggleClass("filter-hidden-body", methods.indexOf(method) < 0);
			}
			if (["alert","error","warn","info"].indexOf(method) > -1) {
				return methods.indexOf(method) > -1;
			} else {
				return methods.indexOf("other") > -1;
			}
		});

		initialized = true;
	}

	function addMarkup$1($node) {
		var $sidebar = $('<div class="debug-sidebar show no-transition"></div>');
		var $expAll = $node.find(".debug-body > .expand-all");
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
		<div class="sidebar-content">\
			<ul class="list-unstyled debug-filters">\
				<li class="php-errors">\
					<span><i class="fa fa-fw fa-lg fa-code"></i>PHP Errors</span>\
					<ul class="list-unstyled">\
					</ul>\
				</li>\
				<li class="channels">\
					<span><i class="fa fa-fw fa-lg fa-list-ul"></i>Channels</span>\
					<ul class="list-unstyled">\
					</ul>\
				</li>\
			</ul>\
			<button class="expand-all" style="display:none;"><i class="fa fa-lg fa-plus"></i> Exp All Groups</button>\
		</div>\
	');
		$node.find(".debug-body").before($sidebar);

		phpErrorToggles($node);
		moveChannelToggles($node);
		addMethodToggles($node);
		if ($expAll.length) {
			$expAll.remove();
			$sidebar.find(".expand-all").show();
		}
		setTimeout(function(){
			$sidebar.removeClass("no-transition");
		}, 500);
	}

	function close$2($node) {
		$node.find(".debug-sidebar")
			.removeClass("show")
			.attr("style", "")
			.trigger("close.debug.sidebar");
		config$4.set("openSidebar", false);
	}

	function open$2($node) {
		$node.find(".debug-sidebar")
			.addClass("show")
			.trigger("open.debug.sidebar");
		config$4.set("openSidebar", true);
	}

	function addMethodToggles($node) {
		var $filters = $node.find(".debug-filters"),
			$entries = $node.find("> .debug-body .m_alert, .group-body > *"),
			val,
			labels = {
				alert: '<i class="fa fa-fw fa-lg fa-bullhorn"></i>Alerts',
				error: '<i class="fa fa-fw fa-lg fa-times-circle"></i>Error',
				warn: '<i class="fa fa-fw fa-lg fa-warning"></i>Warning',
				info: '<i class="fa fa-fw fa-lg fa-info-circle"></i>Info',
				other: '<i class="fa fa-fw fa-lg fa-sticky-note-o"></i>Other'
			},
			haveEntry;
		for (val in labels) {
			haveEntry = val == "other"
				? $entries.not(".m_alert, .m_error, .m_warn, .m_info").length > 0
				: $entries.filter(".m_"+val).not("[data-channel=phpError]").length > 0;
			$filters.append(
				$('<li />').append(
					$('<label class="toggle active" />').toggleClass("disabled", !haveEntry).append(
						$("<input />", {
							type: "checkbox",
							checked: true,
							"data-toggle": "method",
							value: val
						})
					).append(
						$("<span>").append(
							labels[val]
						)
					)
				)
			);
		}
	}

	/**
	 * grab the .debug-body toggles and move them to sidebar
	 */
	function moveChannelToggles($node) {
		var $togglesSrc = $node.find(".debug-body .channels > ul > li"),
			$togglesDest = $node.find(".debug-sidebar .channels ul");
		$togglesDest.append($togglesSrc);
		if ($togglesDest.children().length === 0) {
			$togglesDest.parent().hide();
		}
		$node.find(".debug-body .channels").remove();
	}

	/**
	 * Grab the error toggles from .debug-body's error-summary move to sidebar
	 */
	function phpErrorToggles($node) {
		var $togglesUl = $node.find(".debug-sidebar .php-errors ul"),
			$errorSummary = $node.closest(".debug").find(".m_alert.error-summary"),
			haveFatal = $errorSummary.hasClass("have-fatal");
		if (haveFatal) {
			$togglesUl.append('<li><label class="toggle active">\
			<input type="checkbox" checked data-toggle="error" value="fatal" />fatal <span class="badge">1</span>\
			</label></li>');
		}
		$errorSummary.find("label").each(function(){
			var $li = $(this).parent(),
				$checkbox = $(this).find("input"),
				val = $checkbox.val();
			$togglesUl.append(
				$("<li>").append(
					$('<label class="toggle active">').html(
						$checkbox[0].outerHTML + val + ' <span class="badge">' + $checkbox.data("count") + "</span>"
					)
				)
			);
			$li.remove();
		});
		$errorSummary.find("ul").filter(function(){
			return $(this).children().length === 0;
		}).remove();
		if ($togglesUl.children().length === 0) {
			$togglesUl.parent().hide();
		}
		if (!haveFatal) {
			$errorSummary.remove();
		} else {
			$errorSummary.find("h3").eq(1).remove();
		}
	}

	/**
	 * Add primary Ui elements
	 */

	var config$5;
	var $root$3;

	function init$6($debugRoot) {
		$root$3 = $debugRoot;
		config$5 = $root$3.data("config").get();
		$root$3.find(".debug-menu-bar").append($('<div />', {class:"pull-right"}));
		addChannelToggles();
		addExpandAll();
		addNoti($("body"));
		enhanceErrorSummary();
		init$2($root$3);
		init$3($root$3);
		init$5($root$3);
		init$4($root$3);
		addErrorIcons();
		$root$3.find(".loading").hide();
		$root$3.addClass("enhanced");
	}

	function addChannelToggles() {
		var channels = $root$3.data("channels"),
			$toggles,
			$ul = buildChannelList(channels, $root$3.data("channelRoot"));
		$toggles = $("<fieldset />", {
				"class": "channels",
			})
			.append('<legend>Channels</legend>')
			.append($ul);
		if ($ul.html().length) {
			$root$3.find(".debug-body").prepend($toggles);
		}
	}

	function addErrorIcons() {
		var counts = {
			error : $root$3.find(".m_error[data-channel=phpError]").length,
			warn : $root$3.find(".m_warn[data-channel=phpError]").length
		};
		var $icon;
		var $icons = $("<span>", {class: "debug-error-counts"});
		// var $badge = $("<span>", {class: "badge"});
		if (counts.error) {
			$icon = $(config$5.iconsMethods[".m_error"]).removeClass("fa-lg").addClass("text-error");
			// $root.find(".debug-pull-tab").append($icon);
			$icons.append($icon).append($("<span>", {
				class: "badge",
				html: counts.error
			}));
		}
		if (counts.warn) {
			$icon = $(config$5.iconsMethods[".m_warn"]).removeClass("fa-lg").addClass("text-warn");
			// $root.find(".debug-pull-tab").append($icon);
			$icons.append($icon).append($("<span>", {
				class: "badge",
				html: counts.warn
			}));
		}
		$root$3.find(".debug-pull-tab").append($icons[0].outerHTML);
		$root$3.find(".debug-menu-bar .pull-right").prepend($icons);
	}

	function addExpandAll() {
		var $expandAll = $("<button>", {
				"class": "expand-all",
			}).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups');
		// this is currently invoked before entries are enhance / empty class not yet added
		if ($root$3.find(".m_group:not(.empty)").length > 1) {
			$root$3.find(".debug-log-summary").before($expandAll);
		}
		$root$3.on("click", ".expand-all", function(){
			$(this).closest(".debug").find(".group-header").not(".expanded").each(function() {
				$(this).debugEnhance('expand');
			});
			return false;
		});
	}

	function addNoti($root) {
		if ($root.find(".debug-noti-wrap").length) {
			return;
		}
		$root.append('<div class="debug-noti-wrap">' +
				'<div class="debug-noti-table">' +
					'<div class="debug-noti"></div>' +
				'</div>' +
			'</div>');
	}

	/*
	function addPersistOption() {
		var $node;
		if (config.debugKey) {
			$node = $('<label class="debug-cookie" title="Add/remove debug cookie"><input type="checkbox"> Keep debug on</label>');
			if (cookieGet("debug") === options.debugKey) {
				$node.find("input").prop("checked", true);
			}
			$("input", $node).on("change", function() {
				var checked = $(this).is(":checked");
				if (checked) {
					cookieSet("debug", options.debugKey, 7);
				} else {
					cookieRemove("debug");
				}
			});
			$root.find(".debug-menu-bar").eq(0).prepend($node);
		}
	}
	*/

	function buildChannelList(channels, channelRoot, checkedChannels, prepend) {
		var $ul = $('<ul class="list-unstyled">'),
			$li,
			$label,
			channel,
			channelName = '',
			isChecked = true;
		prepend = prepend || "";
		if ($.isArray(channels)) {
			channels = channelsToTree(channels);
		}
		for (channelName in channels) {
			if (channelName === "phpError") {
				// phpError is a special channel
				continue;
			}
			channel = channels[channelName];
			isChecked = checkedChannels !== undefined
				? checkedChannels.indexOf(prepend + channelName) > -1
				: channel.options.show;
			$label = $('<label>', {
					"class": "toggle",
				}).append($("<input>", {
					checked: isChecked,
					"data-is-root": channelName == channelRoot,
					"data-toggle": "channel",
					type: "checkbox",
					value: prepend + channelName
				})).append(channelName);
			$label.toggleClass("active", isChecked);
			$li = $("<li>").append($label);
			if (channel.options.icon) {
				$li.find('input').after($('<i>', {"class": channel.options.icon}));
			}
			if (Object.keys(channel.channels).length) {
				$li.append(buildChannelList(channel.channels, channelRoot, checkedChannels, prepend + channelName + "."));
			}
			$ul.append($li);
		}
		return $ul;
	}

	function channelsToTree(channels) {
		var channelTree = {},
			channel,
			ref,
			i, i2,
			path;
		channels = channels.sort(function(a,b){
			return a.name < b.name;
		});
		for (i = 0; i < channels.length; i++) {
			ref = channelTree;
			channel = channels[i];
			path = channel.name.split('.');
			for (i2 = 0; i2 < path.length; i2++) {
				if (!ref[ path[i2] ]) {
					ref[ path[i2] ] = {
						options: {
							icon: i2 == path.length - 1 ? channel.icon : null,
							show: i2 == path.length - 1 ? channel.show : null
						},
						channels: {}
					};
				}
				ref = ref[ path[i2] ].channels;
			}
		}
		return channelTree;
	}

	function enhanceErrorSummary() {
		var $errorSummary = $root$3.find(".m_alert.error-summary");
		$errorSummary.find("h3:first-child").prepend(config$5.iconsMethods[".m_error"]);
		$errorSummary.find("li[class*=error-]").each(function() {
			var category = $(this).attr("class").replace("error-", ""),
				html = $(this).html(),
				htmlReplace = '<li><label>' +
					'<input type="checkbox" checked data-toggle="error" data-count="'+$(this).data("count")+'" value="' + category + '" /> ' +
					html +
					'</label></li>';
			$(this).replaceWith(htmlReplace);
		});
		$errorSummary.find(".m_trace").debugEnhance();
	}

	/**
	 * handle expanding/collapsing arrays, groups, & objects
	 */

	var config$6;

	function init$7($delegateNode) {
		config$6 = $delegateNode.data("config").get();
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
		$delegateNode.on("collapsed.debug.group", function(e){
			// console.warn('collapsed.debug.group');
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
			icon = config$6.iconsExpand.expand;
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
		$target.trigger("collapsed.debug." + what);
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
		$target.trigger("expand.debug." + what);
		if (what === "array") {
			// hide the toggle..  there is a different toggle in the expanded version
			$toggle.hide();
			$target.show();
			$target.trigger("expanded.debug." + what);
		} else {
			$target.slideDown("fast", function() {
				var $groupEndValue = $target.find("> .m_groupEndValue");
				$toggle.addClass("expanded");
				iconUpdate($toggle, config$6.iconsExpand.collapse);
				if ($groupEndValue.length) {
					// remove value from label
					$toggle.find(".group-label").last().nextAll().remove();
				}
				// setTimeout for reasons... ensures listener gets visible target
				setTimeout(function(){
					$target.trigger("expanded.debug." + what);
				});
			});
		}
	}

	function groupErrorIconGet($container) {
		var icon = "";
		if ($container.find(".m_error").not(".filter-hidden").length) {
			icon = config$6.iconsMethods[".m_error"];
		} else if ($container.find(".m_warn").not(".filter-hidden").length) {
			icon = config$6.iconsMethods[".m_warn"];
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
				? config$6.iconsExpand.collapse
				: config$6.iconsExpand.expand
			);
		} else {
			$toggle.find(selector).remove();
			if ($target.children().not(".m_warn, .m_error").length < 1) {
				// group only contains errors & they're now hidden
				$group.addClass("empty");
				iconUpdate($toggle, config$6.iconsExpand.empty);
			}
		}
	}

	function iconUpdate($toggle, classNameNew) {
		var $icon = $toggle.children("i").eq(0);
		if ($toggle.is(".group-header") && $toggle.parent().is(".empty")) {
			classNameNew = config$6.iconsExpand.empty;
		}
		$.each(config$6.iconsExpand, function(i, className) {
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

	function Config(defaults, localStorageKey) {
	    var storedConfig = null;
	    if (defaults.useLocalStorage) {
	        storedConfig = lsGet(localStorageKey);
	    }
	    this.config = $.extend({}, defaults, storedConfig || {});
	    // console.warn('config', JSON.parse(JSON.stringify(this.config)));
	    this.haveSavedConfig = typeof storedConfig === "object";
	    this.localStorageKey = localStorageKey;
	    this.localStorageKeys = ["persistDrawer","openDrawer","openSidebar","height","linkFiles","linkFilesTemplate"];
	}

	Config.prototype.get = function(key) {
	    if (typeof key == "undefined") {
	        return JSON.parse(JSON.stringify(this.config));
	    }
	    return typeof(this.config[key]) !== "undefined"
	        ? this.config[key]
	        : null;
	};

	Config.prototype.set = function(key, val) {
	    var lsObj = {},
	        setVals = {},
	        haveLsKey = false;
	    if (typeof key == "object") {
	        setVals = key;
	    } else {
	        setVals[key] = val;
	    }
	    // console.log('config.set', setVals);
	    for (var k in setVals) {
	        this.config[k] = setVals[k];
	    }
	    if (this.config.useLocalStorage) {
	        lsObj = lsGet(this.localStorageKey) || {};
	        if (setVals.linkFilesTemplateDefault && !lsObj.linkFilesTemplate) {
	            // we don't have a user specified template... use the default
	            this.config.linkFiles = setVals.linkFiles = true;
	            this.config.linkFilesTemplate = setVals.linkFilesTemplate = setVals.linkFilesTemplateDefault;
	        }
	        for (var i = 0, count = this.localStorageKeys.length; i < count; i++) {
	            key = this.localStorageKeys[i];
	            if (typeof setVals[key] !== "undefined") {
	                haveLsKey = true;
	                lsObj[key] = setVals[key];
	            }
	        }
	        if (haveLsKey) {
	            lsSet(this.localStorageKey, lsObj);
	        }
	    }
	    this.haveSavedConfig = true;
	};

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

	var listenersRegistered = false;
	var config$7 = new Config({
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
			src: config$7.get('fontAwesomeCss'),
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
			src: config$7.get('clipboardSrc'),
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
		var $self = this;
		if (method === "sidebar") {
			if (arg1 == "add") {
				addMarkup$1($self);
			} else if (arg1 == "open") {
				open$2($self);
			} else if (arg1 == "close") {
				close$2($self);
			}
		} else if (method === "buildChannelList") {
			return buildChannelList(arg1, arg2, arguments[3]);
		} else if (method === "collapse") {
			collapse($self, arg1);
		} else if (method === "expand") {
			expand($self);
		} else if (method === "init") {
			var conf = new Config(config$7.get(), "phpDebugConsole");
			$self.data("config", conf);
			conf.set($self.eq(0).data("options") || {});
			if (typeof arg1 == "object") {
				conf.set(arg1);
			}
			init$1($self);
			init$7($self);
			registerListeners($self);
			init$6($self);
			if (!conf.get("drawer")) {
				$self.debugEnhance();
			}
		} else if (method == "setConfig") {
			if (typeof arg1 == "object") {
				config$7.set(arg1);
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
						enhanceEntries($self);
					} else {
						// log entry assumed
						// console.warn("debugEnhance() : entry");
						enhanceEntry($self); // true
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
