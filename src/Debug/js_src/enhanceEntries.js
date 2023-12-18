import $ from 'jquery'
import * as enhanceObject from './enhanceObject.js'
import * as tableSort from './tableSort.js'
import * as fileLinks from './FileLinks.js'

var config
var toExpandQueue = []
var processingQueue = false

export function init ($root) {
  config = $root.data('config').get()
  enhanceObject.init($root)
  fileLinks.init($root)
  $root.on('click', '.close[data-dismiss=alert]', function () {
    $(this).parent().remove()
  })
  $root.on('click', '.show-more-container .show-less', onClickShowLess)
  $root.on('click', '.show-more-container .show-more', onClickShowMore)
  $root.on('expand.debug.array', onExpandArray)
  $root.on('expand.debug.group', onExpandGroup)
  $root.on('expand.debug.object', onExpandObject)
  $root.on('expanded.debug.next', '.context', function (e) {
    enhanceArray($(e.target).find('> td > .t_array'))
  })
  $root.on('expanded.debug.array expanded.debug.group expanded.debug.object', onExpanded)
}

/**
 * Enhance log entries inside .group-body
 */
export function enhanceEntries ($node) {
  // console.log('enhanceEntries', $node[0])
  var $parent = $node.parent()
  var show = !$parent.hasClass('m_group') || $parent.hasClass('expanded')
  // temporarily hide when enhancing... minimize redraws
  $node.hide()
  $node.children().each(function () {
    enhanceEntry($(this))
  })
  if (show) {
    $node.show().trigger('expanded.debug.group')
  }
  processExpandQueue()
  if ($node.parent().hasClass('m_group') === false) {
    // only add .enhanced to root .group-body
    $node.addClass('enhanced')
  }
}

/**
 * Enhance a single log entry
 * we don't enhance strings by default (add showmore).. needs to be visible to calc height
 */
export function enhanceEntry ($entry) {
  // console.log('enhanceEntry', $entry[0])
  if ($entry.hasClass('enhanced')) {
    return
  } else if ($entry.hasClass('m_group')) {
    enhanceGroup($entry)
  } else if ($entry.hasClass('filter-hidden')) {
    return
  } else if ($entry.is('.m_table, .m_trace')) {
    enhanceEntryTabular($entry)
  } else {
    enhanceEntryDefault($entry)
  }
  $entry.addClass('enhanced')
  $entry.trigger('enhanced.debug')
}

export function enhanceValue ($entry, node) {
  var $node = $(node)
  if ($node.is('.t_array')) {
    enhanceArray($node)
  } else if ($node.is('.t_object')) {
    enhanceObject.enhance($node)
  } else if ($node.is('table')) {
    tableSort.makeSortable($node)
  } else if ($node.is('.t_string')) {
    fileLinks.create($entry, $node)
  } else if ($node.is('.string-encoded.tabs-container')) {
    // console.warn('enhanceStringEncoded', $node)
    enhanceValue($node, $node.find('> .tab-pane.active > *'))
  }
}

function onClickShowLess () {
  var $container = $(this).closest('.show-more-container')
  $container.find('.show-more-wrapper')
    .css('display', 'block')
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
    $(this).css('display', 'inline')
  })
  $container.find('.show-more-fade').fadeOut()
  $container.find('.show-more').hide()
  $container.find('.show-less').show()
}

function onExpandArray (e) {
  var $node = $(e.target) // .t_array
  var $entry = $node.closest('li[class*=m_]')
  e.stopPropagation()
  $node.find('> .array-inner > li > :last-child, > .array-inner > li[class]').each(function () {
    enhanceValue($entry, this)
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
      enhanceValue($entry, this)
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
        '> dd.property:visible > .t_string',
        '> dd.method > ul > li > .t_string.return-value'].join(', '))
  } else {
    $strings = $()
  }
  $strings.not('[data-type-more=numeric]').each(function () {
    enhanceLongString($(this))
  })
}

/**
 * add font-awsome icons
 */
function addIcons ($node) {
  var $caption
  var $icon = determineIcon($node)
  addIconsMisc($node)
  if (!$icon) {
    return
  }
  if ($node.hasClass('m_group')) {
    // custom icon..   add to .group-label
    $node = $node.find('> .group-header .group-label').eq(0)
  } else if ($node.find('> table').length) {
    // table... we'll prepend icon to caption
    $caption = $node.find('> table > caption')
    if (!$caption.length) {
      $caption = $('<caption>')
      $node.find('> table').prepend($caption)
    }
    $node = $caption
  }
  if ($node.find('> i:first-child').hasClass($icon.attr('class'))) {
    // already have icon
    return
  }
  $node.prepend($icon)
}

function addIconsMisc ($node) {
  var $icon
  var $node2
  var selector
  for (selector in config.iconsMisc) {
    $node2 = $node.find(selector)
    if ($node2.length === 0) {
      continue
    }
    $icon = $(config.iconsMisc[selector])
    if ($node2.find('> i:first-child').hasClass($icon.attr('class'))) {
      // already have icon
      $icon = null
      continue
    }
    $node2.prepend($icon)
    $icon = null
  }
}

