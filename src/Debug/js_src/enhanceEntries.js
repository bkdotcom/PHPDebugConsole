import $ from 'jquery'
import * as enhanceObject from './enhanceObject.js'
import * as tableSort from './tableSort.js'

var config
var toExpandQueue = []

export function init ($root) {
  config = $root.data('config').get()
  enhanceObject.init($root)
  $root.on('click', '.close[data-dismiss=alert]', function () {
    $(this).parent().remove()
  })
  $root.on('click', '.show-more-container .show-more', function () {
    var $container = $(this).closest('.show-more-container')
    $container.find('.show-more-wrapper').animate({
      height: $container.find('.t_string').height()
    }, 400, 'swing', function () {
      $(this).css('display', 'inline')
    })
    $container.find('.show-more-fade').fadeOut()
    $container.find('.show-more').hide()
    $container.find('.show-less').show()
  })
  $root.on('click', '.show-more-container .show-less', function () {
    var $container = $(this).closest('.show-more-container')
    $container.find('.show-more-wrapper')
      .css('display', 'block')
      .animate({
        height: '70px'
      })
    $container.find('.show-more-fade').fadeIn()
    $container.find('.show-more').show()
    $container.find('.show-less').hide()
  })
  $root.on('config.debug.updated', function (e, changedOpt) {
    e.stopPropagation()
    if (changedOpt === 'linkFilesTemplate') {
      updateFileLinks($root)
    }
  })
  $root.on('expand.debug.array', function (e) {
    var $node = $(e.target) // .t_array
    var $entry = $node.closest('li[class*=m_]')
    e.stopPropagation()
    $node.find('> .array-inner > li > :last-child, > .array-inner > li[class]').each(function () {
      enhanceValue($entry, this)
    })
  })
  $root.on('expand.debug.group', function (e) {
    var $node = $(e.target) // .m_group
    e.stopPropagation()
    $node.find('> .group-body').debugEnhance()
  })
  $root.on('expand.debug.object', function (e) {
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
  })
  $root.on('expanded.debug.next', '.context', function (e) {
    enhanceArray($(e.target).find('> td > .t_array'))
  })
  $root.on('expanded.debug.array expanded.debug.group expanded.debug.object', function (e) {
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
        .find('> dd.constant > .t_string,' +
          ' > dd.property:visible > .t_string,' +
          ' > dd.method > .t_string')
    } else {
      $strings = $()
    }
    $strings.not('.numeric').each(function () {
      enhanceLongString($(this))
    })
  })
}

/**
 * add font-awsome icons
 */
