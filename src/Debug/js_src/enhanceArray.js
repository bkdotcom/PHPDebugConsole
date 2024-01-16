import $ from 'jquery'

var config

export function init($root)
{
  config = $root.data('config').get()
}

/**
 * Adds expand/collapse functionality to array
 * does not enhance values
 */
export function enhance ($node) {
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
