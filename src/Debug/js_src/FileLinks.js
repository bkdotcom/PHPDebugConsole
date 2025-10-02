import $ from 'zest'

var config
var $debug

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
  $group.find('li[class*=m_]').each(function () {
    create($(this), $(this).find('[data-type-more=filepath]'), remove)
  })
}

/**
 * Create text editor links
 *
 * $entry logentry node
 * $filepath optional [data-type-more=filepath] nodes
 * bool remove  if true, remove links
 */
export function create ($entry, $filepaths, remove) {
  $debug = $entry.closest('.debug')
  if (!config.linkFiles && !remove) {
    return
  }
  if ($entry.is('.m_trace')) {
    createFileLinksTrace($entry, remove)
    return
  }
  if ($entry.is('[data-file]')) {
    createFileLinkDataFile($entry, remove)
    return
  }
  $.each($filepaths || $(), function () {
    createFileLink(this, remove) // , dataFoundFiles
  })
}

function buildFileHref (file, line, docRoot) {
  // console.warn('buildfileHref', {file, line, docRoot})
  var data = {
    file: docRoot
      ? file.replace(/^DOCUMENT_ROOT\b/, docRoot)
      : file,
    line: line || 1,
  }
  return config.linkFilesTemplate.replace(
    /%(\w*)\b/g,
    function (m, key) {
      return Object.hasOwn(data, key)
        ? data[key]
        : ''
    }
  )
}

function createFileLinkDataFile ($entry, remove) {
  // console.warn('createFileLinkDataFile', $entry)
  var docRoot = $debug.data('meta').DOCUMENT_ROOT
  $entry.find('> .file-link').remove()
  if (remove) {
    return
  }
  $entry.append($('<a>', {
    html: '<i class="fa fa-external-link"></i>',
    href: buildFileHref($entry.data('file'), $entry.data('line'), docRoot),
    title: 'Open in editor',
    class: 'file-link lpad'
  })[0].outerHTML)
}

function createFileLinksTrace ($entry, remove) {
  var isUpdate = $entry.find('.file-link').length > 0
  if (!isUpdate) {
    $entry.find('table thead tr > *:last-child').after('<th></th>')
  } else if (remove) {
    $entry.find('table tr:not(.context) > *:last-child').remove()
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
  var docRoot = $debug.data('meta').DOCUMENT_ROOT ?? ''
  var $a = $('<a/>', {
    class: 'file-link',
    href: buildFileHref(info.file, info.line, docRoot),
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
    html: $a,
  }))
}

function createFileLink (filepath, remove) { // , foundFiles
  var $filepath = $(filepath)
  var action = 'create'
  // console.warn('createFileLink', {filepath, remove})
  if (remove) {
    action = 'remove'
  } else if ($filepath.hasClass('file-link')) {
    action = 'update'
  }
  if ($filepath.closest('.m_trace').length) {
    // not recursion...  will end up calling createFileLinksTrace
    // create($filepath.closest('.m_trace'))
    return
  }
  createFileLinkDo($filepath, action)
}

function createFileLinkDo ($filepath, action) {
  var $replace = createFileLinkReplace($filepath, action)
  if ($filepath.is('li, td, th') === false) {
    $filepath.replaceWith($replace)
    return
  }
  $filepath.html(action === 'remove'
    ? $replace.html()
    : $replace
  )
}

function createFileLinkMoveAttr ($src, $dest) {
  // attributes is not a plain object, but an array of attribute nodes
  //   which contain both the name and value
  var attrs = $src[0].attributes
  $.each(attrs, function () {
    if (typeof this === 'undefined') {
      return // continue
    }
    var name = this.name
    if (['html', 'href', 'title'].indexOf(name) > -1) {
      return // continue
    }
    $src.removeAttr(name)
    if (name === 'class') {
      // append to existing dest classes
      $dest.addClass(this.value)
      // $filepath.removeClass('t_string')
      return // continue
    }
    $dest.attr(name, this.value)
  })
  /*
  if (attrs.style) {
    // why is this necessary?
    $replace.attr('style', attrs.style.value)
  }
  */
}

function createFileLinkReplace ($filepath, action) {
  var docRoot = $debug.data('meta').DOCUMENT_ROOT
  var $replace
  var matches = createFileLinkMatches($filepath)
  if (action === 'update') {
    $replace = $filepath.prop('href', buildFileHref(matches[1], matches[2], docRoot))
  } else if (action === 'create') {
    $replace = $('<a>', {
      class: 'file-link',
      href: buildFileHref(matches[1], matches[2], docRoot),
      html: $filepath.html() + ' <i class="fa fa-external-link"></i>',
      title: 'Open in editor'
    })
  } else {
    // remove
    $filepath.find('i.fa-external-link').remove()
    $filepath.removeClass('file-link') // prevent proagation to replace
    $replace = $('<span>', {
      // text: text
      html: $filepath.html()
    })
  }
  createFileLinkMoveAttr($filepath, $replace)
  return $replace
}

function createFileLinkMatches ($filepath) { // , foundFiles
  var matches = []
  var text = $filepath.text().trim()
  // console.warn('createFileLinkMatches', text)
  if ($filepath.parent('.property.debug-value').find('> .t_identifier').text().match(/^file$/)) {
    // object with file .debug-value
    matches = {
      line: 1
    }
    $filepath.parent().parent().find('> .property.debug-value').each(function () {
      var prop = $(this).find('> .t_identifier').text().trim()
      var val = $(this).find('> *:last-child').text().trim()
      matches[prop] = val
    })
    return [null, text, matches.line]
  }
  return text.match(/^(.+?)(?: \(.+? (\d+)(, .+ \d+)?\))?$/) || []
}
