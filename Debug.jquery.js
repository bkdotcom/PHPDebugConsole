/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups and arrays
 *    Add FontAwesome icons
 */

$(function() {
	var fontAwesomeCss = '//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css';
	$('<link/>', { rel: 'stylesheet', href: fontAwesomeCss }).appendTo('head');
	$('.debug').debugEnhance();
	$().debugEnhance('addCss', '.debug');
});

(function ($) {

	var classExpand = 'fa-plus-square-o',
		classCollapse = 'fa-minus-square-o',
		icons = {
			'.expand-all' : '<i class="fa fa-plus"></i>',
			//'.collapsed' :	'<i class="fa '+classExpand+'"></i>',
			//'.expanded' :	'<i class="fa '+classCollapse+'"></i>',
			'.timestamp' :	'<i class="fa fa-calendar"></i>',
			'.assert' :		'<i><b>&ne;</b></i>',
			'.count' :		'<i class="fa fa-plus-circle"></i>',
			'.info'	:		'<i class="fa fa-info-circle"></i>',
			'.warn' :		'<i class="fa fa-warning"></i>',
			'.error' :		'<i class="fa fa-times-circle"></i>',
			'.time' :		'<i class="fa fa-clock-o"></i>'
		};

	jQuery.fn.debugEnhance = function(method) {

		var self = this;

		if ( method == 'addCss' ) {
			addCss(arguments[1]);
		}

		this.each( function() {
			var $debug = $(this),
				$expand_all = $('<a>').prop({
					'href':'#'
				}).html('Expand All').addClass('expand-all');
			if ( $debug.find('.group-header').length ) {
				$expand_all.click( function() {
					$debug.find('.group-header').each( function() {
						if ( !$(this).nextAll('.group').eq(0).is(':visible') ) {
							debugGroupFold(this);
						}
					});
					return false;
				});
				$debug.find('.debug-content').before($expand_all);
			}
		});
		$('.group-header', this).each( function(){
			var $toggle = $(this),
				$target = $toggle.next();
			if ( $target.is(':empty') || !$.trim($target.html()).length ) {
				return;
			}
			$toggle.attr('data-toggle', 'group');
			if ( !$target.hasClass('expanded') && !$target.find('.error, .warn, .group.expanded').length ) {
				$toggle.prepend('<i class="fa '+classExpand+'"></i>');
				$target.hide();
			} else {
				$toggle.prepend('<i class="fa '+classCollapse+'"></i>');
			}
			$target.removeClass('collapsed expanded');
		});
		$('.t_array', this).each( function(){
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
		$('.t_key', this).each( function(){
			var html = $(this).html(),
				matches = html.match(/\[(.*)\]/),
				k = matches[1],
				isInt = k.match(/^\d+$/),
				className = isInt ? 't_key t_int' : 't_key';
			html = '<span class="t_punct">[</span><span class="'+ className +'">' + k + '</span><span class="t_punct">]</span>';
			$(this).replaceWith(html);
		});
		$.each(icons, function(k,v){
			$(k, self).each(function(){
				$(this).prepend(v);
			});
		});
		this.on('click', '[data-toggle=group]', function(){
			debugGroupFold(this);
			return false;
		});
		this.on('click', '[data-toggle=array]', function(){
			debugArrayFold(this);
			return false;
		});
		return this;
	};

	function debugGroupFold(toggle) {
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

	function debugArrayFold(toggle) {
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

	function addCss(scope) {
		var css = ''+
			'.debug i.fa, .debug .assert i { font-size: 1.33em; line-height: 1; margin-right: .33em; }'+
			'.debug i.fa-plus-circle { opacity: 0.42 }'+
			'.debug i.fa-calendar { font-size: 1.1em; }'+
			//'.debug .assert i { font-size: 1.3em; line-height: 1; margin-right: .33em; }'+
			'.debug a.expand-all { color: inherit; text-decoration: none; }'+
			'.debug a.expand-all { display: block; font-size:1.25em; margin-bottom:.5em; }'+
			'.debug .group-header { cursor: pointer; }'+
			'.debug .t_array-collapse, .debug .t_array-expand { cursor: pointer; }'+
			'.debug .t_array-collapse i.fa, .debug .t_array-expand i.fa { font-size: inherit; }';
		if ( scope ) {
			css = css.replace(new RegExp(/\.debug/g), scope+' ');
		}
		$('<style>'+css+'</style>').appendTo('head');
	}

}( jQuery ));
