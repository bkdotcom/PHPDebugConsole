/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups and arrays
 *    Add FontAwesome icons
 */

(function ($) {

	var classExpand = "fa-plus-square-o",
		classCollapse = "fa-minus-square-o",
		classEmpty = "fa-square-o",
		fontAwesomeCss = "//maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css",
		jQuerySrc = "//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js",
		icons = {
			".debug-value" : '<i class="fa fa-eye" title="via __debugInfo()"></i>',
			".expand-all" : '<i class="fa fa-lg fa-plus"></i>',
			".group-header" : '<i class="fa fa-lg '+classCollapse+'"></i>',
			".m_assert" :	'<i class="fa-lg"><b>&ne;</b></i>',
			".m_count" :	'<i class="fa fa-lg fa-plus-circle"></i>',
			".m_error" :	'<i class="fa fa-lg fa-times-circle"></i>',
			".m_info" :		'<i class="fa fa-lg fa-info-circle"></i>',
			".m_warn" :		'<i class="fa fa-lg fa-warning"></i>',
			".m_time" :		'<i class="fa fa-lg fa-clock-o"></i>',
			".timestamp" :	'<i class="fa fa-calendar"></i>',
			".toggle-protected" :	'<i class="fa fa-shield"></i>',
			".toggle-private" :		'<i class="fa fa-user-secret"></i>'
		},
		intervalCounter = 0,
		checkInterval,
		debugKey = getDebugKey();

	if ( !$ ) {
		console.warn('jQuery not yet defined');
		checkInterval = setInterval(function() {
			intervalCounter++;
			if (window.jQuery) {
				clearInterval(checkInterval);
				$ = window.jQuery;
				init();
			} else if (intervalCounter == 10) {
				loadScript(jQuerySrc);
			} else if (intervalCounter == 20) {
				clearInterval(checkInterval);
			}
		}, 500);
		return;
	}

	init();

	function init() {

		$.fn.debugEnhance = function(method) {
			var self = this;
			if ( method ) {
				if ( method === 'addCss' ) {
					addCss(arguments[1]);
				} else if ( method == 'expand' ) {
					self.next().slideDown('fast', function(){
						self.addClass('expanded');
						changeToggleIcon(self, classCollapse);
					});
				} else if ( method == 'collapse' ) {
					self.removeClass('expanded');
					self.next().slideUp('fast', function() {
						changeToggleIcon(self, classExpand);
					});
				}
				return;
			}
			this.each(function(){
				var $self = $(this);
				if ($self.hasClass('enhanced')) {
					console.warn('already enhanced');
					return;
				}
				// console.group('enhancing',this);
				if ($self.hasClass('debug')) {
					addPersistOption($self);
					addExpandAll($self);
					enhanceSummary($self);
				}
				// use a separate/new thread / non-blocking
				setTimeout(function(){
					enhanceListeners($self);
					enhanceArrays($self);
					enhanceGroups($self);
					enhanceObjects($self);
					// enhanceSummary($self);
					// now that everything's been enhanced, class names added, etc
					addIcons($self);
					collapseGroups($self);
					$self.addClass('enhanced');
				});
				// console.groupEnd();
			});
			return this;
		};

		$(function() {
			$('<link/>', { rel: 'stylesheet', href: fontAwesomeCss }).appendTo('head');
			$().debugEnhance('addCss', '.debug');
			$('.debug').debugEnhance();
		});
	}

	function loadScript(src) {
		var jsNode = document.createElement('script'),
			first = document.getElementsByTagName('script')[0];
		jsNode.src = src;
		first.parentNode.insertBefore(jsNode, first);
	}

	/**
	 * Adds CSS to head of page
	 */
	function addCss(scope) {
		console.log('addCss');
		var css = ''+
			'.debug .debug-cookie { color: #666; }'+
			//'.debug .debug-cookie input { vertical-align:sub; }'+
			'.debug i.fa, .debug .m_assert i { margin-right:.33em; }'+
			'.debug i.fa-plus-circle { opacity:0.42; }'+
			'.debug i.fa-calendar { font-size:1.1em; }'+
			'.debug i.fa-eye { color:#00529b; font-size:1.1em; border-bottom:0; }'+
			'.debug i.fa-eye[title] { border-bottom:inherit; }'+
			'.debug i.fa-lg { font-size:1.33em; }'+
			'.debug .group-header.expanded i.fa-warning, .debug .group-header.expanded i.fa-times-circle { display:none; }'+
			'.debug .group-header i.fa-warning { color:#cdcb06; margin-left:.33em}'+		// warning
			'.debug .group-header i.fa-times-circle { color:#D8000C; margin-left:.33em;}'+	// error
			'.debug a.expand-all { font-size:1.25em; color:inherit; text-decoration:none; }'+
			'.debug .t_array-collapse,'+
				'.debug .t_array-expand,'+
				'.debug .group-header,'+
				'.debug .t_object-class,'+
				'.debug .vis-toggles span,'+
				'.debug .interfaces span.toggle-interface { cursor:pointer; }'+
			'.debug .group-header.empty, .debug .t_object-class.empty { cursor:auto; }'+
			'.debug .vis-toggles span:hover,'+
				'.debug .interfaces span.toggle-interface:hover { background-color:rgba(0,0,0,0.1); }'+
			'.debug .vis-toggles span.toggle-off,'+
				'.debug .interfaces span.toggle-off { opacity:0.42 }'+
			//'.debug .t_array-collapse i.fa, .debug .t_array-expand i.fa, .debug .t_object-class i.fa { font-size:inherit; }'+
			'';
		if ( scope ) {
			css = css.replace(new RegExp(/\.debug\s/g), scope+' ');
		}
		$('<style>'+css+'</style>').appendTo('head');
	}

	function addExpandAll($root) {
		console.log('addExpandAll');
		var $expand_all = $('<a>').prop({
				'href':'#'
			}).html('Expand All Groups').addClass('expand-all');
		if ( $root.find('.group-header').length ) {
			$expand_all.on('click', function() {
				$root.find('.group-header').each( function() {
					if ( !$(this).nextAll('.m_group').eq(0).is(':visible') ) {
						toggleGroupOrObject(this);
					}
				});
				return false;
			});
			$root.find('.debug-content').before($expand_all);
		}
	}

	function addIcons($root) {
		// console.log('addIcons');
		$.each(icons, function(selector,v){
			$root.find(selector).addBack(selector).each(function(){
				$(this).prepend(v);
			});
		});
	}

	function addPersistOption($root) {
		console.log('addPersistOption', debugKey);
		var $node;
		if (debugKey) {
			$node = $('<label class="debug-cookie"><input type="checkbox"> Keep debug on</label>').css({'float':'right'});
			if (cookieGet('debug') == debugKey) {
				$node.find('input').prop('checked', true);
			}
			$('input', $node).on('change', function() {
				var checked = $(this).is(':checked');
				console.log('debug persist checkbox changed', checked);
				if (checked) {
					console.log('debugKey', debugKey);
					cookieSave('debug', debugKey, 7);
				} else {
					cookieRemove('debug');
				}
			});
			$root.find('.debug-header').eq(0).prepend($node);
		}
	}

	function getDebugKey() {
		var key = null,
			queryParams = queryDecode(),
			cookieValue = cookieGet('debug');
		console.log('queryParams', queryParams);
		console.log('cookieValue', cookieValue);
		if (typeof queryParams.debug !== "undefined") {
			key = queryParams.debug;
		} else if (cookieValue) {
			key = cookieValue;
		}
		console.info('key', key);
		return key;
	}

	function changeGroupErrorIcon($toggle, icon) {
		var selector = '.fa-times-circle, .fa-warning';
		if (icon) {
			// $toggle.addClass('hasIssue');
			if ($toggle.find(selector).length) {
				$toggle.find(selector).replaceWith(icon);
			} else {
				$toggle.append(icon);
			}
		} else {
			// $toggle.removeClass('hasIssue');
			$toggle.find(selector).remove();
		}
	}

	function getGroupErrorIcon($container) {
		var icon = '';
		if ($container.find('.m_error.show').length) {
			icon = icons['.m_error'];
		} else if ($container.find('.m_warn.show').length) {
			icon = icons['.m_warn'];
		}
		return icon;
	}

	function changeToggleIcon($toggle, classNameNew) {
		var $toggleIcon = $toggle.children('i').eq(0),
			classes = [classEmpty, classCollapse, classExpand];
		$toggleIcon.addClass(classNameNew);
		$.each(classes, function(i,className){
			if (className != classNameNew) {
				$toggleIcon.removeClass(className);
			}
		});
	}

	function cookieGet(name) {
		var nameEQ = name + "=",
			ca = document.cookie.split(';'),
			c = null,
			i = 0;
		for ( i = 0; i < ca.length; i += 1 ) {
			c = ca[i];
			while (c.charAt(0) == ' ') {
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
		console.log('cookieSave', name, value, days);
		var expires = '',
			date = new Date();
		if ( days ) {
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toGMTString();
		}
		document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
	}

	/**
	 * Collapse groups leaving errors visible
	 */
	function collapseGroups($root) {
		// console.group('collapseGroups', $root);
		$root.find('.group-header').addBack('.group-header').not('.enhanced').each( function(){
			var $toggle = $(this),
				$target = $toggle.next(),
				toggleIconClass = classEmpty,
				grpErrorIcon = ($target.find('.m_error, .m_warn').not('.hide').addClass('show'), getGroupErrorIcon($target)),
				selectorKeepVis = '.m_error:visible, .m_warn:visible';	// , .m_group.expanded
			if (!$.trim($target.html()).length) {
				// console.log('empty group');
				$toggle.addClass('empty');
				changeToggleIcon($toggle, classEmpty);
				return;
			}
			// console.log('target', $target);
			$toggle.attr('data-toggle', 'group');
			$toggle.removeClass('collapsed'); // collapsed class is never used
			if ( !$toggle.hasClass('expanded') && !$target.find(selectorKeepVis).length ) {
				$toggle.removeClass('expanded');
				toggleIconClass = classExpand;
				$target.hide();
			} else {
				$toggle.addClass('expanded');
				toggleIconClass = classCollapse;
			}
			$toggle.removeClass('empty');
			if (!grpErrorIcon && !$target.children().not('.m_warn, .m_error').length) {
				/*
					group only contains errors & they're now hidden
				*/
				console.warn('nothing visible in group');
				$toggle.addClass('empty');
				toggleIconClass = classEmpty;
			}
			changeToggleIcon($toggle, toggleIconClass);
			changeGroupErrorIcon($toggle, grpErrorIcon);
		});
		// console.groupEnd();
	}

	function enhanceArrays($root) {
		// console.log('enhanceArrays');
		// console.time('enhanceArrays');
		$root.find('.t_array').each( function() {
			var $expander = $('<span class="t_array-expand" data-toggle="array">' +
						'<span class="t_keyword">Array</span><span class="t_punct">(</span>' +
						'<i class="fa '+classExpand+'"></i>&middot;&middot;&middot;' +
						'<span class="t_punct">)</span>' +
					'</span>'),
				$self = $(this);
			if ( !$.trim( $self.find('.array-inner').html() ).length ) {
				// empty array -> don't add expand/collapse
				$(this).find('br').hide();
				$(this).find('.array-inner').hide();
				return;
			}
			$self.find('.t_keyword').first().
				wrap('<span class="t_array-collapse" data-toggle="array">').
				after('<span class="t_punct">(</span> <i class="fa '+classCollapse+'"></i>').
				parent().next().remove();
			if ( !$self.parents('.t_array, .property').length ) {
				// outermost array -> leave open
				$self.before($expander.hide());
			} else {
				$self.hide().before($expander);
			}
		});
		/*
		$root.find('.t_key').each( function(){
			var html = $(this).html(),
				matches = html.match(/\[(.*)\]/),
				k = matches[1],
				isInt = k.match(/^\d+$/),
				className = isInt ? 't_key t_int' : 't_key';
			html = '<span class="t_punct">[</span>' +
				'<span class="'+ className +'">' + k + '</span>' +
				'<span class="t_punct">]</span>';
			$(this).replaceWith(html);
		});
		*/
		// console.timeEnd('enhanceArrays');
	}

	function enhanceGroups($root) {
	}

	function enhanceListeners($root) {
		$root.on('click', '[data-toggle=array]', function(){
			toggleArray(this);
			return false;
		});
		$root.on('click', '[data-toggle=group]', function(){
			toggleGroupOrObject(this);
			return false;
		});
		$root.on('click', '[data-toggle=object]', function(){
			toggleGroupOrObject(this);
			return false;
		});
		$root.on('click', '.toggle-protected, .toggle-private', function(){
			toggleObjectVis(this);
			return false;
		});
		$root.on('click', '.toggle-interface', function(){
			toggleInterfaceVis(this);
			return false;
		});
	}

	function enhanceObjects($root) {
		// console.log('enhanceObjects');
		// console.time('enhanceObjects');
		$root.find('.t_object-class').each( function() {
			var $toggle = $(this),
				$target = $toggle.next(),
				$wrapper = $toggle.parent(),
				hasProtected = $target.children('.visibility-protected').length > 0,
				hasPrivate = $target.children('.visibility-private').length > 0,
				accessible = $wrapper.data('accessible'),
				toggleClass = accessible == 'public' ?
					'toggle-off' :
					'toggle-on',
				toggleVerb = accessible == 'public' ?
					'show' :
					'hide',
				visToggles = '',
				hiddenInterfaces = [];
			if ($target.is('.t_recursion, .excluded')) {
				$toggle.addClass('empty');
				return;
			}
			$toggle.append(' <i class="fa '+classExpand+'"></i>');
			$toggle.attr('data-toggle', 'object');
			$target.hide();
			if ($target.find(".method[data-implements]").hide().length) {
				// linkify visibility
				$target.find(".method[data-implements]").each(function(){
					var iface = $(this).data("implements");
					if (hiddenInterfaces.indexOf(iface) < 0) {
						hiddenInterfaces.push(iface);
					}
				});
				$.each(hiddenInterfaces, function(i, iface){
					var $interfaces = $target.find(".interfaces"),
						regex = new RegExp('\\b'+iface+'\\b'),
						replacement = '<span class="toggle-interface toggle-off" data-interface="'+iface+'" title="toggle methods">'+
							'<i class="fa fa-eye-slash"></i> '+iface+'</span>',
						htmlNew = $interfaces.html().replace(regex, replacement);
					$interfaces.html(htmlNew);
				});
			}
			if (accessible == 'public') {
				$wrapper.find('.visibility-private, .visibility-protected').hide();
			}
			if (hasProtected) {
				visToggles += ' <span class="toggle-protected '+toggleClass+'">'+toggleVerb+' protected</span>';
			}
			if (hasPrivate) {
				visToggles += ' <span class="toggle-private '+toggleClass+'">'+toggleVerb+' private</span>';
			}
			$target.prepend('<span class="vis-toggles">' + visToggles + '</span>');
		});
		// console.timeEnd('enhanceObjects');
	}

	function enhanceSummary($root) {
		console.log('enhanceSummary');
		$root.find('.alert [class*=error-]').each( function() {
			var html = $(this).html(),
				htmlNew = '<label><input type="checkbox" checked /> ' + html + '</label>',
				className = $(this).attr('class');
			$(this).html(htmlNew);
			$(this).find('input').on('change', function(){
				console.log('onChange', this);
				if ( $(this).is(':checked') ) {
					console.log('show', className);
					$root.find('.debug-content .' + className).show().addClass('show').removeClass('hide');
					collapseGroups($root);
				} else {
					console.log('hide', className);
					$root.find('.debug-content .' + className).hide().addClass('hide').removeClass('show');
					collapseGroups($root);
				}
			});
		});
	}

	function queryDecode(qs) {
		var params = {},
			tokens,
			re = /[?&]?([^&=]+)=?([^&]*)/g;
		if ( qs === undefined ) {
			qs = document.location.search;
		}
		qs = qs.split("+").join(" ");	// replace + with ' '
		while ( true ) {
			tokens = re.exec(qs);
			if ( !tokens ) {
				break;
			}
			params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
		}
		return params;
	}

	function toggleArray(toggle) {
		var $toggle = $(toggle),
			$target = $toggle.hasClass('t_array-expand') ?
				$toggle.next() :
				$toggle.closest('.t_array');
		if ( $toggle.hasClass('t_array-expand') ) {
			$toggle.hide();
			$target.show();
		} else {
			$target.hide();
			$target.prev().show();	// show the "collapsed version"
		}
	}

	function toggleObjectVis(toggle) {
		console.log('toggleObjectVis', toggle);
		var vis = $(toggle).hasClass('toggle-protected') ? 'protected' : 'private',
			$toggles = $(toggle).closest('.t_object').find('.toggle-'+vis),
			icon = $(toggle).find('i')[0].outerHTML;
		console.log('icon', icon);
		if ($(toggle).hasClass('toggle-off')) {
			// show for this and all descendants
			$toggles.
				html(icon + 'hide '+vis).
				addClass('toggle-on').
				removeClass('toggle-off');
			$(toggle).closest('.t_object').find('.visibility-'+vis).show();
		} else {
			// hide for this and all descendants
			$toggles.
				html(icon + 'show '+vis).
				addClass('toggle-off').
				removeClass('toggle-on');
			$(toggle).closest('.t_object').find('.visibility-'+vis).hide();
		}
	}

	function toggleInterfaceVis(toggle) {
		console.log('toggleInterfaceVis', toggle);
		var $toggle = $(toggle),
			iface = $(toggle).data("interface"),
			$methods = $(toggle).closest(".t_object").find("> .object-inner > dd[data-implements="+iface+"]");
		if ($(toggle).hasClass('toggle-off')) {
			$toggle.addClass("toggle-on").removeClass("toggle-off");
			$methods.show();
		} else {
			$toggle.addClass("toggle-off").removeClass("toggle-on");
			$methods.hide();
		}
	}

	function toggleGroupOrObject(toggle) {
		var $toggle = $(toggle);
		if ($toggle.hasClass('empty')) {
			return;
		}
		if ($toggle.hasClass('expanded')) {
			$toggle.debugEnhance('collapse');
		} else {
			$toggle.debugEnhance('expand');
		}
	}

}( window.jQuery || undefined ));