function addIcons ($node) {
  var $caption
  var $icon
  var $node2
  var selector
  for (selector in config.iconsMisc) {
    $node2 = $node.find(selector)
    if ($node2.length) {
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
  if ($node.data('icon')) {
    $icon = $node.data('icon').match('<')
      ? $($node.data('icon'))
      : $('<i>').addClass($node.data('icon'))
  } else if (!$node.hasClass('m_group')) {
    $node2 = $node.hasClass('group-header')
      ? $node.parent()
      : $node
    for (selector in config.iconsMethods) {
      if ($node2.is(selector)) {
        $icon = $(config.iconsMethods[selector])
        break
      }
    }
  }
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

function buildFileLink (file, line) {
  var data = {
    file: file,
    line: line || 1
  }
  return config.linkFilesTemplate.replace(
    /%(\w*)\b/g,
    function (m, key) {
      return Object.prototype.hasOwnProperty.call(data, key)
        ? data[key]
        : ''
    }
  )
}

/**
 * Create text editor links for error, warn, & trace
 */
function createFileLinks ($entry, $strings, remove) {
  var $objects = $entry.find('.t_object > .object-inner > .property.debug-value > .t_identifier').filter(function () {
    return this.innerText.match(/^file$/)
  })
  var detectFiles = $entry.data('detectFiles') === true || $objects.length > 0
  var dataFoundFiles = $entry.data('foundFiles') || []
  var isUpdate = false
  if (!config.linkFiles && !remove) {
    return
  }
  if (detectFiles === false) {
    return
  }
  // console.info('createFileLinks', $entry[0], $strings)
  if ($entry.is('.m_trace')) {
    isUpdate = $entry.find('.file-link').length > 0
    if (!isUpdate) {
      $entry.find('table thead tr > *:last-child').after('<th></th>')
    } else if (remove) {
      $entry.find('table tr > *:last-child').remove()
      return
    }
    $entry.find('table tbody tr').each(function () {
      var $tr = $(this)
      var $tds = $tr.find('> td')
      var $a = $('<a>', {
        class: 'file-link',
        href: buildFileLink($tds.eq(0).text(), $tds.eq(1).text()),
        html: '<i class="fa fa-fw fa-external-link"></i>',
        style: 'vertical-align: bottom',
        title: 'Open in editor'
      })
      if (isUpdate) {
        $tr.find('.file-link').replaceWith($a)
        return // continue
      }
      if ($tr.hasClass('context')) {
        $tds.eq(0).attr('colspan', parseInt($tds.eq(0).attr('colspan'), 10) + 1)
        return // continue;
      }
      $tds.last().after($('<td/>', {
        class: 'text-center',
        html: $a
      }))
    })
    return
  }
  // don't remove data... link template may change
  // $entry.removeData('detectFiles foundFiles')
  if ($entry.is('[data-file]')) {
    /*
      Log entry link
    */
    $entry.find('> .file-link').remove()
    if (!remove) {
      $entry.append($('<a>', {
        html: '<i class="fa fa-external-link"></i>',
        href: buildFileLink($entry.data('file'), $entry.data('line')),
        title: 'Open in editor',
        class: 'file-link lpad'
      })[0].outerHTML)
    }
    return
  }
  if (!$strings) {
    $strings = []
  }
  $.each($strings, function () {
    // console.log('string', $(this).text())
    var $replace
    var $string = $(this)
    var attrs = $string[0].attributes
    var text = $.trim($string.text())
    var matches = []
    if ($string.closest('.m_trace').length) {
      createFileLinks($string.closest('.m_trace'))
      return false
    }
    if ($string.data('file')) {
      // filepath specified in data attr
      matches = typeof $string.data('file') === 'boolean'
        ? [null, text, 1]
        : [null, $string.data('file'), $string.data('line') || 1]
    } else if (dataFoundFiles.indexOf(text) === 0) {
      matches = [null, text, 1]
    } else if ($string.parent('.property.debug-value').find('> .t_identifier').text().match(/^file$/)) {
      // object with file .debug-value
      matches = {
        line: 1
      }
      $string.parent().parent().find('> .property.debug-value').each(function () {
        var prop = $(this).find('> .t_identifier')[0].innerText
        var $valNode = $(this).find('> *:last-child')
        var val = $.trim($valNode[0].innerText)
        matches[prop] = val
      })
      matches = [null, text, matches.line]
    } else {
      matches = text.match(/^(\/.+\.php)(?: \(line (\d+)\))?$/) || []
    }
    if (matches.length) {
      $replace = remove
        ? $('<span>', {
          html: text
        })
        : $('<a>', {
          html: text + ' <i class="fa fa-external-link"></i>',
          href: buildFileLink(matches[1], matches[2]),
          title: 'Open in editor'
        })
      if ($string.is('td, li')) {
        $string.html(remove
          ? text
          : $replace
        )
      } else {
        /*
          attrs is not a plain object, but an array of attribute nodes
          which contain both the name and value
        */
        $.each(attrs, function () {
          var name = this.name
          if (['html', 'href', 'title'].indexOf(name) > -1) {
            return // continue
          }
          $replace.attr(name, this.value)
        })
        $string.replaceWith($replace)
      }
      if (!remove) {
        $replace.addClass('file-link')
      }
    }
  })
}

/**
 * Adds expand/collapse functionality to array
 * does not enhance values
 */
function enhanceArray ($node) {
  // console.log('enhanceArray', $node[0])
  var $arrayInner = $node.find('> .array-inner')
  var isEnhanced = $node.find(' > .t_array-expand').length > 0
  var $expander
  var numParents = $node.parentsUntil('.m_group', '.t_object, .t_array').length
  var expand = $node.data('expand')
  var expandDefault = true
  if (isEnhanced) {
    return
  }
  if ($.trim($arrayInner.html()).length < 1) {
    // empty array -> don't add expand/collapse
    $node.addClass('expanded').find('br').hide()
    return
  }
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
  } else {
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
  $.each(config.iconsArray, function (selector, v) {
    $node.find(selector).prepend(v)
  })
  if (numParents === 0) {
    // outermost array
    expandDefault = true // expand
  } else {
    // nested array
    expandDefault = false // collapse
    if (expand === undefined) {
      expand = $node.closest('.t_array[data-expand]').data('expand')
    }
  }
  if (expand === undefined) {
    expand = expandDefault
  }
  if (expand || $node.hasClass('array-file-tree')) {
    $node.debugEnhance('expand')
  } else {
    $node.debugEnhance('collapse')
  }
}

/**
 * Enhance log entries inside .group-body
 */
export function enhanceEntries ($node) {
  // console.warn('enhanceEntries', $node[0])
  var $parent = $node.parent()
  var show = !$parent.hasClass('m_group') || $parent.hasClass('expanded')
  /*
  if ($node.hasClass('enhanced')) {
    return;
  }
  */
  // temporarily hide when enhancing... minimize redraws
  $node.hide()
  $node.children().each(function () {
    enhanceEntry($(this))
  })
  if (show) {
    $node.show()
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
  if ($entry.is('.enhanced, .filter-hidden')) {
    return
  }
  // console.log('enhanceEntry', $entry[0])
  if ($entry.is('.m_group')) {
    enhanceGroup($entry)
  } else if ($entry.is('.m_trace')) {
    createFileLinks($entry)
    addIcons($entry)
  } else {
    // regular log-type entry
    if ($entry.data('file')) {
      if (!$entry.attr('title')) {
        $entry.attr('title', $entry.data('file') + ': line ' + $entry.data('line'))
      }
      createFileLinks($entry)
    } else if ($entry.data('detect-files')) {
      createFileLinks($entry, $entry.find('.t_string'))
    }
    addIcons($entry)
    if ($entry.hasClass('m_table')) {
      $entry.find('> table > tbody > tr > td').each(function () {
        enhanceValue($entry, this)
      })
    }
    $entry.children().each(function () {
      enhanceValue($entry, this)
    })
  }
  $entry.addClass('enhanced')
  $entry.trigger('enhanced.debug')
}

function enhanceGroup ($group) {
  // console.log('enhanceGroup', $group[0])
  var $toggle = $group.find('> .group-header')
  var $target = $toggle.next()
  addIcons($group) // custom data-icon
  addIcons($toggle) // expand/collapse
  $toggle.attr('data-toggle', 'group')
  $.each(['level-error', 'level-info', 'level-warn'], function (i, val) {
    var $icon
    if ($toggle.hasClass(val)) {
      $icon = $toggle.children('i').eq(0)
      $toggle.wrapInner('<span class="' + val + '"></span>')
      $toggle.prepend($icon) // move icon
    }
  })
  $toggle.removeClass('level-error level-info level-warn')
  if ($.trim($target.html()).length < 1) {
    $group.addClass('empty')
  }
  if ($group.hasClass('filter-hidden')) {
    return
  }
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

function enhanceValue ($entry, node) {
  var $node = $(node)
  if ($node.is('.t_array')) {
    enhanceArray($node)
  } else if ($node.is('.t_object')) {
    enhanceObject.enhance($node)
  } else if ($node.is('table')) {
    tableSort.makeSortable($node)
  } else if ($node.is('.t_string')) {
    createFileLinks($entry, $node)
  }
  if ($node.is('.timestamp')) {
    var $i = $node.find('i')
    var text = $node.text()
    var $span = $('<span>' + text + '</span>')
    if ($node.is('.t_string')) {
      $span.addClass('t_string numeric')
    } else if ($node.is('.t_int')) {
      $span.addClass('t_int')
    } else {
      $span.addClass('t_float')
    }
    if ($node.is('.no-quotes')) {
      $span.addClass('no-quotes')
    }
    $node.removeClass('t_float t_int t_string numeric no-quotes')
    $node.html($i).append($span)
  }
}

function processExpandQueue () {
  while (toExpandQueue.length) {
    toExpandQueue.shift().debugEnhance('expand')
  }
}

/**
 * Linkify files if not already done or update already linked files
 */
function updateFileLinks ($group) {
  var remove = !config.linkFiles || config.linkFilesTemplate.length === 0
  $group.find('li[data-detect-files]').each(function () {
    createFileLinks($(this), $(this).find('.t_string'), remove)
  })
}
