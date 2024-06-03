import $ from 'jquery'
import { delegate } from 'tippy.js'

export function init ($root) {
  delegate($root[0], {
    target: '.fa-hashtag, [title]',
    delay: [200, null], // show / hide delay (null = default)
    allowHTML: true,
    maxWidth: 'none',
    appendTo: function (reference) {
      return $(reference).closest('.group-body, .debug')[0]
    },
    content: tippyContent,
    onHide: tippyOnHide,
    onMount: tippyOnMount,
    onShow: tippyOnShow
  })
}

function tippyContent (reference) {
  var $ref = $(reference)
  var attributes
  var chars
  var title
  if ($ref.hasClass('fa-hashtag')) {
    attributes = $ref.parent().data('attributes')
    chars = $ref.parent().data('chars') || []
    return buildAttributes(attributes, chars)
  }
  title = $ref.prop('title') || $ref.data('titleOrig')
  if (!title) {
    return
  }
  $ref.data('titleOrig', title)
  if (title === 'Deprecated') {
    title = tippyContentDeprecated($ref, title)
  } else if (title === 'Implements') {
    title = tippyContentImplements($ref, title)
  } else if (['Inherited', 'Private ancestor'].indexOf(title) > -1) {
    title = tippyContentInherited($ref, title)
  } else if (title === 'Overrides') {
    title = tippyContentOverrides($ref, title)
  } else if (title === 'Open in editor') {
    title = '<i class="fa fa-pencil"></i> ' + title
  } else if (title === 'Throws') {
     title = tippyContentThrows($ref, title)
  } else if (title.match(/^\/.+: line \d+( \(eval'd line \d+\))?$/)) {
    title = '<i class="fa fa-file-code-o"></i> ' + title
  }
  if ($ref.parent().hasClass('hasTooltip')) {
    title = title + '<br /><br />' + tippyContent($ref.parent()[0])
  }
  return title.replace(/\n/g, '<br />')
}

function tippyContentDeprecated ($ref, title) {
  var titleMore = $ref.parent().data('deprecatedDesc')
  return titleMore
    ? 'Deprecated: ' + titleMore
    : title
}

function tippyContentImplements ($ref, title) {
  var classname = $ref.parent().data('implements')
  return title + ' ' + markupClassname(classname)
}

function tippyContentInherited ($ref, title) {
  var classname = $ref.parent().data('inheritedFrom')
  if (typeof classname === 'undefined') {
    return title
  }
  title = title === 'Inherited'
    ? 'Inherited from'
    : title + '<br />defined in' // private ancestor
  return title + ' ' + markupClassname(classname)
}

function tippyContentOverrides ($ref, title) {
  var classname = $ref.parent().data('declaredPrev')
  return classname
    ? title + ' ' + markupClassname(classname)
    : title
}

function tippyContentThrows ($ref, title) {
  var throws = $ref.parent().data('throws')
  var i
  var count
  var info
  var $dl = $('<dl class="dl-horizontal"></dl>')
  for (i = 0, count = throws.length; i < count; i++) {
    info = throws[i]
    $dl.append($('<dt></dt>').html(markupClassname(info.type)))
    if (info.desc) {
      $dl.append($('<dd></dd>').html(info.desc))
    }
  }
  return title + $dl[0].outerHTML
}

function tippyOnHide (instance) {
  var $ref = $(instance.reference)
  var title = $ref.data('titleOrig')
  if (title) {
    $ref.attr('title', title)
  }
  setTimeout(function () {
    instance.destroy()
  }, 100)
}

function tippyOnMount (instance) {
  var $ref = $(instance.reference)
  var modifiersNew = [
    {
      name: 'flip',
      options: {
        boundary: $ref.closest('.tab-panes')[0],
        padding: 5
      }
    },
    {
      name: 'preventOverflow',
      options: {
        boundary: $ref.closest('.tab-body')[0],
        padding: { top: 2, bottom: 2, left: 5, right: 5 }
      }
    }
  ]
  // console.log('popperInstance options before', instance.popperInstance.state.options)
  instance.popperInstance.setOptions({
    modifiers: mergeModifiers(instance.popperInstance.state.options.modifiers, modifiersNew)
  })
  // console.log('popperInstance options after', instance.popperInstance.state.options)
}

function tippyOnShow (instance) {
  var $ref = $(instance.reference)
  $ref.removeAttr('title')
  $ref.addClass('hasTooltip')
  $ref.parents('.hasTooltip').each(function () {
    if (this._tippy) {
      this._tippy.hide()
    }
  })
  return true
}

function mergeModifiers (modCur, modNew) {
  var i
  var count
  var modifier
  var names = []
  for (i = 0, count = modNew.length; i < count; i++) {
    modifier = modNew[i]
    names.push(modifier.name)
  }
  for (i = 0, count = modCur.length; i < count; i++) {
    modifier = modCur[i]
    if (names.indexOf(modifier.name) > -1) {
      continue
    }
    modNew.push(modifier)
  }
  return modNew
}

function buildAttributes (attributes, chars) {
  var i
  var count = attributes.length
  var charRegex = new RegExp('[' + chars.join('') + ']', 'gu')
  var html = '<dl>' +
    '<dt class="attributes">attributes</dt>'
  for (i = 0; i < count; i++) {
    html += buildAttribute(attributes[i])
  }
  html += '</dl>'
  html = html.replace(charRegex, function (char) {
    return '<span class="unicode">' + char + '</span>'
  })
  return html
}

function buildAttribute (attribute) {
  var html = '<dd class="attribute">'
  var arg
  var args = []
  html += markupClassname(attribute.name)
  if (Object.keys(attribute.arguments).length) {
    $.each(attribute.arguments, function (i, val) {
      arg = i.match(/^\d+$/) === null
        ? '<span class="t_parameter-name">' + i + '</span><span class="t_punct">:</span>'
        : ''
      arg += dumpSimple(val)
      args.push(arg)
    })
    html += '<span class="t_punct">(</span>' +
      args.join('<span class="t_punct">,</span> ') +
      '<span class="t_punct">)</span>'
  }
  html += '</dd>'
  return html
}

function dumpSimple (val) {
  var type = 'string'
  if (typeof val === 'number') {
    type = val % 1 === 0
      ? 'int'
      : 'float'
  }
  if (typeof val === 'string' && val.length && val.match(/^\d*(\.\d+)?$/) !== null) {
    type = 'string numeric'
  }
  return '<span class="t_' + type + '">' + val + '</span>'
}

function markupClassname (val) {
  var matches = val.match(/^(.+\\)([^\\]+)$/)
  val = matches
    ? '<span class="namespace">' + matches[1] + '</span>' + matches[2]
    : val
  return '<span class="classname">' + val + '</span>'
}
