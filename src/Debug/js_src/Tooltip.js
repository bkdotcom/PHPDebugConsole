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
  var title
  if ($ref.hasClass('fa-hashtag')) {
    attributes = $ref.parent().data('attributes')
    return buildAttributes(attributes)
  }
  title = $ref.prop('title')
  if (!title) {
    return
  }
  $ref.data('titleOrig', title)
  if (title === 'Deprecated') {
    title = tippyContentDeprecated($ref, title)
  } else if (['Inherited', 'Private ancestor'].indexOf(title) > -1) {
    title = tippyContentInherited($ref, title)
  } else if (title === 'Open in editor') {
    title = '<i class="fa fa-pencil"></i> ' + title
  } else if (title.match(/^\/.+: line \d+$/)) {
    title = '<i class="fa fa-file-code-o"></i> ' + title
  }
  return title.replace(/\n/g, '<br />')
}

function tippyContentDeprecated ($ref, title) {
  var titleMore = $ref.parent().data('deprecatedDesc')
  return titleMore
    ? 'Deprecated: ' + titleMore
    : title
}

function tippyContentInherited ($ref, title) {
  var titleMore = $ref.parent().data('inheritedFrom')
  if (typeof titleMore === 'undefined') {
    return title
  }
  title = title === 'Inherited'
    ? 'Inherited from'
    : title + '<br />defined in'
  titleMore = '<span class="classname">' +
    titleMore.replace(/^(.*\\)(.+)$/, '<span class="namespace">$1</span>$2') +
    '</span>'
  return title + ' ' + titleMore
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

function buildAttributes (attributes) {
  var i
  var count = attributes.length
  var html = '<dl>' +
    '<dt class="attributes">attributes</dt>'
  for (i = 0; i < count; i++) {
    html += buildAttribute(attributes[i])
  }
  html += '</dl>'
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
