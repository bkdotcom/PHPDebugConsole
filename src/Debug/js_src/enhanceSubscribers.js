import $ from 'microDom'

var enhanceObject
var enhanceValue

export function init($root, enhanceVal, enhanceObj) {
  enhanceValue = enhanceVal
  enhanceObject = enhanceObj
  $root.on('click', '.close[data-dismiss=alert]', function () {
    $(this).parent().remove()
  })
  $root.on('click', '.show-more-container .show-less', onClickShowLess)
  $root.on('click', '.show-more-container .show-more', onClickShowMore)
  $root.on('click', '.char-ws, .unicode', onClickUnicode)
  $root.on('expand.debug.array', onExpandArray)
  $root.on('expand.debug.group', onExpandGroup)
  $root.on('expand.debug.object', onExpandObject)
  $root.on('expanded.debug.next', '.context', function (e) {
    enhanceValue($(e.target).find('> td > .t_array'), $(e.target).closest('li'))
  })
  $root.on('expanded.debug.array expanded.debug.group expanded.debug.object', onExpanded)
}

function onClickShowLess () {
  var $container = $(this).closest('.show-more-container')
  $container.find('.show-more-wrapper')
    .style('display', 'block')
    .animate({
      height: '70px'
    })
  $container.find('.show-more-fade').fadeIn()
  $container.find('.show-more').show()
  $container.find('.show-less').hide()
}

function onClickShowMore () {
  var $container = $(this).closest('.show-more-container')
  $container.find('.show-more-wrapper').animate({
    height: $container.find('.t_string').height()
  }, 400, 'swing', function () {
    $(this).style('display', 'inline')
  })
  $container.find('.show-more-fade').fadeOut()
  $container.find('.show-more').hide()
  $container.find('.show-less').show()
}

function onClickUnicode(e) {
  var codePoint = $(this).data('codePoint')
  var url = 'https://symbl.cc/en/' + codePoint
  e.stopPropagation()
  if (codePoint) {
    window.open(url, 'unicode').focus()
  }
}

function onExpandArray (e) {
  var $node = $(e.target) // .t_array
  var $entry = $node.closest('li[class*=m_]')
  e.stopPropagation()
  $node.find('> .array-inner > li > :last-child, > .array-inner > li[class]').each(function () {
    enhanceValue(this, $entry)
  })
}

function onExpandGroup (e) {
  var $node = $(e.target) // .m_group
  e.stopPropagation()
  $node.find('> .group-body').debugEnhance()
}

function onExpandObject (e) {
  var $node = $(e.target) // .t_object
  var $entry = $node.closest('li[class*=m_]')
  e.stopPropagation()
  if ($node.is('.enhanced')) {
    return
  }
  $node.find('> .object-inner')
    .find('> .constant > :last-child,' +
      '> .property > :last-child,' +
      '> .method .t_string'
    ).each(function () {
      enhanceValue(this, $entry)
    })
  enhanceObject.enhanceInner($node)
}

function onExpanded (e) {
  var $strings
  var $target = $(e.target)
  if ($target.hasClass('t_array')) {
    // e.namespace = array.debug ??
    $strings = $target.find('> .array-inner')
      .find('> li > .t_string,' +
        ' > li.t_string')
  } else if ($target.hasClass('m_group')) {
    // e.namespace = debug.group
    $strings = $target.find('> .group-body > li > .t_string')
  } else if ($target.hasClass('t_object')) {
    // e.namespace = debug.object
    $strings = $target.find('> .object-inner')
      .find(['> dd.constant > .t_string',
        '> dd.property > .t_string', // was '> dd.property:visible > .t_string'
        '> dd.method > ul > li > .t_string.return-value'].join(', '))
      .filter(':visible')
  } else {
    $strings = $()
  }
  $strings.not('[data-type-more=numeric]').each(function () {
    enhanceLongString($(this))
  })
}

function enhanceLongString ($node) {
  var $container
  var $stringWrap
  var height = $node.height()
  var diff = height - 70
  if (diff > 35) {
    $stringWrap = $node.wrap('<div class="show-more-wrapper"></div>').parent()
    $stringWrap.append('<div class="show-more-fade"></div>')
    $container = $stringWrap.wrap('<div class="show-more-container"></div>').parent()
    $container.append('<button type="button" class="show-more"><i class="fa fa-caret-down"></i> More</button>')
    $container.append('<button type="button" class="show-less" style="display:none;"><i class="fa fa-caret-up"></i> Less</button>')
  }
}
