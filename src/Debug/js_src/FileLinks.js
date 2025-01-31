import $ from 'jquery'

var config

export function init ($root) {
  config = $root.data('config').get()
  $root.on('config.debug.updated', function (e, changedOpt) {
    e.stopPropagation()
    if (changedOpt === 'linkFilesTemplate') {
      config = $root.data('config').get()
      update($root)
    }
  })
}

/**
 * Linkify files if not already done or update already linked files
 */
export function update ($group) {
  var remove = !config.linkFiles || config.linkFilesTemplate.length === 0
  $group.find('li[data-detect-files]').each(function () {
    create($(this), $(this).find('.t_string'), remove)
  })
}

/**
 * Create text editor links for error, warn, & trace
 */
export function create ($entry, $strings, remove) {
  var $objects = $entry.find('.t_object > .object-inner > .property.debug-value > .t_identifier').filter(function () {
    return this.innerText.match(/^file$/)
  })
  var detectFiles = $entry.data('detectFiles') === true || $objects.length > 0
  if (!config.linkFiles && !remove) {
    return
  }
  if (detectFiles === false) {
    return
  }
  // console.warn('createFileLinks', remove, $entry[0], $strings)
  if ($entry.is('.m_trace')) {
    createFileLinksTrace($entry, remove)
    return
  }
  // don't remove data... link template may change
  // $entry.removeData('detectFiles foundFiles')
  if ($entry.is('[data-file]')) {
    /*
      Log entry link
    */
    createFileLinkDataFile($entry, remove)
    return
  }
  createFileLinksStrings($entry, $strings, remove)
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

function createFileLinksStrings ($entry, $strings, remove) {
  var dataFoundFiles = $entry.data('foundFiles') || []
  if ($entry.is('.m_table')) {
    $strings = $entry.find('> table > tbody > tr > .t_string')
  }
  if (!$strings) {
    $strings = []
  }
  $.each($strings, function () {
    createFileLink(this, remove, dataFoundFiles)
  })
}

function createFileLinkDataFile ($entry, remove) {
  // console.warn('createFileLinkDataFile', $entry)
  $entry.find('> .file-link').remove()
  if (remove) {
    return
  }
  $entry.append($('<a>', {
    html: '<i class="fa fa-external-link"></i>',
    href: buildFileLink($entry.data('file'), $entry.data('line')),
    title: 'Open in editor',
    class: 'file-link lpad'
  })[0].outerHTML)
}

function createFileLinksTrace ($entry, remove) {
  var isUpdate = $entry.find('.file-link').length > 0
  if (!isUpdate) {
    $entry.find('table thead tr > *:last-child').after('<th></th>')
  } else if (remove) {
    $entry.find('table t:not(.context) > *:last-child').remove()
    return
  }
  $entry.find('table tbody tr').each(function () {
    createFileLinksTraceProcessTr($(this), isUpdate)
  })
}

function createFileLinksTraceProcessTr($tr, isUpdate) {
  var $tds = $tr.find('> td')
  var info = {
    file: $tr.data('file') || $tds.eq(0).text(),
    line: $tr.data('line') || $tds.eq(1).text()
  }
  var $a = $('<a>', {
    class: 'file-link',
    href: buildFileLink(info.file, info.line),
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
    return // continue
  }
  $tds.last().after($('<td/>', {
    class: 'text-center',
    html: $a
  }))
}

function createFileLink (string, remove, foundFiles) {
  var $replace
  var $string = $(string)
  var attrs = string.attributes // attrs is not a plain object, but an array of attribute nodes
                                //    which contain both the name and value
  var text = $.trim($string.text())
  var matches = createFileLinkMatches($string, foundFiles)
  var action = 'create'
  if (remove) {
    action = 'remove'
  } else if ($string.hasClass('file-link')) {
    action = 'update'
  }
  if ($string.closest('.m_trace').length) {
    // not recursion...  will end up calling createFileLinksTrace
    create($string.closest('.m_trace'))
    return
  }
  if (matches.length < 1) {
    return
  }

  $replace = createFileLinkReplace($string, matches, text, action)

  /*
  console.warn('createFileLink', {
    action: action,
    matches: matches,
    // stringOuterHTML: string.outerHTML,
    stringText: text,
    replace: $replace[0].outerHTML
  })
  */
  if (action !== 'update') {
    createFileLinkUpdateAttr($string, $replace, attrs)
  }
  if ($string.is('td, th, li') === false) {
    $string.replaceWith($replace)
    return
  }
  $string.html(remove
    ? text
    : $replace
  )
}

function createFileLinkUpdateAttr ($string, $replace, attrs) {
  $.each(attrs, function () {
    if (typeof this === 'undefined') {
      return // continue
    }
    var name = this.name
    if (['html', 'href', 'title'].indexOf(name) > -1) {
      return // continue
    }
    if (name === 'class') {
      $replace.addClass(this.value)
      $string.removeClass('t_string')
      return // continue
    }
    $replace.attr(name, this.value)
    $string.removeAttr(name)
  })
  if (attrs.style) {
    // why is this necessary?
    $replace.attr('style', attrs.style.value)
  }
}

function createFileLinkReplace ($string, matches, text, action) {
  var $replace
  if (action === 'remove') {
    $replace = $('<span>', {
      text: text
    })
    $string.removeClass('file-link') // remove so doesn't get added to $replace
  } else if (action === 'update') {
    $replace = $string
    $replace.prop('href', buildFileLink(matches[1], matches[2]))
  } else {
    $replace = $('<a>', {
      class: 'file-link',
      href: buildFileLink(matches[1], matches[2]),
      html: text + ' <i class="fa fa-external-link"></i>',
      title: 'Open in editor'
    })
  }
  return $replace
}

function createFileLinkMatches ($string, foundFiles) {
  var matches = []
  var text = $.trim($string.text())
  if ($string.data('file')) {
    // filepath specified in data-file attr
    return typeof $string.data('file') === 'boolean'
      ? [null, text, 1]
      : [null, $string.data('file'), $string.data('line') || 1]
  }
  if (foundFiles.indexOf(text) === 0) {
    return [null, text, 1]
  }
  if ($string.parent('.property.debug-value').find('> .t_identifier').text().match(/^file$/)) {
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
    return [null, text, matches.line]
  }
  return text.match(/^(\/.+\.php)(?: \(line (\d+)(, eval'd line \d+)?\))?$/) || []
}
