/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups and arrays
 *    Add FontAwesome icons
 */

(function ($) {

	if ( !$ ) {
		console.warn('jQuery not yet defined -> not enhancing debug output');
		return;
	}

	var classExpand = 'fa-plus-square-o',
		classCollapse = 'fa-minus-square-o',
		fontAwesomeCss = '//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css',
		icons = {
			'.expand-all' : '<i class="fa fa-plus"></i>',
			'.group-header' : '<i class="fa '+classCollapse+'"></i>',
			'.timestamp' :	'<i class="fa fa-calendar"></i>',
			'.assert' :		'<i><b>&ne;</b></i>',
			'.count' :		'<i class="fa fa-plus-circle"></i>',
			'.info'	:		'<i class="fa fa-info-circle"></i>',
			'.warn' :		'<i class="fa fa-warning"></i>',
			'.error' :		'<i class="fa fa-times-circle"></i>',
			'.time' :		'<i class="fa fa-clock-o"></i>'
		};

	$(function() {
		$('<link/>', { rel: 'stylesheet', href: fontAwesomeCss }).appendTo('head');
		$().debugEnhance('addCss', '.debug');
		$('.debug').debugEnhance();
	});

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
			addIcons(this);
			collapseGroups(this);
			enhanceSummary(this);
			enhanceArrays(this);
			console.groupEnd();
		});
		this.on('click', '[data-toggle=group]', function(){
			toggleGroup(this);
			return false;
		});
		this.on('click', '[data-toggle=array]', function(){
			toggleArray(this);
			return false;
		});
		return this;
	};

	/**
	 * Adds CSS to head of page
	 */
	function addCss(scope) {
		console.log('addCss');
		var css = ''+
			'.debug i.fa, .debug .assert i { font-size: 1.33em; line-height: 1; margin-right: .33em; }'+
			'.debug i.fa-plus-circle { opacity: 0.42 }'+
			'.debug i.fa-calendar { font-size: 1.1em; }'+
			//'.debug .assert i { font-size: 1.3em; line-height: 1; margin-right: .33em; }'+
			'.debug a.expand-all { color: inherit; text-decoration: none; }'+
			'.debug a.expand-all { font-size:1.25em; }'+
			'.debug .group-header { cursor: pointer; }'+
			'.debug .t_array-collapse, .debug .t_array-expand { cursor: pointer; }'+
			'.debug .t_array-collapse i.fa, .debug .t_array-expand i.fa { font-size: inherit; }';
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
			}).html('Expand All').addClass('expand-all');
		if ( $(root).find('.group-header').length ) {
			$expand_all.on('click', function() {
				$('.group-header', root).each( function() {
					if ( !$(this).nextAll('.group').eq(0).is(':visible') ) {
						toggleGroup(this);
					}
				});
				return false;
			});
			$(root).find('.debug-content').before($expand_all);
		}
	}

	function collapseGroups(root) {
		console.log('collapseGroups');
		$('.group-header', root).each( function(){
			var $toggle = $(this),
				$target = $toggle.next(),
				selectorKeepVis = '.error:visible, .warn:visible, .group.expanded';
			if ( $target.is(':empty') || !$.trim($target.html()).length ) {
				return;
			}
			$toggle.attr('data-toggle', 'group');
			if ( !$target.hasClass('expanded') && !$target.find(selectorKeepVis).length ) {
				$toggle.find('i').addClass(classExpand).removeClass(classCollapse);
				$target.hide();
			} else {
				$toggle.find('i').addClass(classCollapse).removeClass(classExpand);
			}
			$target.removeClass('collapsed expanded');
		});
	}

	function enhanceArrays(root) {
		console.log('enhanceArrays');
		$('.t_array', root).each( function() {
			var $expander = $('<span class="t_array-expand" data-toggle="array">'+
					'<span class="t_keyword">Array</span><span class="t_punct">(</span>' +
					'<i class="fa '+classExpand+'"></i>&middot;&middot;&middot;' +
					'<span class="t_punct">)</span>' +
				'</span>');
			if ( !$.trim( $(this).find('.t_array-inner').html() ).length ) {
				// empty array -> don't add expand/collapse
				$(this).find('br').hide();
				$(this).find('.t_array-inner').hide();
				return;
			}
			$(this).find('.t_keyword').first().
				wrap('<span class="t_array-collapse" data-toggle="array">').
				after(' <i class="fa '+classCollapse+'"></i>');
			if ( !$(this).parents('.t_array').length ) {
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
					$('.debug-content .' + className, root).show();
				} else {
					console.log('hide', className);
					$('.debug-content .' + className, root).hide();
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

	function toggleGroup(toggle) {
		var $toggle = $(toggle),
			$target = $toggle.next();
		if ( $target.is(':visible') ) {
			$target.slideUp('fast', function(){
				$toggle.find('i').addClass(classExpand).removeClass(classCollapse);
			});
		} else {
			$target.slideDown('fast', function(){
				$toggle.find('i').addClass(classCollapse).removeClass(classExpand);
			});	//.css('display','');
		}
	}

}( window.jQuery || undefined ));