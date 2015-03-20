/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups and arrays
 *    Add FontAwesome icons
 */

(function ($) {

	var classExpand = 'fa-plus-square-o',
		classCollapse = 'fa-minus-square-o',
		classEmpty = 'fa-square-o',
		fontAwesomeCss = '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css',
		jQuerySrc = '//code.jquery.com/jquery-1.11.2.min.js',
		icons = {
			'.debug-value' : '<i class="fa fa-eye" title="via __debugInfo()"></i>',
			'.expand-all' : '<i class="fa fa-lg fa-plus"></i>',
			'.group-header' : '<i class="fa fa-lg '+classCollapse+'"></i>',
			'.m_assert' :	'<i class="fa-lg"><b>&ne;</b></i>',
			'.m_count' :	'<i class="fa fa-lg fa-plus-circle"></i>',
			'.m_error' :	'<i class="fa fa-lg fa-times-circle"></i>',
			'.m_info' :		'<i class="fa fa-lg fa-info-circle"></i>',
			'.m_warn' :		'<i class="fa fa-lg fa-warning"></i>',
			'.m_time' :		'<i class="fa fa-lg fa-clock-o"></i>',
			'.timestamp' :	'<i class="fa fa-calendar"></i>',
			'.toggle-protected' :	'<i class="fa fa-shield"></i>',
			'.toggle-private' :		'<i class="fa fa-user-secret"></i>'
		},
		initialized = false,
		intervalCounter = 0,
		checkInterval;

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

		jQuery.fn.debugEnhance = function(method) {
			if ( method ) {
				if ( method === 'addCss' ) {
					addCss(arguments[1]);
				}
				return;
			}
			this.each( function() {
				console.group('enhancing',this);
				if ( $('.expand-all', this).length ) {
					console.warn('already enhanced');
					return;
				}
				addExpandAll(this);
				enhanceSummary(this);
				enhanceArrays(this);
				enhanceObjects(this);
				addIcons(this);
				collapseGroups(this); // needs to come after adding icons
				console.groupEnd();
			});
			this.on('click', '[data-toggle=group], [data-toggle=object]', function(){
				toggleGroup(this);
				return false;
			});
			this.on('click', '.toggle-protected, .toggle-private', function(){
				toggleObjectVis(this);
				return false;
			});
			this.on('click', '[data-toggle=array]', function(){
				toggleArray(this);
				return false;
			});
			return this;
		};

		$(function() {
			$('<link/>', { rel: 'stylesheet', href: fontAwesomeCss }).appendTo('head');
			$().debugEnhance('addCss', '.debug');
			$('.debug').debugEnhance();
			initialized = true;
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
			'.debug i.fa, .debug .m_assert i { margin-right:.33em; }'+
			//'.debug .group-header i.fa-plus-square-o,'+
			//	'.debug .group-header i.fa-minus-square-o { vertical-align:middle; }'+
			'.debug i.fa-plus-circle { opacity:0.42; }'+
			'.debug i.fa-calendar { font-size:1.1em; }'+
			'.debug i.fa-eye { color:#00529b; font-size:1.1em; border-bottom:0; }'+
			'.debug i.fa-eye[title] { border-bottom:inherit; }'+
			'.debug i.fa-lg { font-size:1.33em; }'+

			'.debug .hasIssue.expanded i.fa-warning, .debug .hasIssue.expanded i.fa-times-circle { display:none; }'+
			'.debug .hasIssue i.fa-warning { color:#cdcb06; margin-left:.33em}'+		// warning
			'.debug .hasIssue i.fa-times-circle { color:#D8000C; margin-left:.33em;}'+	// error

			//'.debug .assert i { font-size:1.3em; line-height:1; margin-right:.33em; }'+
			'.debug a.expand-all { font-size:1.25em; color:inherit; text-decoration:none; }'+
			'.debug .t_array-collapse,'+
				'.debug .t_array-expand,'+
				'.debug .group-header,'+
				'.debug .t_object-class,'+
				'.debug .vis-toggles span { cursor:pointer; }'+
			'.debug .group-header.empty { cursor:auto; }'+
			'.debug .vis-toggles span:hover { background-color:rgba(0,0,0,0.1); }'+
			'.debug .vis-toggles span.toggle-off { opacity:0.42 }'+
			//'.debug .t_array-collapse i.fa, .debug .t_array-expand i.fa, .debug .t_object-class i.fa { font-size:inherit; }'+
			'';
		if ( scope ) {
			css = css.replace(new RegExp(/\.debug\s/g), scope+' ');
		}
		$('<style>'+css+'</style>').appendTo('head');
	}

	function addIcons(root) {
		console.log('addIcons');
		$.each(icons, function(k,v){
			$(k, root).each(function(){
				$(this).prepend(v);
			});
		});
	}

	function addExpandAll(root) {
		console.log('addExpandAll');
		var $expand_all = $('<a>').prop({
				'href':'#'
			}).html('Expand All Groups').addClass('expand-all');
		if ( $(root).find('.group-header').length ) {
			$expand_all.on('click', function() {
				$('.group-header', root).each( function() {
					if ( !$(this).nextAll('.m_group').eq(0).is(':visible') ) {
						toggleGroup(this);
					}
				});
				return false;
			});
			$(root).find('.debug-content').before($expand_all);
		}
	}

	function changeGroupErrorIcon($toggle, icon) {
		var selector = '.fa-times-circle, .fa-warning';
		if (icon) {
			$toggle.addClass('hasIssue');
			if ($toggle.find(selector).length) {
				$toggle.find(selector).replaceWith(icon);
			} else {
				$toggle.append(icon);
			}
		} else if (initialized) {
			$toggle.find(selector).remove();
		}
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

	/**
	 * Collapse groups leaving errors visible
	 */
	function collapseGroups(root) {
		console.group('collapseGroups');
		if (!initialized) {
			$('.m_error, .m_warn', root).addClass('show');
		}
		$('.group-header', root).each( function(){
			var $toggle = $(this),
				$target = $toggle.next(),
				toggleIconClass = classEmpty,
				errorIcon = getHiddenErrorIcon($target),
				selectorKeepVis = '.m_error:visible, .m_warn:visible';	// , .m_group.expanded
			if (!$.trim($target.html()).length) {
				console.log('empty group');
				$toggle.addClass('empty');
				changeToggleIcon($toggle, classEmpty);
				return;
			}
			console.log('errorIcon', errorIcon);
			$toggle.attr('data-toggle', 'group');
			// if ( !$target.hasClass('expanded') && !$target.find(selectorKeepVis).length ) {
			if ( !$toggle.hasClass('expanded') && !$target.find(selectorKeepVis).length ) {
				// $toggle.removeClass('expanded');
				toggleIconClass = classExpand;
				$target.hide();
			} else {
				$toggle.addClass('expanded');
				toggleIconClass = classCollapse;
			}
			if (!initialized) {
				// collapsed class is never used
				$toggle.removeClass('collapsed');
			} else {
				$toggle.removeClass('empty hasIssue');
				if (!errorIcon && !$target.children().not('.m_warn, .m_error').length) {
					console.warn('group is effectively empty');
					$toggle.addClass('empty');
					toggleIconClass = classEmpty;
				}
			}
			changeToggleIcon($toggle, toggleIconClass);
			changeGroupErrorIcon($toggle, errorIcon);
		});
		console.groupEnd();
	}

	function enhanceArrays(root) {
		console.log('enhanceArrays');
		$('.t_array', root).each( function() {
			var $expander = $('<span class="t_array-expand" data-toggle="array">'+
					'<span class="t_keyword">Array</span><span class="t_punct">(</span>' +
					'<i class="fa '+classExpand+'"></i>&middot;&middot;&middot;' +
					'<span class="t_punct">)</span>' +
				'</span>');
			if ( !$.trim( $(this).find('.array-inner').html() ).length ) {
				// empty array -> don't add expand/collapse
				$(this).find('br').hide();
				$(this).find('.array-inner').hide();
				return;
			}
			$(this).find('.t_keyword').first().
				wrap('<span class="t_array-collapse" data-toggle="array">').
				after('<span class="t_punct">(</span> <i class="fa '+classCollapse+'"></i>').
				parent().next().remove();
			if ( !$(this).parents('.t_array, .property').length ) {
				// outermost array -> leave open
				$(this).before($expander.hide());
			} else {
				$(this).hide().before($expander);
			}
		});
		$('.t_key', root).each( function(){
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
	}

	function enhanceObjects(root) {
		console.log('enhanceObjects');
		$('.t_object-class', root).each( function() {
			var $toggle = $(this),
				$target = $toggle.next(),
				$wrapper = $toggle.parent(),
				hasProtected = $target.children('.visibility-protected').length > 0,
				hasPrivate = $target.children('.visibility-private').length > 0,
				accessible = $wrapper.data('accessible'),
				toggleClass = accessible == 'public'
					? 'toggle-off'
					: 'toggle-on',
				toggleVerb = accessible == 'public'
					? 'show'
					: 'hide',
				visToggles = '';
			if ($target.is('.t_recursion, .excluded')) {
				return;
			}
			$toggle.append(' <i class="fa '+classExpand+'"></i>');
			$toggle.attr('data-toggle', 'object');
			$target.hide();
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
	}

	function enhanceSummary(root) {
		console.log('enhanceSummary');
		$('.alert [class*=error-]', root).each( function() {
			var html = $(this).html(),
				htmlNew = '<label><input type="checkbox" checked /> ' + html + '</label>',
				className = $(this).attr('class');
			$(this).html(htmlNew);
			$(this).find('input').on('change', function(){
				console.log('this', this);
				if ( $(this).is(':checked') ) {
					console.log('show', className);
					$('.debug-content .' + className, root).show().addClass('show').removeClass('hide');
					collapseGroups(root);
				} else {
					console.log('hide', className);
					$('.debug-content .' + className, root).hide().addClass('hide').removeClass('show');
					collapseGroups(root);
				}
			});
		});
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

	function toggleGroup(toggle) {
		var $toggle = $(toggle),
			$target = $toggle.next();
		if ($toggle.hasClass('empty')) {
			return;
		}
		if ( $target.is(':visible') ) {
			$toggle.removeClass('expanded');
			$target.slideUp('fast', function() {
				changeToggleIcon($toggle, classExpand);
			});
		} else {
			$target.slideDown('fast', function(){
				$toggle.addClass('expanded');
				changeToggleIcon($toggle, classCollapse);
			});
		}
	}

	function getHiddenErrorIcon($container) {
		var icon = '';
		if ($container.find('.m_error.show').length) {
			icon = icons['.m_error'];
		} else if ($container.find('.m_warn.show').length) {
			icon = icons['.m_warn'];
		}
		return icon;
	}

}( window.jQuery || undefined ));