function determineIcon ($node) {
  var $icon
  var $node2
  var selector
  if ($node.data('icon')) {
    return $node.data('icon').match('<')
      ? $($node.data('icon'))
      : $('<i>').addClass($node.data('icon'))
  }
  if ($node.hasClass('m_group')) {
    return $icon
  }
  $node2 = $node.hasClass('group-header')
    ? $node.parent()
    : $node
  for (selector in config.iconsMethods) {
    if ($node2.is(selector)) {
      $icon = $(config.iconsMethods[selector])
      break
    }
  }
  return $icon
}

/**
 * Adds expand/collapse functionality to array
 * does not enhance values
 */
function enhanceArray ($node) {
  // console.log('enhanceArray', $node[0])
  var $arrayInner = $node.find('> .array-inner')
  var isEnhanced = $node.find(' > .t_array-expand').length > 0
  if (isEnhanced) {
    return
  }
  if ($.trim($arrayInner.html()).length < 1) {
    // empty array -> don't add expand/collapse
    $node.addClass('expanded').find('br').hide()
    /*
    if ($node.hasClass('max-depth') === false) {
      return
    }
    */
    return
  }
  enhanceArrayAddMarkup($node)
  $.each(config.iconsArray, function (selector, v) {
    $node.find(selector).prepend(v)
  })
  $node.debugEnhance(enhanceArrayIsExpanded($node) ? 'expand' : 'collapse')
}

function enhanceArrayAddMarkup ($node) {
  var $arrayInner = $node.find('> .array-inner')
  var $expander
  if ($node.closest('.array-file-tree').length) {
    $node.find('> .t_keyword, > .t_punct').remove()
    $arrayInner.find('> li > .t_operator, > li > .t_key.t_int').remove()
    $node.prevAll('.t_key').each(function () {
      var $dir = $(this).attr('data-toggle', 'array')
      $node.prepend($dir)
      $node.prepend(
        '<span class="t_array-collapse" data-toggle="array">▾ </span>' + // ▼
        '<span class="t_array-expand" data-toggle="array">▸ </span>' // ▶
      )
    })
    return
  }
  $expander = $('<span class="t_array-expand" data-toggle="array">' +
      '<span class="t_keyword">array</span><span class="t_punct">(</span> ' +
      '<i class="fa ' + config.iconsExpand.expand + '"></i>&middot;&middot;&middot; ' +
      '<span class="t_punct">)</span>' +
    '</span>')
  // add expand/collapse
  $node.find('> .t_keyword').first()
    .wrap('<span class="t_array-collapse" data-toggle="array">')
    .after('<span class="t_punct">(</span> <i class="fa ' + config.iconsExpand.collapse + '"></i>')
    .parent().next().remove() // remove original '('
  $node.prepend($expander)
}

function enhanceArrayIsExpanded ($node) {
  var expand = $node.data('expand')
  var numParents = $node.parentsUntil('.m_group', '.t_object, .t_array').length
  var expandDefault = numParents === 0
  if (expand === undefined && numParents !== 0) {
    // nested array and expand === undefined
    expand = $node.closest('.t_array[data-expand]').data('expand')
  }
  if (expand === undefined) {
    expand = expandDefault
  }
  return expand || $node.hasClass('array-file-tree')
}

function enhanceEntryDefault ($entry) {
  // regular log-type entry
  var title
  if ($entry.data('file')) {
    if (!$entry.attr('title')) {
      title = $entry.data('file') + ': line ' + $entry.data('line')
      if ($entry.data('evalline')) {
        title += ' (eval\'d line ' + $entry.data('evalline') + ')'
      }
      $entry.attr('title', title)
    }
    fileLinks.create($entry)
  }
  addIcons($entry)
  $entry.children().each(function () {
    enhanceValue($entry, this)
  })
}

function enhanceEntryTabular ($entry) {
  fileLinks.create($entry)
  addIcons($entry)
  if ($entry.hasClass('m_table')) {
    $entry.find('> table > tbody > tr > td').each(function () {
      enhanceValue($entry, this)
    })
  }
  // table may have a expand collapse row that's initially expanded
  //   trigger expanded event  (so, trace context args are enhanced, etc)
  $entry.find('tbody > tr.expanded').next().trigger('expanded.debug.next')
  tableSort.makeSortable($entry.find('> table'))
}

function enhanceGroup ($group) {
  // console.log('enhanceGroup', $group[0])
  var $toggle = $group.find('> .group-header')
  var $target = $toggle.next()
  addIcons($group) // custom data-icon
  addIcons($toggle) // expand/collapse
  $toggle.attr('data-toggle', 'group')
  $toggle.find('.t_array, .t_object').each(function () {
    $(this).data('expand', false)
    enhanceValue($group, this)
  })
  $.each(['level-error', 'level-info', 'level-warn'], function (i, classname) {
    var $toggleIcon
    if ($group.hasClass(classname)) {
      $toggleIcon = $toggle.children('i').eq(0)
      $toggle.wrapInner('<span class="' + classname + '"></span>')
      $toggle.prepend($toggleIcon) // move icon
    }
  })
  /*
  if ($group.hasClass('filter-hidden')) {
    return
  }
  */
  if (
    $group.hasClass('expanded') ||
    $target.find('.m_error, .m_warn').not('.filter-hidden').not('[data-uncollapse=false]').length
  ) {
    toExpandQueue.push($toggle)
    return
  }
  $toggle.debugEnhance('collapse', true)
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

function processExpandQueue () {
  if (processingQueue) {
    return
  }
  processingQueue = true
  while (toExpandQueue.length) {
    toExpandQueue.shift().debugEnhance('expand')
  }
  processingQueue = false
}
