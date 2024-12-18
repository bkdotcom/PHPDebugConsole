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
  var title = $ref.prop('title') || $ref.data('titleOrig')
  if ($ref.hasClass('fa-hashtag')) {
    return tippyContentAttributes($ref)
  }
  if (!title) {
    return
  }
  $ref.data('titleOrig', title)
  $ref.removeAttr('title')
  $ref.addClass('hasTooltip')
  return tippyContentBuildTitle($ref, title)
}

function tippyContentBuildTitle($ref, title) {
  var $parent = $ref.parent()
  title = refTitle($ref, title)
  if ($parent.prop('title') || $parent.data('titleOrig')) {
    title = title + '<br /><br />' + tippyContent($parent[0])
  }
  return title.replace(/\n/g, '<br />')
}

function refTitle($ref, title) {
  switch (title) {
    case 'Deprecated':
      return refTitleDeprecated($ref, title)
    case 'Implements':
      return refTitleImplements($ref, title)
    case 'Inherited':
    case 'Private ancestor':
      return refTitleInherited($ref, title)
    case 'Overrides':
      return refTitleOverrides($ref, title)
    case 'Open in editor':
      return '<i class="fa fa-pencil"></i> ' + title
    case 'Throws':
      return refTitleThrows($ref, title)
  }
  return title.match(/^\/.+: line \d+( \(eval'd line \d+\))?$/)
    ? '<i class="fa fa-file-code-o"></i> ' + title
    : title
}

function refTitleDeprecated ($ref, title) {
  var titleMore = $ref.parent().data('deprecatedDesc')
  return titleMore
    ? 'Deprecated: ' + titleMore
    : title
}

function refTitleImplements ($ref, title) {
  var className = $ref.parent().data('implements')
  var $interface = $ref.closest('.object-inner').find('> .implements span[data-interface]').filter(function ($node) {
    return $(this).data('interface') === className
  })
  return title + ' ' + $interface[0].innerHTML
}

function refTitleInherited ($ref, title) {
  var classname = $ref.parent().data('inheritedFrom')
  if (typeof classname === 'undefined') {
    return title
  }
  title = title === 'Inherited'
    ? 'Inherited from'
    : title + '<br />defined in' // private ancestor
  return title + ' ' + markupClassname(classname)
}

function refTitleOverrides ($ref, title) {
  var classname = $ref.parent().data('declaredPrev')
  return classname
    ? title + ' ' + markupClassname(classname)
    : title
}

function refTitleThrows ($ref, title) {
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

function tippyContentAttributes ($ref) {
  var attributes = $ref.parent().data('attributes').map(function (attr) {
    return buildAttribute(attr)
  })
  var chars = $ref.parent().data('chars') || []
  var charRegex = new RegExp('[' + chars.join('') + ']', 'gu')
  var html = '<dl>' +
    '<dt class="attributes">attributes</dt>' +
    attributes.join('') +
    '</dl>'
  return html.replace(charRegex, function (char) {
    return '<span class="unicode">' + char + '</span>'
  })
}

function tippyOnHide (instance) {
  var $ref = $(instance.reference)
  var titleOrig = $ref.data('titleOrig')
  if (titleOrig) {
    $ref.attr('title', titleOrig)
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
  // var $ref = $(instance.reference)
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
